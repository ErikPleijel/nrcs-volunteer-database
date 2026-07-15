<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserDigitalStatusBadge extends Component
{
    public User $user;

    public string $label;
    public string $icon;
    public string $styles;
    public string $title;

    protected int $thresholdMonths = 6;

    public function __construct(User $user)
    {
        $this->user = $user;

        $this->resolveStatus();
    }

    protected function resolveStatus(): void
    {
        $neverLoggedIn = is_null($this->user->last_login_at);

        if ($neverLoggedIn) {
            $this->label  = 'Never logged in';
            $this->icon   = 'fa-user-slash';
            $this->styles = 'bg-gray-100 text-gray-800';
            $this->title  = 'This user has never logged into the system.';
            return;
        }

        // Assumes you have isDigitallyDormant(int $months) on User
        $isDormant = $this->user->isDigitallyDormant($this->thresholdMonths);
        $lastLogin = $this->user->last_login_at?->format('Y-m-d');

        if (! $isDormant) {
            $this->label  = 'Digitally active';
            $this->icon   = 'fa-wifi';
            $this->styles = 'bg-green-100 text-green-800';
            $this->title  = 'Last login: ' . $lastLogin;
            return;
        }

        $this->label  = 'Digitally dormant';
        $this->icon   = 'fa-plug';
        $this->styles = 'bg-amber-100 text-amber-800';
        $this->title  = 'No login in the last ' . $this->thresholdMonths . ' months. Last login: ' . $lastLogin;
    }

    public function render()
    {
        return view('components.user-digital-status-badge', [
            'label'  => $this->label,
            'icon'   => $this->icon,
            'styles' => $this->styles,
            'title'  => $this->title,
        ]);
    }
}

