<?php

namespace App\Channels;

use App\Services\SendGridService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SendGridChannel
{
    protected $sendGrid;

    public function __construct(SendGridService $sendGrid)
    {
        $this->sendGrid = $sendGrid;
    }

    public function send($notifiable, Notification $notification)
    {
        Log::info('SendGridChannel: Starting to send email', [
            'notifiable_email' => $notifiable->email,
            'notification_class' => get_class($notification)
        ]);

        try {
            $message = $notification->toSendGrid($notifiable);

            Log::info('SendGridChannel: Message prepared', [
                'to' => $message['to'],
                'subject' => $message['subject']
            ]);

            $result = $this->sendGrid->sendEmail(
                $message['to'],
                $message['subject'],
                $message['html']
            );

            Log::info('SendGridChannel: Email send result', ['result' => $result]);

            return $result;
        } catch (\Exception $e) {
            Log::error('SendGridChannel: Error sending email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
