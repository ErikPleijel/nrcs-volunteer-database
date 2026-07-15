<?php

namespace App\View\Composers;

use App\Services\Reports\RedCrossUnitStatsService;
use Illuminate\View\View;

class RedCrossUnitStatsComposer
{
    protected $statsService;

    public function __construct(RedCrossUnitStatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $view->with('redCrossUnitStats', $this->statsService->getComprehensiveStats());
    }
}
