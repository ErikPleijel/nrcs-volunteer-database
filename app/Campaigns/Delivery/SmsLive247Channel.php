<?php

namespace App\Campaigns\Delivery;

use App\Exceptions\SmsLive247Exception;
use App\Models\MessagingRecipient;
use App\Services\SmsLive247Service;

/**
 * Campaign SMS via SMSLive247 — a thin adapter over SmsLive247Service. Selected with
 * CAMPAIGN_SMS_CHANNEL=smslive247 (config/campaigns.php); LogSmsChannel is the default.
 *
 * A campaign run with --dry-run (DeliveryMessage meta dry_run) never calls the API, even
 * when SMSLIVE247_DRY_RUN=false.
 */
final class SmsLive247Channel implements DeliveryChannel
{
    public const PROVIDER = 'smslive247';

    public function __construct(private readonly SmsLive247Service $sms) {}

    public function name(): string
    {
        return 'sms';
    }

    public function supports(MessagingRecipient $recipient): bool
    {
        return ! empty($recipient->phone);
    }

    public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
    {
        try {
            $result = $this->sms->sendSms(
                (string) $recipient->phone,
                (string) ($message->smsBody ?? $message->body),
                dryRun: (bool) ($message->meta['dry_run'] ?? false),
            );
        } catch (SmsLive247Exception $e) {
            return DeliveryAttempt::failed(
                'sms',
                $e->getMessage(),
                $e->status !== null ? (string) $e->status : 'connection',
                provider: self::PROVIDER,
            );
        }

        return DeliveryAttempt::success(
            'sms',
            $result['message_id'],
            ['dry_run' => $result['dry_run']],
            provider: $result['dry_run'] ? self::PROVIDER.'-dryrun' : self::PROVIDER,
        );
    }
}
