<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserLifecycleStatusBadge extends Component
{
    public User $user;

    public string $label;
    public string $styles;
    public string $icon;

    public function __construct(User $user)
    {
        $this->user = $user;

        $this->label  = $user->lifecycle_status_label;  // uses your accessor
        $this->icon   = $this->resolveIcon($user->lifecycle_status);
        $this->styles = $this->resolveStyles($user->lifecycle_status);
    }

    private function resolveIcon(string $status): string
    {
        return match ($status) {
            'pending_engagement' => 'fa-hourglass-half',
            'active'             => 'fa-check-circle',
            'dormant'            => 'fa-bed',
            'archived'           => 'fa-archive',
            default              => 'fa-question-circle',
        };
    }

    private function resolveStyles(string $status): string
    {
        return match ($status) {
            'pending_engagement' => 'bg-yellow-100 text-yellow-800',
            'active'             => 'bg-green-100 text-green-800',
            'dormant'            => 'bg-gray-200 text-gray-800',
            'archived'           => 'bg-red-100 text-red-800',
            default              => 'bg-gray-100 text-gray-800',
        };
    }

    public function render()
    {
        return view('components.user-lifecycle-status-badge');
    }
}
