<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Division;

class TaskForce extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'task_force_type_id',
        'branch_id',
        'inactive',
        'team_leader_user_id', // Add this line
        'assist_team_leader_user_id', // Add this line
    ];

    protected $casts = [
        'inactive' => 'boolean',
    ];

    public function taskForceType()
    {
        return $this->belongsTo(TaskForceType::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Assuming a pivot table 'task_force_members' exists
    public function users()
    {
        return $this->belongsToMany(User::class, 'task_force_members', 'task_force_id', 'user_id')
                    ->withTimestamps(); // Useful for tracking when a member was added
    }

    // Define relationship for the team leader
    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_user_id');
    }

    // Define relationship for the assistant team leader
    public function assistantTeamLeader()
    {
        return $this->belongsTo(User::class, 'assist_team_leader_user_id');
    }



    /**
     * Scope a query to only include active task forces.
     */
    public function scopeActive($query)
    {
        return $query->where('inactive', false);
    }

    /**
     * Check if a given user is authorized to view this Task Force.
     *
     * @param \App\Models\User $user The user to check.
     * @return bool
     */
    public function isViewableBy(User $user): bool
    {
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        switch ($accessLevel) {
            case 'national':
                // National admins can view all task forces.
                return true;

            case 'branch':
                // Branch admins can view task forces within their branch.
                return $this->branch_id == $scopedId;

            case 'division':
                // For division-level users, we need to check if the task force's branch
                // matches the branch of the user's division.
                if ($user->division) {
                    return $this->branch_id == $user->division->branch_id;
                }
                return false;

            default:
                // Admin-level authorization fails by default.
                return false;
        }
    }
}
