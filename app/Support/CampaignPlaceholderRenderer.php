<?php

namespace App\Support;

use App\Models\User;

class CampaignPlaceholderRenderer
{
    /** Shown for {{user.time_since_last_first_aid}} when the user has no first-aid record. */
    private const NO_FIRST_AID_FALLBACK = 'no first-aid training on record';

    /**
     * Shown for {{user.membership_expiry}} when the user has never had a personal
     * membership (expired members get their real expiry date). Must read correctly in "your membership expires on ___" —
     * matches the existing fallback for the same field in
     * IdCardController::verifyId().
     */
    private const NO_MEMBERSHIP_FALLBACK = 'N/A';

    /** Shown for {{user.donations_summary}} when the user has no personal donations. */
    private const NO_DONATIONS_FALLBACK = 'no donations on file';

    /**
     * Shown for {{user.current_membership}} when the user has never had a
     * personal membership (expired members get their real fee name). Every
     * seeded template (CampaignPurposesSeeder) uses this token immediately
     * before the literal word "membership" — e.g. "your ___ membership ...
     * expired on" — so this must be an adjective-like word, not a noun phrase
     * like "N/A" (which reads as "your N/A membership...", broken) or an
     * empty string (leaves a double space).
     */
    private const NO_MEMBERSHIP_NAME_FALLBACK = 'Red Cross';

    /**
     * User columns every placeholder token reads. Shared by CampaignSendRunner
     * and the Step 5 / admin previews, which select narrow column lists — a
     * column missing here renders as its fallback (e.g. the preview once
     * showed "no first-aid training on record" for everyone).
     */
    public const USER_COLUMNS = [
        'id', 'title', 'first_name', 'middle_name', 'last_name', 'email',
        'telephone1', 'telephone2', 'lifecycle_status', 'last_first_aid_at',
        'branch_id', 'division_id', 'red_cross_unit_id',
    ];

    /** Relations the placeholder tokens read; eager-load these alongside USER_COLUMNS. */
    public const USER_RELATIONS = [
        'branch:id,name,code',
        'division:id,name',
        'redCrossUnit:id,name',
        'latestPersonalMembershipPayment.membershipFee',
    ];

    /**
     * Render placeholders like {{user.first_name}} (and @{{user.first_name}}) using a User.
     * Keep this allowlisted (don’t do arbitrary eval / dot-walking).
     */
    public static function render(string $template, User $user): string
    {
        // Lazy resolvers: each closure is invoked ONLY when its token is present in the template,
        // so expensive tokens (e.g. donations_summary, which re-queries) never run for a campaign
        // that doesn't use them. Output is identical to evaluating the full map eagerly.
        $resolvers = [
            'user.first_name' => fn () => $user->first_name ?? '',
            'user.last_name' => fn () => $user->last_name ?? '',
            // Title + first + last. Deliberately not User::full_name (no title; also used on
            // ID cards) nor full_middle_name (adds a bracketed middle name).
            'user.full_name' => fn () => collect([$user->title, $user->first_name, $user->last_name])
                ->map(fn ($part) => trim((string) $part))
                ->filter()
                ->join(' '),
            'user.email' => fn () => $user->email ?? '',
            'user.phone' => fn () => $user->telephone1 ?? ($user->telephone2 ?? ''),
            'user.branch' => fn () => $user->branch->name ?? '',
            'user.division' => fn () => $user->division->name ?? '',
            'user.red_cross_unit' => fn () => $user->redCrossUnit->name ?? '',
            'user.db_code_short' => fn () => $user->getUserIdReferenceShortAttribute() ?? '',
            // Retired token: removed from the wizard dropdown and the known-placeholder lists,
            // but still resolves (to the short code) so any stored campaign or purpose template
            // using it doesn't send literal "{{user.db_code_long}}". TEMPORARY — delete this
            // entry once the VPS shows no usage:
            //   SELECT id, status FROM messaging_campaigns WHERE CONCAT_WS(' ',subject,body,filter_json) LIKE '%db_code_long%';
            //   SELECT id, name FROM campaign_purposes WHERE CONCAT_WS(' ',default_subject,default_email_body,default_sms_body) LIKE '%db_code_long%';
            'user.db_code_long' => fn () => $user->getUserIdReferenceShortAttribute() ?? '',
            'user.lifecycle' => fn () => $user->getLifecycleStatusLabelAttribute() ?? '',
            'user.donations_summary' => fn () => $user->getDonationSummary() ?: self::NO_DONATIONS_FALLBACK,
            'user.current_membership' => fn () => $user->current_membership_name ?? self::NO_MEMBERSHIP_NAME_FALLBACK,
            'user.membership_expiry' => fn () => $user->latestPersonalMembershipPayment?->expiry_date?->format('d M Y') ?? self::NO_MEMBERSHIP_FALLBACK,
            'user.time_since_last_first_aid' => fn () => $user->timeSinceLastFirstAid() ?? self::NO_FIRST_AID_FALLBACK,
            'app.url' => fn () => config('app.campaign_display_url') ?: route('welcome'),
        ];

        // Resolve each token at most once per render, even if it appears multiple times,
        // so a repeated expensive token does not multiply its queries.
        $resolved = [];

        // Support both {{...}} and @{{...}} (your UI uses @{{ to avoid Blade)
        return preg_replace_callback('/@?\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($resolvers, &$resolved) {
            $key = $m[1];

            if (! array_key_exists($key, $resolvers)) {
                return $m[0]; // keep unknown placeholders as-is
            }

            if (! array_key_exists($key, $resolved)) {
                $resolved[$key] = (string) ($resolvers[$key])();
            }

            return $resolved[$key];
        }, $template) ?? $template;
    }
}
