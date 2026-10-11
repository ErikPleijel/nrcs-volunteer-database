<?php

namespace App\Support\Divisions;

/**
 * Corrections to division names imported from the legacy database, keyed by
 * division id (the legacy DivisionID, kept as divisions.id by
 * migrate:divisions, so it is stable across re-imports).
 *
 * Applied in two places, so a correction survives a fresh legacy import:
 *   - MigrateDivisions, when it inserts a legacy division;
 *   - a data migration, for databases imported before the correction.
 * Both only replace the exact legacy spelling: a name that has since been
 * changed some other way is left alone.
 */
final class DivisionNameCorrections
{
    /** id => [legacy spelling, corrected name] */
    public const BY_ID = [
        3289 => ['lkwo', 'Ikwo'],                // Ebonyi: lowercase L for capital I
        3317 => ['Ijero,', 'Ijero'],             // Ekiti
        3325 => ['Ilejemeje.', 'Ilejemeje'],     // Ekiti
        3326 => ['Enugu South,', 'Enugu South'], // Enugu
        3341 => ['Udenu.', 'Udenu'],             // Enugu
        3411 => ['jaba', 'Jaba'],                // Kaduna
        3454 => ['kumbotso', 'Kumbotso'],        // Kano
        3774 => ['kebbe', 'Kebbe'],              // Sokoto
    ];

    /**
     * The name to store for a legacy division: the corrected name when
     * $name is exactly the known legacy spelling, otherwise $name unchanged.
     */
    public static function apply(int $id, ?string $name): ?string
    {
        $correction = self::BY_ID[$id] ?? null;

        return $correction !== null && $name === $correction[0] ? $correction[1] : $name;
    }
}
