<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a course, as participant or facilitator. result applies to
 * participants only; training_id is set when a passed participant's training
 * record is created on closing the course.
 */
class CourseParticipant extends Model
{
    public const ROLE_PARTICIPANT = 'participant';

    public const ROLE_FACILITATOR = 'facilitator';

    public const ROLES = [self::ROLE_PARTICIPANT, self::ROLE_FACILITATOR];

    public const RESULT_PASSED = 'passed';

    public const RESULT_FAILED = 'failed';

    public const RESULT_ABSENT = 'absent';

    public const RESULTS = [self::RESULT_PASSED, self::RESULT_FAILED, self::RESULT_ABSENT];

    protected $fillable = [
        'course_id',
        'user_id',
        'role',
        'result',
        'result_recorded_by_user_id',
        'result_recorded_at',
        'training_id',
        'added_by_user_id',
    ];

    protected $casts = [
        'result_recorded_at' => 'datetime',
    ];

    protected $attributes = [
        'role' => self::ROLE_PARTICIPANT,
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function resultRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_recorded_by_user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function isFacilitator(): bool
    {
        return $this->role === self::ROLE_FACILITATOR;
    }

    public function hasPassed(): bool
    {
        return $this->result === self::RESULT_PASSED;
    }
}
