<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Some legacy names store "&amp;" literally (e.g. red_cross_units 389, 934).
 * The home page map now HTML-escapes names, so these would show as "&amp;".
 * Replace "&amp;" with "&" in red_cross_units, branches and divisions names.
 */
return new class extends Migration
{
    public function up(): void
    {
        $changed = [];

        foreach (['red_cross_units', 'branches', 'divisions'] as $table) {
            $changed[] = $table.': '.DB::table($table)
                ->where('name', 'like', '%&amp;%')
                ->update(['name' => DB::raw("REPLACE(name, '&amp;', '&')")]);
        }

        $message = 'Decoded &amp; in names: '.implode(', ', $changed).'.';
        Log::info($message);

        if (! app()->runningUnitTests()) {
            (new ConsoleOutput)->writeln("  {$message}");
        }
    }

    public function down(): void
    {
        // Irreversible: decoded names cannot be told apart from ones typed with "&".
    }
};
