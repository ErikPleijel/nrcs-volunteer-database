<?php

namespace App\Support;

use App\Models\User;

class CampaignPlaceholderRenderer
{
    /** Shown for {{user.time_since_last_first_aid}} when the user has no first-aid record. */
    private const NO_FIRST_AID_FALLBACK = 'no first-aid training on record';

    /**
     * Shown for {{user.membership_expiry}} when the user has no personal
     * membership. Must read correctly in "your membership expires on ___" —
     * matches the existing fallback for the same field in
     * IdCardController::verifyId().
     */
    private const NO_MEMBERSHIP_FALLBACK = 'N/A';

    /** Shown for {{user.donations_summary}} when the user has no personal donations. */
    private const NO_DONATIONS_FALLBACK = 'no donations on file';

    /**
     * Shown for {{user.current_membership}} when the user has no personal
     * membership. Every seeded template (CampaignPurposesSeeder) uses this
     * token immediately before the literal word "membership" — e.g. "your
     * ___ membership ... is due to expire soon" — so this must be an
     * adjective-like word, not a noun phrase like "N/A" (which reads as
     * "your N/A membership...", broken) or an empty string (leaves a
     * double space).
     */
    private const NO_MEMBERSHIP_NAME_FALLBACK = 'current';

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
            'user.full_name' => fn () => $user->full_name ?? '',
            'user.email' => fn () => $user->email ?? '',
            'user.phone' => fn () => $user->telephone1 ?? ($user->telephone2 ?? ''),
            'user.branch' => fn () => $user->branch->name ?? '',
            'user.division' => fn () => $user->division->name ?? '',
            'user.red_cross_unit' => fn () => $user->redCrossUnit->name ?? '',
            'user.db_code_short' => fn () => $user->getUserIdReferenceShortAttribute() ?? '',
            'user.db_code_long' => fn () => $user->getUserIdReferenceAttribute() ?? '',
            'user.lifecycle' => fn () => $user->getLifecycleStatusLabelAttribute() ?? '',
            'user.donations_summary' => fn () => $user->getDonationSummary() ?: self::NO_DONATIONS_FALLBACK,
            'user.current_membership' => fn () => $user->current_membership_name ?? self::NO_MEMBERSHIP_NAME_FALLBACK,
            'user.membership_expiry' => fn () => optional($user->currentMembershipPayment()->personal()->first()?->expiry_date)->format('d M Y') ?? self::NO_MEMBERSHIP_FALLBACK,
            'user.time_since_last_first_aid' => fn () => $user->timeSinceLastFirstAid() ?? self::NO_FIRST_AID_FALLBACK,
            'app.url' => fn () => route('welcome'),
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
