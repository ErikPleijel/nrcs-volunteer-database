<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserFirstAidShieldBadge extends Component
{
    public ?string $type;
    public ?string $message;
    public ?string $icon;
    public ?string $styles;

    public function __construct(User $user)
    {
        if ($user->hasValidFirstAidTraining()) {
            $this->type    = 'valid';
            $this->message = 'First Aid';
            $this->icon    = 'fa-kit-medical'; // FA6 pro-looking, fallback below
            $this->styles  = 'bg-green-600 text-white';
        } elseif ($user->hasFirstAidTraining()) {
            $this->type    = 'expired';
            $this->message = 'Expired';
            $this->icon    = 'fa-kit-medical';
            $this->styles  = 'bg-yellow-500 text-white';
        } else {
            // ← IMPORTANT: Return empty badge (nothing rendered)
            $this->type    = null;
            $this->message = null;
            $this->icon    = null;
            $this->styles  = null;
        }
    }

    public function render()
    {
        return view('components.user-first-aid-shield');
    }
}
