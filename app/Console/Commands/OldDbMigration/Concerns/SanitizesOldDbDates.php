<?php

namespace App\Console\Commands\OldDbMigration\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared date/timestamp sanitization for old-DB migration commands.
 *
 * Carbon::parse() happily parses technically-valid-but-absurd dates (year 202,
 * year 223, ...) without throwing, so a bare try/catch around it never catches
 * that class of legacy-data corruption. It only surfaces later as a hard SQL
 * error when MySQL rejects the value for being outside the target column
 * type's storable range. This trait adds that range check on top of the
 * Carbon parse, so bad values are caught and nulled out here instead.
 */
trait SanitizesOldDbDates
{
    /**
     * Parse a raw old-DB date/timestamp value and validate it fits the target
     * column type's MySQL-storable range. Returns null (and logs a warning)
     * if the value is unparseable or out of range; otherwise returns the
     * parsed Carbon instance for the caller to format/use as needed.
     *
     * @param  mixed  $value  Raw value read from the old DB.
     * @param  'date'|'timestamp'  $columnType  'date' for DATE/DATETIME target columns
     *         (MySQL storable year range 1000-9999), 'timestamp' for TIMESTAMP target
     *         columns (MySQL storable year range 1970-2038).
     * @param  string  $sourceTable  Old-DB table the value was read from (for logging).
     * @param  mixed  $sourceId  Old-DB row's primary key (or other identifying value), for logging.
     * @param  string  $sourceColumn  Old-DB column the value was read from (for logging).
     */
    protected function sanitizeOldDbDate(
        mixed $value,
        string $columnType,
        string $sourceTable,
        mixed $sourceId,
        string $sourceColumn
    ): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;

        // Legacy MySQL zero-dates are an expected "no value", not corruption.
        if (in_array($raw, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (Throwable $e) {
            $this->logBadOldDbDate($sourceTable, $sourceId, $sourceColumn, $raw, "unparseable ({$e->getMessage()})");

            return null;
        }

        [$minYear, $maxYear] = $columnType === 'timestamp' ? [1970, 2038] : [1000, 9999];

        if ($parsed->year < $minYear || $parsed->year > $maxYear) {
            $this->logBadOldDbDate(
                $sourceTable,
                $sourceId,
                $sourceColumn,
                $raw,
                "year {$parsed->year} outside valid {$columnType} range ({$minYear}-{$maxYear})"
            );

            return null;
        }

        return $parsed;
    }

    private function logBadOldDbDate(string $sourceTable, mixed $sourceId, string $sourceColumn, string $rawValue, string $reason): void
    {
        $message = "Old-DB date sanitized to null: {$sourceTable}.{$sourceColumn} (row {$sourceId}) = '{$rawValue}' — {$reason}";

        $this->warn($message);

        Log::warning($message);
    }
}
