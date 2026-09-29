<?php

namespace App\Campaigns\Recipients;

use Illuminate\Database\Eloquent\Builder;

/**
 * Picks, for every E.164 number the campaign would text, the ONE recipient who gets the
 * SMS. Priority: active lifecycle status → number came from telephone1 (not telephone2) →
 * lowest user id (oldest account). Everyone else at that number is a shared-number loser.
 *
 * Needs the whole audience at once, so it runs as a separate pass before recipient rows
 * are written (the send runner only ever sees 50 rows at a time).
 */
final class SmsNumberPlanner
{
    /** Columns RecipientContact and the ranking read. */
    public const USER_COLUMNS = [
        'id', 'email', 'telephone1', 'telephone2', 'email_opt_out', 'sms_opt_out', 'lifecycle_status',
    ];

    /**
     * @param  Builder  $audience  the campaign's filtered user query
     * @return array<string, int> E.164 number => winning user id
     */
    public static function winners(Builder $audience, string $channel, int $chunk = 1000): array
    {
        /** @var array<string, array{0:int,1:int,2:int}> $best number => rank tuple (lower wins) */
        $best = [];

        (clone $audience)
            ->select(self::USER_COLUMNS)
            ->orderBy('id')
            ->chunkById($chunk, function ($users) use ($channel, &$best) {
                foreach ($users as $user) {
                    $contact = RecipientContact::for($user, $channel);
                    if (! $contact->getsSms) {
                        continue;
                    }

                    $rank = [
                        $user->lifecycle_status === 'active' ? 0 : 1,
                        $contact->phone->source === 'telephone1' ? 0 : 1,
                        (int) $user->id,
                    ];

                    $number = $contact->effectivePhone;
                    if (! isset($best[$number]) || $rank < $best[$number]) {
                        $best[$number] = $rank;
                    }
                }
            });

        return array_map(fn (array $rank) => $rank[2], $best);
    }
}
