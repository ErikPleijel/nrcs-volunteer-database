<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword; // Original Laravel ResetPassword notification
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Notifications\ResetPassword as CustomResetPassword; // Your custom notification
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Notifications\VerifyEmailNotification; // Your custom VerifyEmailNotification
use Illuminate\Notifications\Notification; // Import for the sendUsing callback signature
use App\Models\User;
use App\Policies\UserPolicy;



class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Override Laravel's default ResetPassword notification to use your custom one
        ResetPassword::sendUsing(function ($notifiable, string $token, ?string $email = null) {
            // Dispatch your custom SendGrid notification directly
            // Pass the token and the notifiable's email (or the provided email if any)
            return $notifiable->notify(new CustomResetPassword($token, $notifiable->getEmailForPasswordReset()));
        });

        // Ensure your VerifyEmailNotification is consistently used
        VerifyEmail::sendUsing(function ($notifiable) {
            return $notifiable->notify(new VerifyEmailNotification());
        });

    }
}
