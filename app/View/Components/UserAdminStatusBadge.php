<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Spatie\Permission\Models\Role;
use Illuminate\View\View;

class UserAdminStatusBadge extends Component
{
    public User $user;

    public string $label = '';
    public string $icon = '';
    public string $styles = '';
    public string $title = '';

    public bool $shouldRender = true;

    protected int $thresholdMonths = 6;

    protected array $adminRoles = [];

    public function __construct(User $user)
    {
        $this->user = $user;

        $this->resolveAdminRoles();
        $this->resolveStatus();
    }

    /**
     * Build admin roles dynamically from the permission: manage-admin-panel
     */
    protected function resolveAdminRoles(): void
    {
        $this->adminRoles = Role::whereHas('permissions', function ($q) {
            $q->where('name', 'manage-admin-panel');
        })->pluck('name')->toArray();
    }

    protected function resolveStatus(): void
    {
        $isAdmin = $this->user->hasAnyRole($this->adminRoles);


        if (! $isAdmin) {
            $this->shouldRender = false;
            return;
        }

        if (! $this->user->last_admin_activity_at) {
            $this->label  = 'No admin activity yet';
            $this->icon   = 'fa-circle-minus';
            $this->styles = 'bg-gray-100 text-gray-800';
            $this->title  = 'This admin has not yet created or updated any records.';
            return;
        }

        if (! $this->user->isAdministrativelyDormant($this->thresholdMonths)) {
            $this->label  = 'Admin active';
            $this->icon   = 'fa-user-cog';
            $this->styles = 'bg-green-100 text-green-800';
            $this->title  = 'Last admin activity: ' . $this->user->last_admin_activity_at->format('Y-m-d');
            return;
        }

        // Dormant admin
        $this->label  = 'Admin dormant';
        $this->icon   = 'fa-user-clock';
        $this->styles = 'bg-amber-100 text-amber-800';
        $this->title  = 'No admin updates in the last ' . $this->thresholdMonths . ' months. Last activity: '
            . $this->user->last_admin_activity_at->format('Y-m-d');
    }

    public function render(): View
    {
        // ✅ If user has no admin role → render EMPTY view
        if (! $this->shouldRender) {
            return view('components.empty');
        }

        return view('components.user-admin-status-badge', [
            'label'  => $this->label,
            'icon'   => $this->icon,
            'styles' => $this->styles,
            'title'  => $this->title,
        ]);
    }
}
