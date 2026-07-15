<?php

namespace App\Campaigns\Delivery;

use App\Models\MessagingRecipient;
use Illuminate\Support\Facades\Log;

final class CampaignDeliveryService
{
    /** @var DeliveryChannel[] */
    private array $channels;

    /**
     * @param DeliveryChannel[] $channels
     */
    public function __construct(array $channels = [])
    {
        $this->channels = $channels;
    }

    /**
     * @param  string  $channelWanted  "email"|"sms"|"both"|"email_fallback_sms"
     */
    public function deliver(string $channelWanted, MessagingRecipient $recipient, DeliveryMessage $message): DeliveryOutcome
    {
        Log::channel('campaign_deliveries')->info(
            'CampaignDeliveryService channels',
            [
                'count'  => count($this->channels),
                'wanted' => $channelWanted,
                'recipient_id' => $recipient->id,
            ]
        );

        // ✅ Option B: real fallback semantics
        // Try EMAIL first. Only attempt SMS if email is unsupported or email delivery failed.
        if ($channelWanted === 'email_fallback_sms') {
            return $this->deliverEmailFallbackSms($recipient, $message);
        }

        // Normal "email" | "sms" | "both"
        $attempts = [];

        foreach ($this->channels as $channel) {
            if (!$this->channelIsWanted($channel->name(), $channelWanted)) {
                continue;
            }

            if (!$channel->supports($recipient)) {
                $attempts[] = DeliveryAttempt::failed(
                    $channel->name(),
                    'Recipient not supported for this channel (missing destination/opt-out/etc).',
                    'unsupported'
                );
                continue;
            }

            $attempts[] = $channel->deliver($recipient, $message);
        }

        return new DeliveryOutcome($attempts);
    }

    private function deliverEmailFallbackSms(
        MessagingRecipient $recipient,
        DeliveryMessage $message
    ): DeliveryOutcome
    {
        $attempts = [];

        $email = $this->findChannel('email');
        $sms   = $this->findChannel('sms');

        // ---- 1) Try email first (if channel exists) ----
        if ($email === null) {
            Log::channel('campaign_deliveries')->info(
                'Fallback to SMS (email channel missing)',
                [
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email ?? null,
                    'phone' => $recipient->phone ?? null,
                ]
            );

            $attempts[] = DeliveryAttempt::failed(
                'email',
                'Email channel is not configured.',
                'channel_missing'
            );
        } elseif (!$email->supports($recipient)) {
            Log::channel('campaign_deliveries')->info(
                'Fallback to SMS (email unsupported)',
                [
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email ?? null,
                    'phone' => $recipient->phone ?? null,
                ]
            );

            $attempts[] = DeliveryAttempt::failed(
                'email',
                'Recipient not supported for email (missing destination/opt-out/etc).',
                'unsupported'
            );
        } else {
            $emailAttempt = $email->deliver($recipient, $message);
            $attempts[] = $emailAttempt;

            // If email succeeds, stop here (no SMS fallback).
            if ($emailAttempt->ok) {
                return new DeliveryOutcome($attempts);
            }

            Log::channel('campaign_deliveries')->info(
                'Fallback to SMS (email delivery failed)',
                [
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email ?? null,
                    'phone' => $recipient->phone ?? null,
                    'error' => $emailAttempt->errorMessage ?? null,
                ]
            );
        }

        // ---- 2) Fallback to SMS (only if email failed/unsupported/missing channel) ----
        if ($sms === null) {
            $attempts[] = DeliveryAttempt::failed(
                'sms',
                'SMS channel is not configured.',
                'channel_missing'
            );
            return new DeliveryOutcome($attempts);
        }

        if (!$sms->supports($recipient)) {
            $attempts[] = DeliveryAttempt::failed(
                'sms',
                'Recipient not supported for sms (missing destination/opt-out/etc).',
                'unsupported'
            );
            return new DeliveryOutcome($attempts);
        }

        $attempts[] = $sms->deliver($recipient, $message);
        return new DeliveryOutcome($attempts);
    }


    private function findChannel(string $name): ?DeliveryChannel
    {
        foreach ($this->channels as $c) {
            if ($c->name() === $name) {
                return $c;
            }
        }
        return null;
    }

    private function channelIsWanted(string $channelName, string $wanted): bool
    {
        if ($wanted === 'both') {
            return in_array($channelName, ['email', 'sms'], true);
        }

        // Note: do NOT treat email_fallback_sms as "both" here; it is handled specially above.
        return $channelName === $wanted;
    }
}
