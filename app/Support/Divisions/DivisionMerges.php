<?php

namespace App\Support\Divisions;

/**
 * Legacy divisions that are not LGAs of their own and are merged into the
 * division of the LGA they belong to, keyed by division id (the legacy
 * DivisionID).
 *
 * Applied in two places, so a merge survives a fresh legacy import:
 *   - the legacy import never creates a merged-away division and maps its
 *     id to the target wherever it writes a division id (target());
 *   - a data migration moves everything in databases imported earlier
 *     (DivisionMerger).
 */
final class DivisionMerges
{
    /** merged-away division id => division it is merged into */
    public const INTO = [
        // Ondo: Okeigbo is half of the Ile Oluji/Okeigbo LGA (see the
        // 3653 entry in DivisionNameCorrections).
        3654 => 3653,
    ];

    /**
     * The division id to store for a legacy division id: the merge target
     * for a merged-away division, otherwise the id itself (null and 0 as
     * given, for the importers' own null handling).
     */
    public static function target(int|string|null $id): int|string|null
    {
        if ($id === null || $id === '' || ! is_numeric($id)) {
            return $id;
        }

        return self::INTO[(int) $id] ?? $id;
    }

    public static function isMergedAway(int|string|null $id): bool
    {
        return is_numeric($id) && array_key_exists((int) $id, self::INTO);
    }
}
