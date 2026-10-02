<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Log extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'branch_id',
        'division_id',
        'description',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Keys whose values are never stored in old_values / new_values, at any
     * depth (e.g. a member nested inside a payment snapshot). write() and the
     * audit-log:redact-sensitive command replace them with REDACTED.
     */
    public const REDACTED_KEYS = [
        'national_id_number',
        'national_id_number_hash',
        'personal_info',
        'password',
        'remember_token',
        'legacy_password_hash',
    ];

    public const REDACTED = '[redacted]';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * The model this log entry is about (Payment, Member, etc.).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes (for filters)
    |--------------------------------------------------------------------------
    */

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        if (!$branchId) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function scopeForDivision(Builder $query, ?int $divisionId): Builder
    {
        if (!$divisionId) {
            return $query;
        }

        return $query->where('division_id', $divisionId);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (!$userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeForAction(Builder $query, ?string $action): Builder
    {
        if (!$action) {
            return $query;
        }

        return $query->where('action', $action);
    }

    public function scopeFromDate(Builder $query, ?string $date): Builder
    {
        if (!$date) {
            return $query;
        }

        return $query->whereDate('created_at', '>=', $date);
    }

    public function scopeToDate(Builder $query, ?string $date): Builder
    {
        if (!$date) {
            return $query;
        }

        return $query->whereDate('created_at', '<=', $date);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: simple logging
    |--------------------------------------------------------------------------
    */

    /**
     * Create a log entry in a concise way.
     *
     * @param  string       $action
     * @param  Model|null   $subject
     * @param  array|null   $context  ['branch_id' => ..., 'division_id' => ...]
     * @param  array|null   $old      Old values snapshot
     * @param  array|null   $new      New values snapshot
     * @param  string|null  $description
     * @return static
     */
    public static function write(
        string $action,
        ?Model $subject = null,
        ?array $context = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null
    ): self {
        $context = $context ?? [];

        $authId = auth()->id();

        // Avoid FK violations: if no such user, set user_id to null
        $userId = null;
        if ($authId) {
            $userExists = \App\Models\User::whereKey($authId)->exists();
            if ($userExists) {
                $userId = $authId;
            }
        }

        return static::create([
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'branch_id'    => $context['branch_id']   ?? null,
            'division_id'  => $context['division_id'] ?? null,
            'description'  => $description,
            'old_values'   => static::redactSensitive($old),
            'new_values'   => static::redactSensitive($new),
        ]);
    }

    /**
     * Recursively replace the value of every REDACTED_KEYS key with REDACTED.
     * Null values are left as null (nothing to hide), so already-redacted or
     * empty snapshots come back unchanged.
     */
    public static function redactSensitive(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                if ($value !== null) {
                    $values[$key] = self::REDACTED;
                }
            } elseif (is_array($value)) {
                $values[$key] = static::redactSensitive($value);
            }
        }

        return $values;
    }
}
