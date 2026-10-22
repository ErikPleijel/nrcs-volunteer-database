<?php

namespace App\Campaigns\Delivery;

use App\Mail\CampaignEmail;
use App\Models\MessagingRecipient;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Campaign email through Laravel's mail system (MAIL_MAILER: smtp for SES SMTP, ses, ...).
 *
 * - A send that throws is a failed attempt, never a success, so the recipient is not
 *   marked sent and `email_fallback_sms` still tries SMS.
 * - In production a mailer that does not deliver (log, array) fails every attempt with
 *   `mailer_not_configured` instead of pretending: nothing is marked sent until a real
 *   mailer is set in .env.
 * - A campaign run with --dry-run (DeliveryMessage meta dry_run) never sends.
 */
final class MailEmailChannel implements DeliveryChannel
{
    public const PROVIDER = 'mail';

    /** Mail transports that accept a message without delivering it. */
    public const NON_DELIVERING_TRANSPORTS = ['log', 'array'];

    public function name(): string
    {
        return 'email';
    }

    public function supports(MessagingRecipient $recipient): bool
    {
        return ! empty($recipient->email);
    }

    public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
    {
        $mailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport", $mailer);

        // NDPA, as in LogSmsChannel: masked address, and no message body (it holds
        // personal details) — only its length.
        $logContext = [
            'campaign_id' => $recipient->messaging_campaign_id ?? null,
            'recipient_id' => $recipient->id,
            'user_id' => $recipient->user_id ?? null,
            'to' => PhoneNumber::maskEmail($recipient->email),
            'subject' => $message->subject,
            'body_length' => mb_strlen($message->emailBody ?? $message->body),
            'mailer' => $mailer,
        ];

        if ($message->meta['dry_run'] ?? false) {
            $id = 'mail-dryrun-'.Str::uuid()->toString();
            Log::channel('campaign_deliveries')->info('Campaign email (dry run, not sent)', $logContext + ['provider_message_id' => $id]);

            return DeliveryAttempt::success('email', $id, ['dry_run' => true], provider: self::PROVIDER.'-dryrun');
        }

        if (app()->isProduction() && in_array($transport, self::NON_DELIVERING_TRANSPORTS, true)) {
            $error = "No real mailer is configured (MAIL_MAILER={$mailer}); the email was not sent.";
            Log::channel('campaign_deliveries')->warning('Campaign email not sent: '.$error, $logContext);

            return DeliveryAttempt::failed('email', $error, 'mailer_not_configured', provider: self::PROVIDER);
        }

        try {
            $sent = Mail::to($recipient->email)->send(new CampaignEmail(
                subjectLine: (string) ($message->subject ?? ''),
                htmlBody: (string) ($message->emailBody ?? $message->body),
                fromEmail: (string) (($message->meta['from_email'] ?? null) ?: config('campaigns.delivery.mail_from_email')),
                fromName: (string) (($message->meta['from_name'] ?? null) ?: config('campaigns.default_from_name')),
                replyToEmail: config('campaigns.default_reply_to_email') ?: null,
            ));
        } catch (\Throwable $e) {
            Log::channel('campaign_deliveries')->error('Campaign email failed', $logContext + ['error' => $e->getMessage()]);

            return DeliveryAttempt::failed('email', $e->getMessage(), 'mail_error', provider: self::PROVIDER);
        }

        $id = $sent?->getMessageId() ?: 'mail-'.Str::uuid()->toString();
        Log::channel('campaign_deliveries')->info('Campaign email sent', $logContext + ['provider_message_id' => $id]);

        return DeliveryAttempt::success('email', $id, provider: self::PROVIDER.':'.$transport);
    }
}
