<?php

namespace App\Services;

use App\Models\Setting;

class UrlDomainValidator
{
    private const ALLOWED_DOMAINS_SETTING_KEY = 'campaign.allowed_domains';

    /**
     * Parse allowed domains from settings (comma-separated list like "abc.com, xyz.org").
     *
     * @return array<int, string>
     */
    public function allowedDomains(): array
    {
        // Safe default: if the setting is missing or empty, treat it as no domains allowed.
        $raw = (string) Setting::get(self::ALLOWED_DOMAINS_SETTING_KEY, '');

        $domains = array_map(
            fn (string $domain) => strtolower(trim($domain)),
            explode(',', $raw)
        );

        return array_values(array_filter($domains, fn ($domain) => $domain !== ''));
    }

    /**
     * @param  array<string>  $bodies
     * @return array<int, string> List of disallowed domains (unique, lowercased)
     */
    public function findDisallowedDomains(array $bodies): array
    {
        $disallowed = [];

        foreach ($bodies as $body) {
            if (!is_string($body) || trim($body) === '') {
                continue;
            }

            $domains = $this->extractDomainsFromText($body);

            foreach ($domains as $domain) {
                if (!$this->isAllowedDomain($domain)) {
                    $disallowed[] = $domain;
                }
            }
        }

        return array_values(array_unique($disallowed));
    }

    /**
     * @param  array<string, string>  $bodies Keyed by form field (email_body, sms_body, etc.)
     * @return array<string, array<int, string>>
     */
    public function findDisallowedDomainsByField(array $bodies): array
    {
        $result = [];

        foreach ($bodies as $field => $body) {
            if (!is_string($body) || trim($body) === '') {
                continue;
            }

            $domains = $this->extractDomainsFromText($body);
            $disallowed = array_values(array_filter(
                $domains,
                fn ($domain) => !$this->isAllowedDomain($domain)
            ));

            if (!empty($disallowed)) {
                $result[$field] = array_values(array_unique($disallowed));
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function allowedDomainsNormalized(): array
    {
        return $this->allowedDomains();
    }

    private function isAllowedDomain(string $domain): bool
    {
        $allowedDomains = $this->allowedDomainsNormalized();

        // The app's own domain is always trusted — it's used by built-in
        // links (login snippet, unsubscribe/footer links), not user input.
        if ($appDomain = $this->appDomain()) {
            $allowedDomains[] = $appDomain;
        }

        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain) {
                return true;
            }

            // Allow any subdomain of the configured domain.
            if (str_ends_with($domain, '.' . $allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    private function appDomain(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ? strtolower((string) preg_replace('/^www\./i', '', $host)) : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractDomainsFromText(string $text): array
    {
        preg_match_all('/https?:\/\/[^\s<>"\']+|www\.[^\s<>"\']+/i', $text, $matches);

        $urls = $matches[0] ?? [];

        return array_values(array_filter(array_map([$this, 'extractDomain'], $urls)));
    }

    private function extractDomain(string $url): ?string
    {
        $cleanUrl = rtrim($url, '.,);\'\"]');

        if (stripos($cleanUrl, 'http') !== 0) {
            $cleanUrl = 'http://' . ltrim($cleanUrl);
        }

        $parts = parse_url($cleanUrl);
        $host = $parts['host'] ?? null;

        if (!$host) {
            return null;
        }

        return preg_replace('/^www\./i', '', strtolower($host));
    }
}
