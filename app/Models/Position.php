<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'positions';

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
     * Scope a query to only include positions that should be included in lists.
     */
    public function scopeIncludeInList($query)
    {
        return $query->where('include_in_list', true);
    }

    /**
     * Scope a query to exclude positions from lists.
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
     * Check if this position should be included in lists.
     *
     * @return bool
     */
    public function shouldIncludeInList()
    {
        return $this->include_in_list;
    }

    /**
     * Check if this position has a level assigned.
     *
     * @return bool
     */
    public function hasLevel()
    {
        return !is_null($this->level);
    }

    /**
     * Get the level display with fallback.
     *
     * @return string
     */
    public function getLevelDisplayAttribute()
    {
        return $this->level ? "Level {$this->level}" : 'No level assigned';
    }

    /**
     * Check if position is at or above a certain level.
     *
     * @param int $level
     * @return bool
     */
    public function isAtOrAboveLevel($level)
    {
        return $this->level && $this->level >= $level;
    }

    /**
     * Check if position is at or below a certain level.
     *
     * @param int $level
     * @return bool
     */
    public function isAtOrBelowLevel($level)
    {
        return $this->level && $this->level <= $level;
    }

    /**
     * Get positions grouped by level.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function groupedByLevel()
    {
        return static::whereNotNull('level')
                    ->orderBy('level')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('level');
    }
}
