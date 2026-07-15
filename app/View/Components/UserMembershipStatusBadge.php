<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserMembershipStatusBadge extends Component
{
    public string $type;
    public string $line1;
    public string $line2;
    public string $icon;
    public string $styles;
    public bool $line2Danger = false;

    public function __construct(User $user)
    {
        $isVolunteer    = $user->isVolunteer();           // red_cross_unit_id IS NOT NULL
        $canVolunteer   = $user->wantsVolunteer();        // can_contribute_volunteering
        $canMember      = $user->wantsMembership();       // can_contribute_member

        if ($isVolunteer) {
            // In an RC unit — always a volunteer regardless of payment
            $this->type   = 'volunteer';
            $this->line1  = 'Volunteer';
            $this->line2  = $user->current_membership_name ?? 'No payment';
            $this->line2Danger = is_null($user->current_membership_name);
            $this->icon   = 'fa-hands-helping';
            $this->styles = 'bg-green-100 text-green-800';

        } elseif ($user->isUnassignedGhost()) {
            // Left their RC unit, no genuine (non-volunteer-fee) membership to fall back on
            $this->type   = 'unassigned';
            $this->line1  = 'Volunteer/Unassigned';
            $this->line2  = $user->current_membership_name ?? 'No unit assigned';
            $this->icon   = 'fa-user-slash';
            $this->styles = 'bg-yellow-100 text-yellow-800';

        } elseif ($user->currentMembershipPayment) {
            // Valid payment, not in RC unit
            $this->type   = 'active';
            $this->line1  = 'Member';
            $this->line2  = $user->current_membership_name ?? '';
            $this->icon   = 'fa-id-card';
            $this->styles = 'bg-blue-100 text-blue-800';

        } elseif ($user->latestMembershipPayment && $canMember && !$canVolunteer) {
            // Expired payment, membership ticked, not interested in volunteering
            $this->type   = 'expired';
            $this->line1  = 'Membership';
            $this->line2  = 'Expired';
            $this->icon   = 'fa-id-card';
            $this->styles = 'bg-red-100 text-red-800';

        } elseif ($canVolunteer) {
            // No RC unit, no valid payment — wants to volunteer
            // (covers: volunteer only, both ticked)
            $this->type   = 'volunteer_interested';
            $this->line1  = 'Volunteer';
            $this->line2  = 'Interested';
            $this->icon   = 'fa-hand-sparkles';
            $this->styles = 'bg-yellow-100 text-yellow-800';

        } else {
            // No RC unit, no valid payment, no volunteer interest
            // (covers: membership only ticked, neither ticked)
            $this->type   = 'membership_interested';
            $this->line1  = 'Membership';
            $this->line2  = 'Interested';
            $this->icon   = 'fa-id-card';
            $this->styles = 'bg-yellow-100 text-amber-800';
        }
    }

    public function render()
    {
        return view('components.user-membership-status-badge');
    }
}
