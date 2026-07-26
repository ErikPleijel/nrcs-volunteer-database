<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserEmailVerificationStatusBadge extends Component
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
        if (! $this->user->email) {
            $this->label  = 'No email';
            $this->icon   = 'fa-envelope-open-text';
            $this->styles = 'bg-gray-100 text-gray-600';
            $this->title  = 'No email address on file.';
            return;
        }

        if ($this->user->email_verified_at) {
            $this->label  = 'Email verified';
            $this->icon   = 'fa-envelope-circle-check';
            $this->styles = 'bg-green-100 text-green-800';
            $this->title  = 'Email verified: ' . $this->user->email;
            return;
        }

        $this->label  = 'Email not verified';
        $this->icon   = 'fa-envelope';
        $this->styles = 'bg-yellow-100 text-yellow-800';
        $this->title  = 'Email not verified: ' . $this->user->email;
    }

    public function render()
    {
        return view('components.user-email-verification-status-badge', [
            'label'  => $this->label,
            'icon'   => $this->icon,
            'styles' => $this->styles,
            'title'  => $this->title,
        ]);
    }
}
