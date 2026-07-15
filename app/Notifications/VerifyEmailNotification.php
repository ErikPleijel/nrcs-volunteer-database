<?php

namespace App\Notifications;

use App\Services\SendGridService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function via($notifiable)
    {
        return ['sendgrid'];
    }

    public function toSendGrid($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $htmlContent = view('emails.verify-email', [
            'user' => $notifiable,
            'url' => $verificationUrl
        ])->render();

        return [
            'to' => $notifiable->email,
            'subject' => 'Verify Your Email Address - Red Cross Volunteers',
            'html' => $htmlContent
        ];
    }
}
