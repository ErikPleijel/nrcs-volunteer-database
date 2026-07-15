<?php

namespace App\View\Components\Campaigns;

use App\Services\Campaigns\CampaignDailyRateStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DailyRateGauges extends Component
{
    public function __construct(private readonly CampaignDailyRateStatsService $statsService)
    {
    }

    public function render(): View|string
    {
        $stats = $this->statsService->summary();

        return view('components.campaigns.daily-rate-gauges', [
            'emailSent' => $stats['email_sent_today'],
            'smsSent' => $stats['sms_sent_today'],
            'emailCap' => $stats['email_cap'],
            'smsCap' => $stats['sms_cap'],
        ]);
    }
}
