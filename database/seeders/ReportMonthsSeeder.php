<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportMonthsSeeder extends Seeder
{
    public function run(): void
    {
        // 5 years back from now
        $start = now()->subYears(5)->startOfMonth();

        // 2 years into the future
        $end = now()->addYears(2)->startOfMonth();

        $rows = [];
        $now  = now();

        for ($date = $start->copy(); $date <= $end; $date->addMonth()) {
            $rows[] = [
                'month_start' => $date->toDateString(),
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // idempotent: if rerun, it won't create duplicates thanks to unique index
        DB::table('report_months')->upsert(
            $rows,
            ['month_start'],   // unique key
            ['updated_at']     // fields to update on conflict
        );
    }
}
