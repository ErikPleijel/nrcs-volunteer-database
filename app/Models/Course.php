<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A planned training course that people attend. Closing it creates a Training
 * record for each participant who passed (once only, see trainings.course_id).
 *
 * branch_id NULL = national course. Status is planned / closed / cancelled;
 * "ongoing" is not stored, it is a planned course whose dates include today.
 */
class Course extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PLANNED, self::STATUS_CLOSED, self::STATUS_CANCELLED];

    protected $fillable = [
        'name',
        'training_type_id',
        'start_date',
        'end_date',
        'place',
        'branch_id',
        'division_id',
        'external_facilitators',
        'status',
        'created_by_user_id',
        'closed_at',
        'closed_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PLANNED,
    ];

    // --- Relationships ---

    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** Everyone on the course, participants and facilitators. */
    public function courseParticipants(): HasMany
    {
        return $this->hasMany(CourseParticipant::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_participants')
            ->wherePivot('role', CourseParticipant::ROLE_PARTICIPANT)
            ->withPivot(['role', 'result', 'result_recorded_at', 'training_id'])
            ->withTimestamps();
    }

    public function facilitators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_participants')
            ->wherePivot('role', CourseParticipant::ROLE_FACILITATOR)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** Training records issued when the course was closed. */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    // --- Scopes ---

    /** Planned courses that have not ended yet (includes ongoing ones). */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED)
            ->whereDate('end_date', '>=', today());
    }

    // --- Status helpers ---

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** A planned course whose dates include today. */
    public function isOngoing(): bool
    {
        return $this->isPlanned()
            && today()->betweenIncluded($this->start_date, $this->end_date);
    }

    public function isNational(): bool
    {
        return $this->branch_id === null;
    }
}
