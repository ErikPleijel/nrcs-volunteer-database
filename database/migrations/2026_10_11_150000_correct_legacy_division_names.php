<?php

use App\Support\Divisions\DivisionNameCorrections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Applies DivisionNameCorrections to divisions imported before the
 * corrections existed (a fresh legacy import applies them itself, in
 * MigrateDivisions). Only rows that still have the exact legacy spelling
 * are changed — compared byte for byte, since the column's collation would
 * otherwise treat "jaba" and "Jaba" as equal. Ids are unchanged, so members,
 * payments and every other link stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DivisionNameCorrections::BY_ID as $id => [$legacy, $corrected]) {
            DB::table('divisions')
                ->where('id', $id)
                ->whereRaw('BINARY name = ?', [$legacy])
                ->update(['name' => $corrected, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (DivisionNameCorrections::BY_ID as $id => [$legacy, $corrected]) {
            DB::table('divisions')
                ->where('id', $id)
                ->whereRaw('BINARY name = ?', [$corrected])
                ->update(['name' => $legacy, 'updated_at' => now()]);
        }
    }
};
