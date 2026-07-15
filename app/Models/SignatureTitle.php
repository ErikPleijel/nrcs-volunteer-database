<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignatureTitle extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'signature_titles';

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
     * The attributes with default values.
     *
     * @var array
     */
    protected $attributes = [
        'level' => 2,
        'include_in_list' => true,
    ];

    /**
     * Scope a query to only include signature titles that should be included in lists.
     */
    public function scopeIncludeInList($query)
    {
        return $query->where('include_in_list', true);
    }

    /**
     * Scope a query to exclude signature titles from lists.
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
     * Check if this signature title should be included in lists.
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
     * Check if signature title is at or above a certain level.
     *
     * @param int $level
     * @return bool
     */
    public function isAtOrAboveLevel($level)
    {
        return $this->level >= $level;
    }

    /**
     * Check if signature title is at or below a certain level.
     *
     * @param int $level
     * @return bool
     */
    public function isAtOrBelowLevel($level)
    {
        return $this->level <= $level;
    }

    /**
     * Check if this is a high-level signature title.
     *
     * @param int $threshold
     * @return bool
     */
    public function isHighLevel($threshold = 5)
    {
        return $this->level >= $threshold;
    }

    /**
     * Check if this is a low-level signature title.
     *
     * @param int $threshold
     * @return bool
     */
    public function isLowLevel($threshold = 3)
    {
        return $this->level <= $threshold;
    }

    /**
     * Get signature titles grouped by level.
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

    /**
     * Get the most common level.
     *
     * @return int
     */
    public static function getMostCommonLevel()
    {
        return static::selectRaw('level, COUNT(*) as count')
                    ->groupBy('level')
                    ->orderBy('count', 'desc')
                    ->first()
                    ->level ?? 2;
    }

    /**
     * Get signature titles suitable for documents.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function forDocuments()
    {
        return static::includeInList()
                    ->orderByLevel()
                    ->orderByName()
                    ->get();
    }
}
