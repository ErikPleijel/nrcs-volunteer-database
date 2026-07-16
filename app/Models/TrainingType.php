<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'training_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'validity_years_limit',
        'certificate_hq_only',
        'description',
        'group_id', // Added group_id to fillable attributes
        'is_first_aid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'validity_years_limit' => 'integer',
        'certificate_hq_only' => 'boolean',
        'group_id'     => 'integer',
        'is_first_aid' => 'boolean',
    ];

    /**
     * Get the trainings for this training type.
     */
    public function trainings()
    {
        return $this->hasMany(Training::class);
    }

    /**
     * Get only active trainings for this training type.
     */
    public function activeTrainings()
    {
        return $this->hasMany(Training::class)->where('is_deleted', false);
    }

    /**
     * Get the training group that owns the training type.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'group_id');
    }

    /**
     * Scope a query to only include active training types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive training types.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include HQ-only certificate types.
     */
    public function scopeHqOnly($query)
    {
        return $query->where('certificate_hq_only', true);
    }

    /**
     * Scope a query to only include non-HQ certificate types.
     */
    public function scopeNonHqOnly($query)
    {
        return $query->where('certificate_hq_only', false);
    }

    /**
     * Scope a query to order by name.
     */
    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Scope a query to order by group and then by name.
     */
    public function scopeOrderByGroupThenName($query)
    {
        return $query->leftJoin('training_groups', 'training_types.group_id', '=', 'training_groups.id')
            ->orderBy('training_groups.group_name')
            ->orderBy('training_types.name')
            ->select('training_types.*'); // Ensure we only select fields from the main table
    }

    /**
     * Check if this training type is currently available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->is_active;
    }

    /**
     * Check if certificates can only be issued by HQ.
     *
     * @return bool
     */
    public function isHqOnlyCertificate()
    {
        return $this->certificate_hq_only;
    }

    /**
     * Check if training type has a validity limit.
     *
     * @return bool
     */
    public function hasValidityLimit()
    {
        return !is_null($this->validity_years_limit);
    }

    /**
     * Get the total number of trainings for this type.
     *
     * @return int
     */
    public function getTotalTrainingsCountAttribute()
    {
        return $this->trainings()->count();
    }

    /**
     * Get the total number of active trainings for this type.
     *
     * @return int
     */
    public function getActiveTrainingsCountAttribute()
    {
        return $this->activeTrainings()->count();
    }

    /**
     * Get formatted validity years display.
     *
     * @return string
     */
    public function getValidityDisplayAttribute()
    {
        if (!$this->validity_years_limit) {
            return 'No expiry';
        }

        return $this->validity_years_limit . ' year' . ($this->validity_years_limit > 1 ? 's' : '');
    }
}
