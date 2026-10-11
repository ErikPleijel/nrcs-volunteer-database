<?php

use App\Services\DivisionMerger;
use App\Support\Divisions\DivisionMerges;
use App\Support\Divisions\DivisionNameCorrections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Okeigbo (3654) is half of the Ile Oluji/Okeigbo LGA in Ondo: merges it
 * into Ile-Oluji (3653) and renames 3653 to the official LGA name, in
 * databases imported before the merge existed (a fresh legacy import never
 * creates 3654 and maps its id itself — DivisionMerges). Every record that
 * pointed at 3654 moves to 3653; see DivisionMerger for the full list.
 *
 * Nothing happens when 3654 is already gone, so it is safe to run on a
 * fresh or already-merged database.
 */
return new class extends Migration
{
    public function up(): void
    {
        [$legacy, $corrected] = DivisionNameCorrections::BY_ID[3653];

        DB::table('divisions')
            ->where('id', 3653)
            ->whereRaw('BINARY name = ?', [$legacy])
            ->update(['name' => $corrected, 'updated_at' => now()]);

        foreach (DivisionMerges::INTO as $fromId => $toId) {
            app(DivisionMerger::class)->merge($fromId, $toId);
        }
    }

    public function down(): void
    {
        // A merge cannot be undone: once both divisions' records are
        // together there is no telling which came from Okeigbo.
    }
};
