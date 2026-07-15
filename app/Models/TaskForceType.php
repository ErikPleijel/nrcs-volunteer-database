<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskForceType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_force_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'level',
        'include_in_list',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'include_in_list' => 'boolean',
    ];

    /**
     * Get the task forces for this task force type.
     */
    public function taskForces()
    {
        return $this->hasMany(TaskForce::class);
    }

    /**
     * Get only active task forces for this type.
     */
    public function activeTaskForces()
    {
        return $this->hasMany(TaskForce::class)->where('inactive', false);
    }

    /**
     * Scope a query to only include task force types that should be included in lists.
     */
    public function scopeIncludeInList($query)
    {
        return $query->where('include_in_list', true);
    }

    /**
     * Scope a query to exclude task force types from lists.
     */
    public function scopeExcludeFromList($query)
    {
        return $query->where('include_in_list', false);
    }

    /**
     * Scope a query to filter by level.
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to filter by minimum level.
     */
    public function scopeMinLevel($query, $minLevel)
    {
        return $query->where('level', '>=', $minLevel);
    }

    /**
     * Scope a query to filter by maximum level.
     */
    public function scopeMaxLevel($query, $maxLevel)
    {
        return $query->where('level', '<=', $maxLevel);
    }

    /**
     * Scope a query to order by level ascending.
     */
    public function scopeOrderByLevel($query)
    {
        return $query->orderBy('level', 'asc');
    }

    /**
     * Scope a query to order by level descending.
     */
    public function scopeOrderByLevelDesc($query)
    {
        return $query->orderBy('level', 'desc');
    }

    /**
     * Scope a query to order by name.
     */
    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Check if this task force type should be included in lists.
     *
     * @return bool
     */
    public function shouldIncludeInList()
    {
        return $this->include_in_list;
    }

    /**
     * Get the level display.
     *
     * @return string
     */
    public function getLevelDisplayAttribute()
    {
        return "Level {$this->level}";
    }

    /**
     * Check if task force type is at or above a certain level.
     *
     * @param int $level
     * @return bool
     */
    public function isAtOrAboveLevel($level)
    {
        return $this->level >= $level;
    }

    /**
     * Check if task force type is at or below a certain level.
     */
    public function isAtOrBelowLevel($level)
    {
        return $this->level <= $level;
    }

    /**
     * Get the total number of task forces for this type.
     *
     * @return int
     */
    public function getTotalTaskForcesCountAttribute()
    {
        return $this->taskForces()->count();
    }

    /**
     * Get the total number of active task forces for this type.
     *
     * @return int
     */
    public function getActiveTaskForcesCountAttribute()
    {
        return $this->activeTaskForces()->count();
    }

    /**
     * Check if this type has any active task forces.
     *
     * @return bool
     */
    public function hasActiveTaskForces()
    {
        return $this->activeTaskForces()->exists();
    }

    /**
     * Get task force types grouped by level.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function groupedByLevel()
    {
        return static::orderBy('level')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('level');
    }
}
