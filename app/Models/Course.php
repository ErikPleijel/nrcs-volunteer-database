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

    /**
     * Courses $user may see: national staff all; branch staff their branch's
     * courses; division staff their branch's courses that are not organised
     * by another division. Everyone sees national courses, since participants
     * may come from any branch.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scopedId = $user->getScopedId();

        return match ($user->getAccessLevel()) {
            'national' => $query,
            'branch' => $query->where(fn ($q) => $q
                ->whereNull('branch_id')
                ->orWhere('branch_id', $scopedId ?? 0)),
            'division' => $query->where(fn ($q) => $q
                ->whereNull('branch_id')
                ->orWhere(fn ($q) => $q
                    ->where('branch_id', $user->getScopedBranchId() ?? 0)
                    ->where(fn ($q) => $q->whereNull('division_id')->orWhere('division_id', $scopedId ?? 0)))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Whether $user's area lets them edit, cancel or close this course (the
     * permission itself is checked by route middleware): national staff any
     * course, branch staff their own branch's courses, not national ones.
     */
    public function isManageableBy(User $user): bool
    {
        return match ($user->getAccessLevel()) {
            'national' => true,
            'branch' => $this->branch_id !== null && $this->branch_id === $user->getScopedId(),
            default => false,
        };
    }

    /**
     * Whether $viewer may add or remove $person on this course (the
     * manage_course_participants permission is checked by route middleware).
     * The organisers (isManageableBy) may add anyone, since participants come
     * from any branch; other staff only people in their own branch or
     * division, e.g. a branch nominating its own people for a national course.
     */
    public function canHandlePerson(User $viewer, User $person): bool
    {
        if ($this->isManageableBy($viewer)) {
            return true;
        }

        $scopedId = $viewer->getScopedId();

        return match ($viewer->getAccessLevel()) {
            'branch' => $scopedId !== null && (int) $person->branch_id === $scopedId,
            'division' => $scopedId !== null && (int) $person->division_id === $scopedId,
            default => false,
        };
    }

    /**
     * Whether $viewer is on the organising side and so may record results
     * (manage_course_participants is checked by route middleware): national
     * staff on any course; for a branch course also the staff of that branch,
     * assistants included, who can see it. Staff who only nominated people
     * for another branch's or a national course cannot.
     */
    public function canRecordResults(User $viewer): bool
    {
        if ($viewer->getAccessLevel() === 'national') {
            return true;
        }

        return $this->branch_id !== null && $this->isVisibleTo($viewer);
    }

    /** Results can be recorded from the start date while the course is planned. */
    public function resultsOpen(): bool
    {
        return $this->isPlanned() && ! $this->start_date->isAfter(today());
    }

    /**
     * People $viewer may add to this course (same rule as canHandlePerson()):
     * not archived or anonymized, not a System Administrator, not already on it.
     */
    public function addablePeopleFor(User $viewer): Builder
    {
        $query = User::query()
            ->selectableForEntry()
            ->whereIn('lifecycle_status', ['pending_engagement', ...User::OPERATIONAL_STATUSES])
            ->whereNull('anonymized_at')
            ->whereDoesntHave('courseParticipations', fn ($q) => $q->where('course_id', $this->id));

        if ($this->isManageableBy($viewer)) {
            return $query;
        }

        $scopedId = $viewer->getScopedId();

        return match ($viewer->getAccessLevel()) {
            'branch' => $query->where('branch_id', $scopedId ?? 0),
            'division' => $query->where('division_id', $scopedId ?? 0),
            default => $query->whereRaw('1 = 0'),
        };
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

    /** Status for display, with "ongoing" and "ended, not closed" worked out from the dates. */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelled',
            $this->isClosed() => 'Closed',
            $this->isOngoing() => 'Ongoing',
            $this->end_date?->isBefore(today()) => 'Ended, not closed',
            default => 'Planned',
        };
    }

    /** Tailwind classes for the status badge. */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status_label) {
            'Cancelled' => 'bg-gray-100 text-gray-700',
            'Closed' => 'bg-green-100 text-green-800',
            'Ongoing' => 'bg-blue-100 text-blue-800',
            'Ended, not closed' => 'bg-amber-100 text-amber-800',
            default => 'bg-indigo-100 text-indigo-800',
        };
    }

    /** "3 Mar 2027" or "3 – 5 Mar 2027" / "30 Mar – 2 Apr 2027". */
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date;
        $end = $this->end_date;

        if (! $start) {
            return '';
        }
        if (! $end || $start->isSameDay($end)) {
            return $start->format('j M Y');
        }
        if ($start->isSameMonth($end)) {
            return $start->format('j').' – '.$end->format('j M Y');
        }
        if ($start->isSameYear($end)) {
            return $start->format('j M').' – '.$end->format('j M Y');
        }

        return $start->format('j M Y').' – '.$end->format('j M Y');
    }
}
