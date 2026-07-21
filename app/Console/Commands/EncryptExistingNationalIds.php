<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptExistingNationalIds extends Command
{
    protected $signature = 'ndpa:encrypt-national-ids {--dry-run : Show what would be encrypted without writing any changes}';

    protected $description = 'Encrypt plaintext national_id_number and personal_info rows in-place using DB::table() to bypass Eloquent casts.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry-run mode — no changes will be written.');
        }

        $encrypted = 0;
        $alreadyDone = 0;

        DB::table('users')
            ->where(function ($q) {
                $q->whereNotNull('national_id_number')
                    ->orWhereNotNull('personal_info');
            })
            ->select('id', 'national_id_number', 'personal_info')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($dryRun, &$encrypted, &$alreadyDone) {
                foreach ($rows as $row) {
                    $updates = [];

                    if ($row->national_id_number !== null && ! $this->isEncrypted($row->national_id_number)) {
                        $updates['national_id_number'] = Crypt::encryptString($row->national_id_number);
                    }

                    if ($row->personal_info !== null && ! $this->isEncrypted($row->personal_info)) {
                        $updates['personal_info'] = Crypt::encryptString($row->personal_info);
                    }

                    if (empty($updates)) {
                        $alreadyDone++;
                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('users')->where('id', $row->id)->update($updates);
                    }

                    $encrypted++;
                }

                $this->line("Running total — to encrypt: {$encrypted}, already encrypted: {$alreadyDone}");
            });

        $verb = $dryRun ? 'Would encrypt' : 'Encrypted';
        $this->info("{$verb} {$encrypted} row(s). {$alreadyDone} row(s) were already encrypted.");

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
