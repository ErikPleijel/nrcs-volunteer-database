<?php

namespace App\Services;

class PlaceholderBracketValidator
{
    /**
     * @param  array<string, string>  $bodiesByField  Keyed by form field (email_body, sms_body, etc.)
     * @return array<int, string> Fields whose body still contains a literal '[' or ']' placeholder marker
     */
    public function findBracketPlaceholders(array $bodiesByField): array
    {
        $flagged = [];

        foreach ($bodiesByField as $field => $body) {
            if (! is_string($body) || trim($body) === '') {
                continue;
            }

            if (str_contains($body, '[') || str_contains($body, ']')) {
                $flagged[] = $field;
            }
        }

        return $flagged;
    }
}
