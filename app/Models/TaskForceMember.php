<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskForceMember extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_force_members';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_force_id',
        'user_id',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'task_force_id' => 'integer',
        'user_id' => 'integer',
        'timestamp' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'timestamp',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the task force that this member belongs to.
     */
    public function taskForce()
    {
        return $this->belongsTo(TaskForce::class, 'task_force_id');
    }

    /**
     * Get the user (member) of this task force.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope a query to filter by task force.
     */
    public function scopeByTaskForce($query, $taskForceId)
    {
        return $query->where('task_force_id', $taskForceId);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by active task forces only.
     */
    public function scopeActiveTaskForces($query)
    {
        return $query->whereHas('taskForce', function ($q) {
            $q->where('inactive', false);
        });
    }

    /**
     * Scope a query to filter by inactive task forces only.
     */
    public function scopeInactiveTaskForces($query)
    {
        return $query->whereHas('taskForce', function ($q) {
            $q->where('inactive', true);
        });
    }

    /**
     * Scope a query to filter by task force type.
     */
    public function scopeByTaskForceType($query, $taskForceTypeId)
    {
        return $query->whereHas('taskForce', function ($q) use ($taskForceTypeId) {
            $q->where('task_force_type_id', $taskForceTypeId);
        });
    }

    /**
     * Scope a query to filter by branch.
     */
    public function scopeByBranch($query, $branchId)
    {
        return $query->whereHas('taskForce', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        });
    }

    /**
     * Scope a query to filter by division.
     */
    public function scopeByDivision($query, $divisionId)
    {
        return $query->whereHas('taskForce', function ($q) use ($divisionId) {
            $q->where('division_id', $divisionId);
        });
    }

    /**
     * Scope a query to order by timestamp (newest first).
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('timestamp', 'desc');
    }

    /**
     * Scope a query to order by timestamp (oldest first).
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('timestamp', 'asc');
    }

    /**
     * Scope a query to include task force and user relationships.
     */
    public function scopeWithRelations($query)
    {
        return $query->with(['taskForce', 'user']);
    }

    /**
     * Check if the task force this member belongs to is active.
     *
     * @return bool
     */
    public function isInActiveTaskForce()
    {
        return $this->taskForce && $this->taskForce->isActive();
    }

    /**
     * Check if the task force this member belongs to is inactive.
     *
     * @return bool
     */
    public function isInInactiveTaskForce()
    {
        return $this->taskForce && $this->taskForce->isInactive();
    }

    /**
     * Check if the user is a team leader of this task force.
     *
     * @return bool
     */
    public function isTeamLeader()
    {
        return $this->taskForce &&
               $this->taskForce->team_leader_user_id === $this->user_id;
    }

    /**
     * Check if the user is an assistant team leader of this task force.
     *
     * @return bool
     */
    public function isAssistantTeamLeader()
    {
        return $this->taskForce &&
               $this->taskForce->assist_team_leader_user_id === $this->user_id;
    }

    /**
     * Check if the user has a leadership role in this task force.
     *
     * @return bool
     */
    public function hasLeadershipRole()
    {
        return $this->isTeamLeader() || $this->isAssistantTeamLeader();
    }

    /**
     * Get the member's role in the task force.
     *
     * @return string
     */
    public function getRoleAttribute()
    {
        if ($this->isTeamLeader()) {
            return 'Team Leader';
        } elseif ($this->isAssistantTeamLeader()) {
            return 'Assistant Team Leader';
        } else {
            return 'Member';
        }
    }

    /**
     * Get the task force status for this membership.
     *
     * @return string
     */
    public function getTaskForceStatusAttribute()
    {
        return $this->taskForce ? $this->taskForce->status_display : 'Unknown';
    }

    /**
     * Get membership duration in days.
     *
     * @return int
     */
    public function getMembershipDurationAttribute()
    {
        return $this->timestamp->diffInDays(now());
    }

    /**
     * Get formatted membership duration.
     *
     * @return string
     */
    public function getFormattedMembershipDurationAttribute()
    {
        $days = $this->membership_duration;

        if ($days < 1) {
            return 'Less than a day';
        } elseif ($days < 30) {
            return "{$days} day" . ($days > 1 ? 's' : '');
        } elseif ($days < 365) {
            $months = floor($days / 30);
            return "{$months} month" . ($months > 1 ? 's' : '');
        } else {
            $years = floor($days / 365);
            return "{$years} year" . ($years > 1 ? 's' : '');
        }
    }

    /**
     * Create a new task force membership.
     *
     * @param int $taskForceId
     * @param int $userId
     * @return static
     */
    public static function createMembership($taskForceId, $userId)
    {
        return static::create([
            'task_force_id' => $taskForceId,
            'user_id' => $userId,
            'timestamp' => now(),
        ]);
    }

    /**
     * Remove a task force membership.
     *
     * @param int $taskForceId
     * @param int $userId
     * @return bool
     */
    public static function removeMembership($taskForceId, $userId)
    {
        return static::where('task_force_id', $taskForceId)
                    ->where('user_id', $userId)
                    ->delete();
    }

    /**
     * Check if a membership exists.
     *
     * @param int $taskForceId
     * @param int $userId
     * @return bool
     */
    public static function membershipExists($taskForceId, $userId)
    {
        return static::where('task_force_id', $taskForceId)
                    ->where('user_id', $userId)
                    ->exists();
    }
}
