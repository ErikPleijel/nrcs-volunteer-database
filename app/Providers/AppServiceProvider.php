<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Reports\ActivityStatsService;
use App\Services\Reports\MembershipStatsService;
use App\Services\Reports\RedCrossUnitStatsService;
use App\Services\Reports\TaskForceStatsService;
use App\Services\Reports\TrainingStatsService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Import the User model
// Import the UserObserver

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RedCrossUnitStatsService::class);
        $this->app->singleton(MembershipStatsService::class);
        $this->app->singleton(ActivityStatsService::class);
        $this->app->singleton(TaskForceStatsService::class);
        $this->app->singleton(TrainingStatsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Register SendGrid notification channel
        $this->app->make('Illuminate\Notifications\ChannelManager')
            ->extend('sendgrid', function () {
                return new \App\Channels\SendGridChannel(
                    new \App\Services\SendGridService()
                );
            });


        // Register the User Observer for Super Admin role assignment
        User::observe(UserObserver::class);

        \Illuminate\Support\Facades\View::composer(
            [
                'components.navigation',
                'components.mobile-navigation',
                'components.layouts.admin',
            ],
            \App\View\Composers\NavigationComposer::class
        );

        if (app()->environment('local')) {
            DB::listen(function ($query) {
                // $query->time is in milliseconds
                if ($query->time > 100) { // log queries slower than 100 ms
                    Log::info('SLOW QUERY', [
                        'sql'      => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms'  => $query->time,
                    ]);
                }
            });
        }
    }
}
