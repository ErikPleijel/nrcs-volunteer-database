<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Tells a self-managed person that staff changed their login details on
 * users/edit (email, phone numbers, password). Sent to $to — the old email
 * address when that is what changed, so a hijacked address is noticed.
 */
class AccountDetailsChanged extends Notification
{
    /**
     * @param  string[]  $changes  One line per change, e.g. "Email address changed to x@y.com".
     */
    public function __construct(
        public readonly string $to,
        public readonly array $changes,
    ) {}

    public function via($notifiable)
    {
        return ['sendgrid'];
    }

    public function toSendGrid($notifiable)
    {
        return [
            'to' => $this->to,
            'subject' => 'Your account details were changed - Red Cross Volunteers',
            'html' => view('emails.account-details-changed', [
                'user' => $notifiable,
                'changes' => $this->changes,
            ])->render(),
        ];
    }
}
