<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserTrainingStatusBadge extends Component
{
    public string $type;
    public string $message;
    public string $icon;
    public string $styles;

    /**
     * Create a new component instance.
     *
     * @param \App\Models\User $user
     */
    public function __construct(User $user)
    {

        $totalTrainings = $user-> getActiveTrainingsCountAttribute();

        if ($totalTrainings > 0) {
            $this->type    = 'valid';
            $this->message = $totalTrainings . ' Training' . ($totalTrainings > 1 ? 's' : '');
            $this->icon    = 'fa-graduation-cap';
            $this->styles  = 'bg-green-100 text-green-800';
        } else {
            // No trainings at all
            $this->type    = 'none';
            $this->message = 'No Trainings';
            $this->icon    = 'fa-graduation-cap';
            $this->styles  = 'bg-gray-100 text-gray-800';
        }
    }

    public function render()
    {
        return view('components.user-training-status-badge');
    }
}
