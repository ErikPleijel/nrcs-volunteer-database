<?php

namespace App\Campaigns\Delivery;

use App\Models\MessagingRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LogEmailChannel implements DeliveryChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function supports(MessagingRecipient $recipient): bool
    {
        // Adjust to your schema:
        return !empty($recipient->email);
    }

    public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
    {
        // Fake a provider id so the rest of your pipeline can store something consistent.
        $fakeId = 'log-email-' . Str::uuid()->toString();

        Log::channel('campaign_deliveries')->info('Campaign email delivery (log-only)', [
            'provider' => 'log-only',
            'channel' => 'email',
            'provider_message_id' => $fakeId,

            // campaign context (adjust field names):
            'campaign_id' => $recipient->messaging_campaign_id ?? null,
            'recipient_id' => $recipient->id,
            'user_id' => $recipient->user_id ?? null,

            // destination:
            'to' => $recipient->email,

            // message:
            'subject' => $message->subject,
            'body' => $message->emailBody ?? $message->body,

            // any extra:
            'meta' => $message->meta,
        ]);

        return DeliveryAttempt::success('email', $fakeId);
    }
}
