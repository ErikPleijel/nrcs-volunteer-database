<?php

namespace App\Console\Commands;

use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a letter O/o typed instead of the digit 0 in telephone1 and
 * telephone2 ('O8031234567' → '08031234567'), but only where the result is
 * a valid Nigerian mobile number (PhoneNumber::toE164()). Nothing else in
 * the value changes — spacing and +234 stay as typed. Values with an O that
 * would still not be a valid number are left alone and listed.
 *
 * Dry-run unless --commit. Writes go through the query builder, row by row
 * and only if the value is still the one read, so no model events,
 * observers, audit log entries or updated_at changes. Safe to run again:
 * a fixed value has no O left.
 */
class FixPhoneLetterO extends Command
{
    protected $signature = 'users:fix-phone-letter-o
                            {--dry-run : Preview only (the default)}
                            {--commit : Actually write the fixes}';

    protected $description = 'Replace a letter O/o typed instead of 0 in telephone1/2 where that gives a valid phone number (dry-run unless --commit).';

    private const COLUMNS = ['telephone1', 'telephone2'];

    private const EXAMPLES = 20;

    public function handle(): int
    {
        $commit = (bool) $this->option('commit') && ! $this->option('dry-run');

        $commit ? $this->warn('COMMIT mode — phone numbers WILL be changed.')
                : $this->info('[dry-run] No writes. Use --commit to write.');

        $fixes = [];
        $skipped = [];
        $fixed = 0;

        DB::table('users')
            ->select(['id', ...self::COLUMNS])
            ->where(function ($q) {
                foreach (self::COLUMNS as $column) {
                    // LIKE is case-insensitive here, so this finds O and o.
                    $q->orWhere($column, 'like', '%o%');
                }
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($commit, &$fixes, &$skipped, &$fixed) {
                foreach ($rows as $row) {
                    foreach (self::COLUMNS as $column) {
                        $before = (string) $row->{$column};
                        if (! preg_match('/[Oo]/', $before)) {
                            continue;
                        }

                        $after = str_replace(['O', 'o'], '0', $before);

                        if (! $this->isValidPhone($after)) {
                            $skipped[] = [$row->id, $column, $before];

                            continue;
                        }

                        $fixes[] = [$row->id, $column, $before, $after];

                        if ($commit) {
                            $fixed += DB::table('users')
                                ->where('id', $row->id)
                                ->where($column, $before)
                                ->update([$column => $after]);
                        }
                    }
                }
            });

        $this->newLine();
        $this->line(($commit ? 'Fixed' : 'Would fix').': '.($commit ? $fixed : count($fixes))
            .' (telephone1: '.$this->countFor($fixes, 'telephone1').', telephone2: '.$this->countFor($fixes, 'telephone2').')');
        $this->line('Skipped (still not a valid phone number): '.count($skipped));

        if ($fixes !== []) {
            $this->newLine();
            $this->line('Examples (first '.min(self::EXAMPLES, count($fixes)).'):');
            foreach (array_slice($fixes, 0, self::EXAMPLES) as [$id, $column, $before, $after]) {
                $this->line("  DB-{$id} {$column}: {$before} → {$after}");
            }
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->line('Left untouched:');
            foreach ($skipped as [$id, $column, $value]) {
                $this->line("  DB-{$id} {$column}: {$value}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * A Nigerian mobile number (toE164) that PhoneNumber::normalize() — used
     * by phone login and the duplicate report — also reduces to its 10-digit
     * national number. Rules out '009080228068', which toE164 reads as an
     * international 00 prefix but normalize() leaves at 11 digits.
     */
    private function isValidPhone(string $value): bool
    {
        return PhoneNumber::toE164($value) !== null
            && strlen(PhoneNumber::normalize($value)) === 10;
    }

    private function countFor(array $fixes, string $column): int
    {
        return count(array_filter($fixes, fn ($fix) => $fix[1] === $column));
    }
}
