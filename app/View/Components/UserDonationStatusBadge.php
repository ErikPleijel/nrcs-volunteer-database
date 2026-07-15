<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserDonationStatusBadge extends Component
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
     //   $cashDonationsCount = $user->countCashDonations();
     //   $inKindDonationsCount = $user->countInKindDonations();
      //  $hasDonations = ($cashDonationsCount + $inKindDonationsCount) > 0;
        $totalDonations = $user-> getDonationCountAttribute();

        if ($totalDonations > 0) {
            // No active donations
            $this->type    = 'valid';
            $this->message = $totalDonations . ' Donation' . ($totalDonations > 1 ? 's' : '');
            $this->icon    = 'fa-heart';
            $this->styles  = 'bg-green-100 text-green-800';
        }  else {

            $this->type    = 'none';
            $this->message = 'No Donations';
            $this->icon    = 'fa-heart';
            $this->styles  = 'bg-gray-100 text-gray-800';
        }
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.user-donation-status-badge');
    }
}
