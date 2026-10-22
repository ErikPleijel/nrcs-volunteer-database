<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The "Useful links" and "Downloads" sections at the bottom of My Profile.
 * Each list is one json setting in the 'profile_resources' group, edited by
 * the National DB administrator on its own Settings page
 * (ProfileResourcesSettingsController) — never on the generic settings form,
 * which would show the raw JSON.
 *
 * Link rows:     {title, url, description}
 * Download rows: {id, title, url, path, file_name, description} — either an
 *                external url, or an uploaded file on the private 'local'
 *                disk (path) served to logged-in users by
 *                ProfileDownloadController at /downloads/{id}.
 */
class ProfileResources
{
    public const GROUP = 'profile_resources';

    public const LINKS_KEY = 'profile.useful_links';

    public const DOWNLOADS_KEY = 'profile.downloads';

    public const DISK = 'local';

    public const DIRECTORY = 'profile-downloads';

    /** Allowed upload extensions => [label, Font Awesome icon]. */
    public const FILE_TYPES = [
        'pdf' => ['PDF', 'fa-file-pdf'],
        'docx' => ['Word', 'fa-file-word'],
        'xlsx' => ['Excel', 'fa-file-excel'],
    ];

    /** Largest upload, in kilobytes (the max: validation rule). */
    public const MAX_UPLOAD_KB = 20480;

    /**
     * The links to show, in order. Rows without a title or with anything
     * other than an http(s) url are left out.
     *
     * @return list<array{title: string, url: string, description: string}>
     */
    public static function links(): array
    {
        $links = [];
        foreach (self::stored(self::LINKS_KEY) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($title !== '' && self::isSafeUrl($url)) {
                $links[] = [
                    'title' => $title,
                    'url' => $url,
                    'description' => trim((string) ($row['description'] ?? '')),
                ];
            }
        }

        return $links;
    }

    /**
     * The downloads to show, in order: rows with a title and either an
     * uploaded file or an http(s) url.
     *
     * @return list<array{id: string, title: string, url: ?string, path: ?string, file_name: ?string, description: string}>
     */
    public static function downloads(): array
    {
        $downloads = [];
        foreach (self::stored(self::DOWNLOADS_KEY) as $row) {
            $id = (string) ($row['id'] ?? '');
            $title = trim((string) ($row['title'] ?? ''));
            $path = $row['path'] ?? null;
            $url = $row['url'] ?? null;

            $hasFile = is_string($path) && $path !== '';
            $hasUrl = is_string($url) && self::isSafeUrl($url);

            if ($id === '' || $title === '' || (! $hasFile && ! $hasUrl)) {
                continue;
            }

            $downloads[] = [
                'id' => $id,
                'title' => $title,
                'url' => $hasFile ? null : $url,
                'path' => $hasFile ? $path : null,
                'file_name' => $hasFile ? (string) ($row['file_name'] ?? basename($path)) : null,
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $downloads;
    }

    /**
     * Every uploaded file path in the stored downloads list, including rows
     * downloads() would leave out — used to find files to remove from disk.
     *
     * @return list<string>
     */
    public static function storedPaths(): array
    {
        $paths = [];
        foreach (self::stored(self::DOWNLOADS_KEY) as $row) {
            if (is_string($row['path'] ?? null) && $row['path'] !== '') {
                $paths[] = $row['path'];
            }
        }

        return $paths;
    }

    /** The download row with this id that has an uploaded file, if any. */
    public static function findFile(string $id): ?array
    {
        foreach (self::downloads() as $download) {
            if ($download['id'] === $id && $download['path'] !== null) {
                return $download;
            }
        }

        return null;
    }

    /** True for an absolute http or https url with a host. */
    public static function isSafeUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    /** The url's host without a leading "www.", e.g. "ifrc.org". */
    public static function domain(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * The file type of a download — from the uploaded file, or from an
     * external url ending in .pdf/.docx/.xlsx — as [label, icon]; a plain
     * web link otherwise.
     *
     * @return array{0: string, 1: string}
     */
    public static function fileType(array $download): array
    {
        $name = $download['path'] ?? (string) parse_url((string) ($download['url'] ?? ''), PHP_URL_PATH);
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return self::FILE_TYPES[$extension] ?? ['Web link', 'fa-link'];
    }

    /** @return list<array> */
    private static function stored(string $key): array
    {
        $value = Setting::get($key, []);

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
