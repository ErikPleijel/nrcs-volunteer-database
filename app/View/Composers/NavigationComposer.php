<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NavigationComposer
{
    public function compose(View $view): void
    {
        $navItems = [
            ['url' => '/', 'label' => 'Home', 'pattern' => '/', 'icon' => 'fas fa-home'],
            ['url' => '/profile', 'label' => 'My Profile', 'pattern' => 'profile', 'icon' => 'fas fa-user'],
            ['url' => '/my-unit', 'label' => 'My Team', 'pattern' => 'my-unit', 'icon' => 'fas fa-users'],
            [
                'url' => '/admin/dashboard',
                'label' => 'Admin',
                'pattern' => 'admin*',
                'icon' => 'fas fa-shield-alt',
                'permission' => 'manage-admin-panel',
            ],
        ];

        foreach ($navItems as &$item) {
            $allowed = true;

            if (isset($item['permission'])) {
                $allowed = Auth::check() && Auth::user()->can($item['permission']);
            }

            if (in_array($item['label'], ['My Profile', 'My Team']) && ! Auth::check()) {
                $allowed = false;
            }

            if ($item['label'] === 'My Profile' && Auth::check() && Auth::user()->is_super_admin) {
                $allowed = false;
            }

            if ($item['label'] === 'My Team' && Auth::check() && ! Auth::user()->red_cross_unit_id) {
                $allowed = false;
            }

            $item['allowed'] = $allowed;
        }
        unset($item);

        $view->with('navItems', $navItems);
    }
}
