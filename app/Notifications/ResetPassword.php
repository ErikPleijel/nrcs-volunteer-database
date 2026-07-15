<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification; // Use an alias
use Illuminate\Notifications\Notification; // Import the base Notification class

class ResetPassword extends ResetPasswordNotification // Extend the original
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The user's email address.
     *
     * @var string
     */
    public $email; // Add this property

    /**
     * Create a new notification instance.
     *
     * @param string $token
     * @return void
     */
    public function __construct($token, $email) // Add $email to constructor
    {
        $this->token = $token;
        $this->email = $email; // Store email
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['sendgrid']; // Use your custom sendgrid channel
    }

    /**
     * Get the SendGrid representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toSendGrid($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->email, // Use the stored email
        ], false)); // Use false for relative URL if needed, or true for absolute

        // You will need to create this view file at resources/views/emails/password-reset.blade.php
        $htmlContent = view('emails.password-reset', [
            'user' => $notifiable,
            'url' => $url,
            'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ])->render();

        return [
            'to' => $this->email, // Use the stored email for 'to'
            'subject' => 'Reset Password Notification - Red Cross Volunteers',
            'html' => $htmlContent
        ];
    }
}
