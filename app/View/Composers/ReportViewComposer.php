<?php

namespace App\View\Composers;

use Illuminate\View\View;

class ReportViewComposer
{
    /**
     * Bind data to the view.
     *
     * This will run for every view we register it on in ViewServiceProvider.
     */
    public function compose(View $view): void
    {
        // Simple example: last 5 years including current
        $currentYear = now()->year;
        $years = range($currentYear, $currentYear - 4);

        // Selected year comes from ?year=... query param, default to current
        $selectedYear = (int) request('year', $currentYear);

        $view->with([
            'years'        => $years,
            'selectedYear' => $selectedYear,
        ]);
    }
}
