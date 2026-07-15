<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class UserRcunitTaskforceBadge extends Component
{
    public bool $hasUnit;
    public ?string $unitName = null;
    public ?string $leaderLabel = null;
    public ?string $taskForceLabel = null;

    public function __construct(User $user)
    {
        $unit = $user->redCrossUnit;
        $this->hasUnit = (bool) $unit;

        if ($unit) {
            $this->unitName = $unit->name;

            if ($unit->team_leader_user_id === $user->id) {
                $this->leaderLabel = 'TeamL';
            } elseif ($unit->assistant_team_leader_user_id === $user->id) {
                $this->leaderLabel = 'A-TeamL';
            }
        }

        $taskForceCount = $user->active_task_forces_count ?? 0;
        if ($taskForceCount > 0) {
            $this->taskForceLabel = $taskForceCount === 1 ? 'TF' : $taskForceCount.'TF';
        }
    }

    public function render()
    {
        return view('components.user-rcunit-taskforce-badge');
    }
}
