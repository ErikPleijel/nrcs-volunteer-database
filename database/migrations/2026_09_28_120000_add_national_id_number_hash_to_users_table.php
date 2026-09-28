<?php

use App\Support\NationalIdNumber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uniqueness for the encrypted users.national_id_number, via a keyed hash
 * column (see App\Support\NationalIdNumber). History: the column was
 * created ->unique() (users_national_id_number_unique), that index was
 * dropped in 2026_05_27_085518 once values were encrypted, and the column
 * was widened to TEXT in 2026_06_25_174641. This adds a separate column and
 * index (users_national_id_number_hash_unique), so neither conflicts.
 *
 * Every existing value is decrypted, normalized and hashed BEFORE the schema
 * changes. If two accounts share a NIN the migration stops, naming them,
 * and leaves the table untouched. Requires NIN_HASH_KEY whenever any
 * NIN is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hashes = [];
        $idsByHash = [];

        DB::table('users')
            ->whereNotNull('national_id_number')
            ->where('national_id_number', '<>', '')
            ->select('id', 'national_id_number')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$hashes, &$idsByHash) {
                foreach ($rows as $row) {
                    try {
                        $plain = Crypt::decryptString($row->national_id_number);
                    } catch (DecryptException) {
                        throw new RuntimeException(
                            "users.id {$row->id}: national_id_number could not be decrypted "
                            .'(plaintext or wrong APP_KEY). Run ndpa:encrypt-national-ids '
                            .'or fix the row, then migrate again.'
                        );
                    }

                    $normalized = NationalIdNumber::normalize($plain);
                    if ($normalized === '') {
                        continue;
                    }

                    $hash = NationalIdNumber::hash($normalized);
                    $hashes[$row->id] = $hash;
                    $idsByHash[$hash][] = $row->id;
                }
            });

        $collisions = array_filter($idsByHash, fn (array $ids) => count($ids) > 1);
        if ($collisions) {
            $groups = array_map(
                fn (array $ids) => implode(', ', array_map(fn ($id) => "DB-{$id}", $ids)),
                array_values($collisions)
            );

            throw new RuntimeException(
                count($collisions).' National ID number(s) are shared by more than one account; '
                .'resolve these before migrating (nothing was changed): '.implode(' | ', $groups)
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->char('national_id_number_hash', 64)->nullable()->unique()->after('national_id_number');
        });

        foreach ($hashes as $id => $hash) {
            DB::table('users')->where('id', $id)->update(['national_id_number_hash' => $hash]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['national_id_number_hash']);
            $table->dropColumn('national_id_number_hash');
        });
    }
};
