<?php

namespace App\Services;

use App\Models\User;
use App\Support\Filters\ParsedSearch;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The line shown on the users list when a search finds nobody, saying why
 * where that is safe to tell:
 *
 * - DB code outside a branch/division user's area → its branch and division
 *   only, never a name. Limited to OUT_OF_AREA_PER_HOUR per user, so the
 *   list can't be used to map where everyone is; past that, plain "no
 *   results".
 * - DB code in the viewer's area but filtered out → hidden by filters.
 * - Phone / NIN for branch and division users → the same neutral line
 *   whether or not the person exists anywhere, so it confirms nothing.
 * - A number that is no DB code, phone or NIN → says so.
 */
class UserSearchNotice
{
    public const OUT_OF_AREA_PER_HOUR = 30;

    public static function rateLimitKey(User $viewer): string
    {
        return 'users-search-out-of-area:'.$viewer->id;
    }

    public function for(User $viewer, string $accessLevel, ?int $scopedId, string $search): ?string
    {
        $parsed = ParsedSearch::parse($search);
        $areaLimited = in_array($accessLevel, ['branch', 'division'], true);

        return match ($parsed->type) {
            ParsedSearch::DB_CODE => $this->forDbCode($viewer, $accessLevel, $scopedId, (int) $parsed->value),
            ParsedSearch::PHONE, ParsedSearch::PHONE_OR_NIN => $areaLimited
                ? 'No match in your area. The person may be registered elsewhere — contact the national office.'
                : null,
            ParsedSearch::BAD_NUMBER => 'This is not a DB code, phone number or NIN. '
                .'A DB code has up to 9 digits; a phone number 10 or 11 digits (or +234…); a NIN 11 digits.',
            default => null,
        };
    }

    private function forDbCode(User $viewer, string $accessLevel, ?int $scopedId, int $id): ?string
    {
        // Only people the users list can ever show (same base as UserController::index()).
        $person = User::query()
            ->whereKey($id)
            ->where('is_super_admin', false)
            ->whereNull('organisation_id')
            ->with(['branch:id,name', 'division:id,name'])
            ->first(['id', 'branch_id', 'division_id']);

        if ($person === null) {
            return null;
        }

        $inArea = match ($accessLevel) {
            'national' => true,
            'branch' => $scopedId !== null && (int) $person->branch_id === $scopedId,
            'division' => $scopedId !== null && (int) $person->division_id === $scopedId,
            default => null,
        };

        if ($inArea === null) {
            return null;
        }

        if ($inArea) {
            return "DB {$id} exists but is hidden by your filters. Try Lifecycle → All, or clear the filters.";
        }

        $key = self::rateLimitKey($viewer);
        if (RateLimiter::tooManyAttempts($key, self::OUT_OF_AREA_PER_HOUR)) {
            return null;
        }
        RateLimiter::hit($key, 3600);

        $place = array_filter([
            $person->branch ? $person->branch->name.' Branch' : null,
            $person->division ? $person->division->name.' Division' : null,
        ]);

        return $place
            ? "DB {$id} is in ".implode(', ', $place).'.'
            : "DB {$id} is outside your area.";
    }
}
