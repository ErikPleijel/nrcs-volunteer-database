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
        $this->label  = 'Email not verified';
        $this->icon   = 'fa-envelope';
        $this->styles = 'bg-red-100 text-red-800';
        $this->title  = 'Email not verified: ' . $this->user->email;
    }

    /**
     * Only ever shown for the unverified-email case — hidden for a
     * verified email or no email at all.
     */
    public function shouldRender(): bool
    {
        return (bool) $this->user->email && ! $this->user->email_verified_at;
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
