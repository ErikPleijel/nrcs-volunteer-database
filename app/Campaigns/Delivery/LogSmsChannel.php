<?php

namespace App\Campaigns\Delivery;

use App\Models\MessagingRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LogSmsChannel implements DeliveryChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public function supports(MessagingRecipient $recipient): bool
    {
        // Adjust to your schema:
        return !empty($recipient->phone);
    }

    public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
    {
        $fakeId = 'log-sms-' . Str::uuid()->toString();

        Log::channel('campaign_deliveries')->info('Campaign SMS delivery (log-only)', [
            'provider' => 'log-only',
            'channel' => 'sms',
            'provider_message_id' => $fakeId,

            'campaign_id' => $recipient->messaging_campaign_id ?? null,
            'recipient_id' => $recipient->id,
            'user_id' => $recipient->user_id ?? null,

            'to' => $recipient->phone,
            'body' => $message->smsBody ?? $message->body,
            'meta' => $message->meta,
        ]);

        return DeliveryAttempt::success('sms', $fakeId);
    }
}
