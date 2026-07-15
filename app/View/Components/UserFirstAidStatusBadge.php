<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;

class UserFirstAidStatusBadge extends Component
{
    public string $type;
    public string $message;
    public string $icon;
    public string $styles;
    public ?string $subtext = null;

    public function __construct(User $user)
    {
        if ($user->hasValidFirstAidTraining()) {
            $this->type    = 'valid';
            $this->message = 'First Aid valid';
            $this->icon    = 'fa-kit-medical';
            $this->styles  = 'bg-green-100 text-green-800 border border-green-300';
        } elseif ($user->hasFirstAidTraining()) {
            $this->type    = 'expired';
            $this->message = 'First Aid: exp.';
            $this->icon    = 'fa-kit-medical';
            $this->styles  = 'bg-yellow-100 text-yellow-800';
        } else {
            $this->type    = 'none';
            $this->message = 'No First Aid';
            $this->icon    = 'fa-kit-medical';
            $this->styles  = 'bg-gray-100 text-gray-700 border border-gray-100';
        }

        // Second line: how long since the last first-aid training.
        // Uses the denormalised column; only shown when a FA date exists.
        if ($user->last_first_aid_at) {
            $this->subtext = 'Last FA ' . $this->humanFaAge($user->last_first_aid_at);
        }
    }

    private function humanFaAge($date): string
    {
        $diff   = Carbon::parse($date)->diff(now());
        $years  = $diff->y;
        $months = $diff->m;

        if ($years === 0 && $months === 0) {
            return '<1m';
        }

        $parts = [];
        if ($years > 0)  { $parts[] = $years . 'y'; }
        if ($months > 0) { $parts[] = $months . 'm'; }

        return implode(' ', $parts);
    }

    public function render()
    {
        return view('components.user-first-aid-status-badge');
    }
}
