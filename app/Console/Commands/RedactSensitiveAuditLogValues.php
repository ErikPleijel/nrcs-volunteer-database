<?php

namespace App\Console\Commands;

use App\Models\Log as AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply Log::redactSensitive() to Audit Log rows written before Log::write()
 * redacted on its own (e.g. membership_payment_deleted rows that embedded the
 * member with a decrypted NIN).
 *
 * Dry run by default. Writes only old_values / new_values, through the query
 * builder so updated_at and every other column are left as they are.
 */
class RedactSensitiveAuditLogValues extends Command
{
    protected $signature = 'audit-log:redact-sensitive
                            {--force : Write the changes (default is a dry run)}';

    protected $description = 'Redact NIN, personal_info and credential keys in stored Audit Log old/new values';

    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $perAction = [];

        $query = DB::table('logs')
            ->select(['id', 'action', 'old_values', 'new_values'])
            ->where(function ($q) {
                foreach (AuditLog::REDACTED_KEYS as $key) {
                    $q->orWhere('old_values', 'like', '%"'.$key.'"%')
                        ->orWhere('new_values', 'like', '%"'.$key.'"%');
                }
            });

        $query->chunkById(500, function ($rows) use ($write, &$perAction) {
            foreach ($rows as $row) {
                $changes = [];

                foreach (['old_values', 'new_values'] as $column) {
                    $decoded = $row->{$column} === null ? null : json_decode($row->{$column}, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $redacted = AuditLog::redactSensitive($decoded);

                    if ($redacted !== $decoded) {
                        $changes[$column] = json_encode($redacted, JSON_UNESCAPED_UNICODE);
                    }
                }

                if ($changes === []) {
                    continue;
                }

                $perAction[$row->action] = ($perAction[$row->action] ?? 0) + 1;

                if ($write) {
                    DB::table('logs')->where('id', $row->id)->update($changes);
                }
            }
        });

        if ($perAction === []) {
            $this->info('No Audit Log rows need redacting.');

            return self::SUCCESS;
        }

        ksort($perAction);
        $this->table(['Action', 'Rows'], collect($perAction)->map(fn ($n, $action) => [$action, $n])->values()->all());

        $total = array_sum($perAction);
        $write
            ? $this->info("Redacted {$total} row(s).")
            : $this->warn("Dry run: {$total} row(s) would change. Re-run with --force to write.");

        return self::SUCCESS;
    }
}
