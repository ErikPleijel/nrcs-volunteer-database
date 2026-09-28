<?php

namespace App\Support;

class DbNumber
{
    /**
     * Parse a typed DB number back to a users.id, or null if it isn't one.
     *
     * Accepts the forms people copy off ID cards and profiles:
     * 'DB-123456', 'DB 123 456', 'db123456', the thin-space grouping that
     * older printed cards and certificates show ('DB-123 456'), a bare '123456', and
     * the longer card/reference forms 'DB-123456-BRANCH-DIV' and
     * 'DB-123456/BRANCH/DIV/UNIT' — the branch/division suffix is ignored.
     */
    public static function parse(string $raw): ?int
    {
        // Every kind of whitespace, including U+2009 thin space and NBSP.
        $compact = preg_replace('/[\s\p{Z}]+/u', '', $raw);

        // [0-9], not \d: PHP's /u makes \d match non-ASCII digits too.
        if ($compact === null || ! preg_match('/^(?:DB-?)?([0-9]{1,18})(?:[-\/].*)?$/i', $compact, $m)) {
            return null;
        }

        $id = (int) $m[1];

        return $id > 0 ? $id : null;
    }
}
