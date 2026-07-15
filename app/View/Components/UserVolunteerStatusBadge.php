<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserVolunteerStatusBadge extends Component
{
    public string $type;
    public string $line1;
    public string $line2;
    public string $icon;
    public string $styles;

    public function __construct(User $user)
    {
        if ($user->isVolunteer()) {
            // ✅ Active volunteer
            $this->type   = 'active';
            $this->line1  = 'Volunteer';
            $this->line2  = $user->total_volunteering_hours . ' hours';
            $this->icon   = 'fa-hands-helping';
            $this->styles = 'bg-green-100 text-green-800';

        } elseif ($user->wantsVolunteer()) {
            // 🙋 Interested
            $this->type   = 'interested';
            $this->line1  = 'Volunteering';
            $this->line2  = 'Interested';
            $this->icon   = 'fa-hand-sparkles';
            $this->styles = 'bg-yellow-100 text-yellow-800';

        } else {
            // ❌ Not a volunteer
            $this->type   = 'none';
            $this->line1  = 'Not Volunteer';
            $this->line2  = '';
            $this->icon   = 'fa-user';
            $this->styles = 'bg-gray-100 text-gray-800';
        }
    }

    public function render()
    {
        return view('components.user-volunteer-status-badge');
    }
}

