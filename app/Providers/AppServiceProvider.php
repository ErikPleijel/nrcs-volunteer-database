<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Reports\ActivityStatsService;
use App\Services\Reports\MembershipStatsService;
use App\Services\Reports\RedCrossUnitStatsService;
use App\Services\Reports\TaskForceStatsService;
use App\Services\Reports\TrainingStatsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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

        // Registration abuse protection — 5 attempts per minute per IP,
        // mirroring the throttle:5,1 already applied to the login route.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Registration-form dropdown lookups, 20/min per IP. Named rather than
        // throttle:20,1 because every unnamed throttle:N,1 shares one per-IP
        // counter — so dropdown browsing was using up the login route's budget.
        RateLimiter::for('register-lookups', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Per-IP cap on the phone-login disambiguation steps. A full pass is
        // at most 5 POSTs (DB number, name, birth year, pick, password); wrong
        // answers are separately limited per phone number by LoginThrottle.
        RateLimiter::for('login-phone-flow', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

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
