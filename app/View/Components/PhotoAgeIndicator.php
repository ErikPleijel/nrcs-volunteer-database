<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

/**
 * The "how old is this photo" badge shown below a profile/unit photo.
 * Reads User::image_age_status (fresh/aging/stale) as the single source of
 * truth for the color/icon thresholds — see User::getImageAgeStatusAttribute().
 *
 * Renders nothing when the user has no photo at all. When a photo exists but
 * has no recorded upload date (legacy data), shows a small gray "no date"
 * fallback instead of a colored badge.
 */
class PhotoAgeIndicator extends Component
{
    /** 'fresh' | 'aging' | 'stale' | 'no_date' | null (null = render nothing) */
    public ?string $state;

    public ?string $icon;

    public ?string $colorClass;

    public ?string $sizeClass;

    public ?string $text;

    public ?string $label;

    public bool $compact;

    /** Full sentence ("Photo uploaded 8 years ago") instead of "8 yrs" — used on My Profile. */
    public bool $verbose;

    /** Shown under the text when verbose and the photo is stale. */
    public ?string $hint = null;

    public function __construct(User $user, ?string $label = null, bool $compact = false, bool $verbose = false)
    {
        $this->label = $label;
        $this->compact = $compact;
        $this->verbose = $verbose;

        if (! $user->picture) {
            $this->state = null;
            $this->icon = $this->colorClass = $this->sizeClass = $this->text = null;

            return;
        }

        $status = $user->image_age_status;

        if (is_null($status)) {
            $this->state = 'no_date';
            $this->icon = $this->colorClass = $this->sizeClass = $this->text = null;

            return;
        }

        $this->state = $status;

        [$this->icon, $this->colorClass, $this->sizeClass] = match ($status) {
            'fresh' => ['fa-circle-check', 'text-green-600', 'text-[10px]'],
            'aging' => ['fa-circle-exclamation', 'text-yellow-600', 'text-[10px]'],
            'stale' => ['fa-triangle-exclamation', 'text-red-600', 'text-sm'],
        };

        $ageInYears = $user->image_age_in_years;

        if ($verbose) {
            $years = (int) $ageInYears;
            $this->text = $ageInYears < 1 ? 'Photo uploaded less than a year ago'
                : 'Photo uploaded '.$years.' '.($years === 1 ? 'year' : 'years').' ago';
            $this->sizeClass = $status === 'stale' ? 'text-sm' : 'text-xs';
            $this->hint = $status === 'stale' ? 'Please upload a new photo.' : null;

            return;
        }

        $this->text = $ageInYears < 1 ? '< 1 yr' : (int) $ageInYears.' yrs';
    }

    public function render()
    {
        return view('components.photo-age-indicator');
    }
}
