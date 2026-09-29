<?php

namespace App\Campaigns\Recipients;

use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\Organisation;
use App\Models\User;
use App\Services\UserFilterService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Materialises a campaign's messaging_recipients rows from its filter. The one builder
 * behind both the admin "Build" button and `campaigns:build-recipients`.
 *
 * Rebuilds are safe on a campaign that has already sent: only new recipients are added,
 * and only rows still in REBUILDABLE_STATUSES are updated. Anything queued, sent,
 * delivered, failed, bounced, undeliverable or expired is never touched, so it can never
 * go back to pending and be sent again.
 *
 * Opt-outs are applied here (email and SMS). SMS numbers are converted to E.164, and when
 * several recipients would be texted at the same number only SmsNumberPlanner's winner
 * gets the SMS.
 */
final class CampaignRecipientBuilder
{
    /** The only statuses a rebuild may change (or --fresh may delete). */
    public const REBUILDABLE_STATUSES = ['pending', 'skipped_invalid_number', 'skipped_shared_number'];

    public const SHARED_NUMBER_ERROR = 'Shares an SMS number with another recipient of this campaign, who gets the SMS.';

    public const SHARED_NUMBER_EMAIL_ONLY = 'Shares an SMS number with another recipient of this campaign; this one gets email only.';

    public function __construct(private readonly UserFilterService $userFilterService) {}

    /**
     * The campaign's audience, with the creator's scope (never super-admins).
     */
    public function audience(MessagingCampaign $campaign): Builder
    {
        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        return $this->userFilterService->apply(
            User::query()->where('is_super_admin', false),
            $filters,
            $campaign->scope_level,
            $campaign->scope_id
        );
    }

    /**
     * @return array{created:int, updated:int, kept:int, not_contactable:int, opted_out:int,
     *               invalid_numbers:int, shared_numbers:int, org_emails:int, total:int}
     */
    public function build(MessagingCampaign $campaign, bool $fresh = false, bool $onlyContactable = false, int $chunk = 500): array
    {
        $counts = [
            'created' => 0, 'updated' => 0, 'kept' => 0, 'not_contactable' => 0, 'opted_out' => 0,
            'invalid_numbers' => 0, 'shared_numbers' => 0, 'org_emails' => 0, 'total' => 0,
        ];

        if ($fresh) {
            MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->whereIn('status', self::REBUILDABLE_STATUSES)
                ->delete();
        }

        $channel = $campaign->channel;
        $audience = $this->audience($campaign);
        $smsWinners = SmsNumberPlanner::winners($audience, $channel);

        (clone $audience)
            ->select([...SmsNumberPlanner::USER_COLUMNS, 'first_name', 'last_name'])
            ->orderBy('id')
            ->chunkById($chunk, function ($users) use ($campaign, $channel, $onlyContactable, $smsWinners, &$counts) {
                $existingRows = MessagingRecipient::query()
                    ->where('messaging_campaign_id', $campaign->id)
                    ->where('recipient_type', User::class)
                    ->whereIn('recipient_id', $users->pluck('id'))
                    ->get()
                    ->keyBy('recipient_id');

                foreach ($users as $user) {
                    $contact = RecipientContact::for($user, $channel);

                    if ($contact->optedOutOfAll) {
                        $counts['opted_out']++;
                        continue;
                    }

                    $email = $contact->effectiveEmail;
                    $phone = $contact->effectivePhone;
                    $status = 'pending';
                    $lastError = null;

                    if ($contact->invalidNumber) {
                        $status = 'skipped_invalid_number';
                        $lastError = RecipientPhone::INVALID_NUMBER_ERROR;
                        $counts['invalid_numbers']++;
                    } elseif ($contact->getsSms && ($smsWinners[$phone] ?? null) !== (int) $user->id) {
                        $counts['shared_numbers']++;
                        if ($channel === 'both' && $email) {
                            $phone = null; // email only; the winner gets the SMS
                            $lastError = self::SHARED_NUMBER_EMAIL_ONLY;
                        } else {
                            $status = 'skipped_shared_number';
                            $lastError = self::SHARED_NUMBER_ERROR;
                        }
                    }

                    // Skipped rows are kept visible; only reachable pending rows are required.
                    if ($onlyContactable && $status === 'pending' && ! $this->contactable($channel, $email, $phone)) {
                        $counts['not_contactable']++;
                        continue;
                    }

                    $values = [
                        'email' => $email,
                        'phone' => $phone,
                        'payload_json' => [
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                        ],
                        'status' => $status,
                        'last_error' => $lastError,
                    ];

                    $existing = $existingRows->get($user->id);

                    if (! $existing) {
                        MessagingRecipient::create([
                            'messaging_campaign_id' => $campaign->id,
                            'recipient_type' => User::class,
                            'recipient_id' => $user->id,
                            ...$values,
                        ]);
                        $counts['created']++;
                    } elseif (in_array($existing->status, self::REBUILDABLE_STATUSES, true)) {
                        $existing->update($values);
                        $counts['updated']++;
                    } else {
                        $counts['kept']++; // already attempted: never reset
                    }
                }
            });

        $counts['org_emails'] = $this->addOrganisationRepresentatives($campaign);

        $campaign->refreshRecipientStats();
        $counts['total'] = (int) $campaign->stats_total;

        return $counts;
    }

    public static function summary(array $counts): string
    {
        return "Recipients built. Total: {$counts['total']}. Created: {$counts['created']}. "
            ."Updated: {$counts['updated']}. Already attempted (left unchanged): {$counts['kept']}. "
            ."Skipped (not contactable): {$counts['not_contactable']}. Skipped (opted out): {$counts['opted_out']}. "
            ."No valid mobile number: {$counts['invalid_numbers']}. Shared SMS number (SMS to one recipient only): {$counts['shared_numbers']}. "
            ."Org emails added: {$counts['org_emails']}.";
    }

    private function contactable(string $channel, ?string $email, ?string $phone): bool
    {
        return match ($channel) {
            'email' => (bool) $email,
            'sms' => (bool) $phone,
            default => $email || $phone,
        };
    }

    /**
     * Organisation contact emails, when the campaign filter asks for them. Existing rows are
     * left alone.
     */
    private function addOrganisationRepresentatives(MessagingCampaign $campaign): int
    {
        if (! data_get($campaign->filter_json, 'org_representatives')) {
            return 0;
        }

        $added = 0;

        Organisation::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('users')
            ->when($campaign->scope_level === 'branch' && $campaign->scope_id,
                fn ($q) => $q->where('branch_id', $campaign->scope_id))
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->each(function (Organisation $org) use ($campaign, &$added) {
                $row = MessagingRecipient::firstOrCreate(
                    [
                        'messaging_campaign_id' => $campaign->id,
                        'recipient_type' => Organisation::class,
                        'recipient_id' => $org->id,
                    ],
                    [
                        'email' => trim((string) $org->email),
                        'phone' => null,
                        'payload_json' => ['full_name' => $org->name, 'first_name' => $org->name, 'last_name' => ''],
                        'status' => 'pending',
                    ]
                );

                $added += (int) $row->wasRecentlyCreated;
            });

        return $added;
    }
}
