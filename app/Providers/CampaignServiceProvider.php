<?php

namespace App\Providers;

use App\Campaigns\Delivery\CampaignDeliveryService;
use Illuminate\Support\ServiceProvider;

final class CampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CampaignDeliveryService::class, function ($app) {
            $channelClasses = config('campaigns.delivery.channels', []);

            $channels = [];
            foreach ($channelClasses as $class) {
                $channels[] = $app->make($class);
            }

            return new CampaignDeliveryService($channels);
        });
    }
}
