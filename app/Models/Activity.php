<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use Approvable;

    /** Module key for approval audit actions / notifications. */
    protected $approvalModule = 'activity';

    protected $fillable = [
        'activity_type_id',
        'user_id',
        'date',
        'hours',
        'submission_name',
        'reference',
        'branch_id',
        'division_id',
        // polymorphic pair:
        'assignable_id',
        'assignable_type',
        'submitted_by_user_id',
        'submitted_at',
        'is_deleted',
        'removed_by_user_id',
        'removed_date',

    ];

    protected $casts = [
        'date' => 'date',
        'submitted_at' => 'datetime',
        'is_deleted' => 'boolean',
        'removed_date' => 'date',
    ];

    /* -------------------------
     | Relationships
     |--------------------------*/

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** One-line summary for approval lists. */
    public function approvalSummary(): string
    {
        $type = $this->activityType->name ?? 'Activity';
        $hours = $this->hours ? $this->hours.' hrs · ' : '';

        return $type.' — '.$hours.(optional($this->date)->format('M d, Y') ?? '');
    }

    /** Label => value detail rows for the review page. */
    public function approvalDetailRows(): array
    {
        return [
            'Activity type' => $this->activityType->name ?? '—',
            'Hours' => $this->hours ?? '—',
            'Date' => optional($this->date)->format('M d, Y') ?? '—',
            'Unit' => $this->assignable->name ?? '—',
            'Reference' => $this->reference ?: '—',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Polymorphic target: RedCrossUnit OR TaskForce.
     * Uses assignable_type/assignable_id.
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function removedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    /* -------------------------
     | Backwards-friendly helpers
     |--------------------------*/

    /**
     * Convenience: get the “type tag” as 'red_cross_unit', 'task_force', or null.
     */
    public function getUnitTypeAttribute(): ?string
    {
        if ($this->assignable_type === RedCrossUnit::class) {
            return 'red_cross_unit';
        }
        if ($this->assignable_type === TaskForce::class) {
            return 'task_force';
        }

        return null;
    }

    /**
     * Back-compat accessor so existing views like $activity->redCrossUnit
     * don’t explode. Returns the related model if it’s a RedCrossUnit, otherwise null.
     *
     * IMPORTANT: This is an ATTRIBUTE accessor, not an Eloquent RELATIONSHIP method.
     * When eager loading (e.g., `->with('redCrossUnit')`), Eloquent looks for a
     * method returning a `Illuminate\Database\Eloquent\Relations\Relation` instance.
     * Since `RedCrossUnit` is linked polymorphically via `assignable`, eager loading
     * should use `->with('assignable')` and then check the type within the accessor/logic.
     */
    public function getRedCrossUnitAttribute()
    {
        // If assignable relationship is loaded and it's a RedCrossUnit, return it.
        // This handles cases where `->with('assignable')` was used.
        if ($this->relationLoaded('assignable') && $this->assignable instanceof RedCrossUnit) {
            return $this->assignable;
        }

        // If not loaded polymorphically, and there's a direct red_cross_unit_id (unlikely based on fillable),
        // we might attempt to lazy load. However, the schema implies assignable is the source.
        // For correctness with polymorphic relation, only the 'assignable' path is relevant for RedCrossUnit.

        return null;
    }

    /**
     * Helper to set the assignable pair from a model instance.
     * Example: $activity->setAssignable($unit); // $unit is RedCrossUnit|TaskForce
     */
    public function setAssignable(Model $model): void
    {
        $this->assignable_type = get_class($model);
        $this->assignable_id = $model->getKey();
    }

    /* -------------------------
     | Scopes
     |--------------------------*/

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    public function scopeWithTrashed($query)
    {
        return $query;
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->where('is_deleted', true);
    }

    public function scopeWithoutTrashed($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeByActivityType($query, $activityTypeId)
    {
        return $query->where('activity_type_id', $activityTypeId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter by specific unit type (either 'red_cross_unit' or 'task_force').
     */
    public function scopeByUnitType($query, string $type)
    {
        $class = match ($type) {
            'red_cross_unit' => RedCrossUnit::class,
            'task_force' => TaskForce::class,
            default => null,
        };

        return $class
            ? $query->where('assignable_type', $class)
            : $query->whereNull('assignable_type');
    }

    /**
     * Filter by a specific RedCrossUnit id.
     * This scope expects activities to have the assignable polymorphic relationship set to a RedCrossUnit.
     */
    public function scopeForRedCrossUnit($query, $unitId)
    {
        return $query
            ->where('assignable_type', RedCrossUnit::class)
            ->where('assignable_id', $unitId);
    }

    /**
     * Get the activity reference in VOL-{id}/{BRANCH_CODE} format.
     */
    public function getActivityReferenceAttribute(): string
    {
        $branchCode = 'UNK';

        if ($this->branch) {
            $branchCode = strtoupper($this->branch->code ?? $this->branch->name ?? 'UNK');
        }

        return "VOL-{$this->id}/{$branchCode}";
        // return "VOL-{$this->id}";
    }

    /**
     * Filter by a specific TaskForce id.
     */
    public function scopeForTaskForce($query, $taskForceId)
    {
        return $query
            ->where('assignable_type', TaskForce::class)
            ->where('assignable_id', $taskForceId);
    }
}
