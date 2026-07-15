<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the activities for this activity type.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get only active activities for this activity type.
     */
    public function activeActivities()
    {
        return $this->hasMany(Activity::class)->where('is_deleted', false);
    }

    /**
     * Scope a query to only include active activity types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive activity types.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to order by name.
     */
    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Check if this activity type is currently available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->is_active;
    }

    /**
     * Get the total number of activities for this type.
     *
     * @return int
     */
    public function getTotalActivitiesCountAttribute()
    {
        return $this->activities()->count();
    }

    /**
     * Get the total number of active activities for this type.
     *
     * @return int
     */
    public function getActiveActivitiesCountAttribute()
    {
        return $this->activeActivities()->count();
    }

    /**
     * Get the total hours logged for this activity type.
     *
     * @return int
     */
    public function getTotalHoursAttribute()
    {
        return $this->activeActivities()->sum('hours') ?? 0;
    }
}
