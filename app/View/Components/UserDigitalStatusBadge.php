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

    public function __construct(User $user)
    {
        $this->user = $user;

        $this->resolveStatus();
    }

    protected function resolveStatus(): void
    {
        $recency = $this->user->formatActivityRecency($this->user->last_login_at, 'Logged in');
        $lastLogin = $this->user->last_login_at?->format('Y-m-d');

        if (! $recency['hasData']) {
            $this->label  = 'Never logged in';
            $this->icon   = 'fa-user-slash';
            $this->styles = 'bg-gray-100 text-gray-800';
            $this->title  = 'This user has never logged into the system.';
            return;
        }

        if ($recency['isRecent']) {
            $this->label  = $recency['label'];
            $this->icon   = 'fa-wifi';
            $this->styles = 'bg-green-100 text-green-800';
            $this->title  = 'Last login: ' . $lastLogin;
            return;
        }

        $this->label  = $recency['label'];
        $this->icon   = 'fa-plug';
        $this->styles = 'bg-gray-800 text-white';
        $this->title  = 'Last login: ' . $lastLogin;
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

