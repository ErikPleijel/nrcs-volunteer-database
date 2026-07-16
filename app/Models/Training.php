<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use Approvable, HasFactory;

    /** Module key for approval audit actions / notifications. */
    protected $approvalModule = 'training';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trainings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'training_type_id',
        'training_date',
        'duration',
        'valid_years',
        'submitted_at',
        'submission_name',
        'is_deleted',
        'reference',
        'submitted_by_user_id',
        'branch_id',
        'division_id',
        'removed_by_user_id',
        'removed_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'training_type_id' => 'integer',
        'training_date' => 'date',
        'duration' => 'integer',
        'valid_years' => 'integer',
        'submitted_at' => 'datetime',
        'is_deleted' => 'boolean',
        'submitted_by_user_id' => 'integer',
        'branch_id' => 'integer',
        'division_id' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'training_date',
        'submitted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * After a training is approved, recompute the member's denormalised latest
     * first-aid date. Called unconditionally — recalculateLastFirstAidAt() derives
     * from the member's APPROVED first-aid trainings and is a no-op otherwise.
     */
    protected function afterApproved(?User $member): void
    {
        $member?->recalculateLastFirstAidAt();
    }

    /**
     * After a training is demoted back to pending (edited post-approval), recompute
     * the member's denormalised latest first-aid date the same way afterApproved()
     * does — this training no longer counts as approved, so it may need to drop out
     * of the MAX(training_date) aggregate recalculateLastFirstAidAt() derives from.
     */
    protected function afterDemoted(?User $member): void
    {
        $member?->recalculateLastFirstAidAt();
    }

    /** One-line summary for approval lists. */
    public function approvalSummary(): string
    {
        return ($this->trainingType->name ?? 'Training').' — '.(optional($this->training_date)->format('M d, Y') ?? '');
    }

    /** Label => value detail rows for the review page. */
    public function approvalDetailRows(): array
    {
        return [
            'Training type' => $this->trainingType->name ?? '—',
            'Date' => optional($this->training_date)->format('M d, Y') ?? '—',
            'Duration' => $this->duration ? $this->duration.' hrs' : '—',
            'Reference' => $this->reference ?: '—',
        ];
    }

    /**
     * Get the training type for this training.
     */
    public function trainingType()
    {
        return $this->belongsTo(TrainingType::class, 'training_type_id');
    }

    /**
     * Get the user who took this training.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who submitted this training record.
     */
    public function submittedByUser()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function removedByUser()
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    /**
     * Get the branch for this training.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the division for this training.
     */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function certificatePrints()
    {
        return $this->hasMany(CertificatePrint::class);
    }

    /**
     * Scope a query to only include non-deleted trainings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope a query to only include deleted trainings.
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    /**
     * Scope a query to filter by training type.
     */
    public function scopeByTrainingType($query, $trainingTypeId)
    {
        return $query->where('training_type_id', $trainingTypeId);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('training_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by current year.
     */
    public function scopeCurrentYear($query)
    {
        return $query->whereYear('training_date', now()->year);
    }

    /**
     * Scope a query to filter by branch.
     */
    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope a query to filter by division.
     */
    public function scopeByDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    /**
     * Scope a query to only include valid (non-expired) trainings.
     */
    public function scopeValid($query)
    {
        return $query->where('is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('valid_years')
                    ->orWhereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) >= CURDATE()');
            });
    }

    /**
     * Scope a query to only include expired trainings.
     */
    public function scopeExpired($query)
    {
        return $query->where('is_deleted', false)
            ->whereNotNull('valid_years')
            ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) < CURDATE()');
    }

    /**
     * Scope a query to order by training date (newest first).
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('training_date', 'desc');
    }

    /**
     * Scope a query to order by training date (oldest first).
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('training_date', 'asc');
    }

    /**
     * Check if this training is currently active (not deleted).
     *
     * @return bool
     */
    public function isActive()
    {
        return ! $this->is_deleted;
    }

    /**
     * Check if this training has expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        // If not active (deleted), consider it expired
        if (! $this->isActive()) {
            return true;
        }

        // If no valid_years set, it never expires
        if (! $this->valid_years || ! $this->training_date) {
            return false;
        }

        // Calculate expiry date and check if it's in the past
        $expiryDate = $this->training_date->copy()->addYears($this->valid_years);

        return $expiryDate->isPast();
    }

    /**
     * Check if this training is still valid.
     *
     * @return bool
     */
    public function isValid()
    {
        // Must be active (not deleted)
        if (! $this->isActive()) {
            return false;
        }

        // If no valid_years set, it's permanently valid
        if (! $this->valid_years) {
            return true;
        }

        // Otherwise, check if it hasn't expired
        return ! $this->isExpired();
    }

    /**
     * Check if this training is completed (active but with expiry logic).
     * This is useful for display purposes.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->isActive();
    }

    /**
     * Get the status of this training for display purposes.
     *
     * @return string
     */
    public function getStatusAttribute()
    {
        if (! $this->isActive()) {
            return 'deleted';
        }

        if (! $this->valid_years) {
            return 'permanent'; // No expiry
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->expiresSoon(30)) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /**
     * Get the expiry date of this training.
     *
     * @return Carbon|null
     */
    public function getExpiryDateAttribute()
    {
        if (! $this->valid_years || ! $this->training_date) {
            return null;
        }

        return $this->training_date->copy()->addYears($this->valid_years);
    }

    /**
     * Get days until expiry.
     *
     * @return int|null
     */
    public function getDaysUntilExpiryAttribute()
    {
        $expiryDate = $this->expiry_date;

        if (! $expiryDate) {
            return null;
        }

        return now()->diffInDays($expiryDate, false);
    }

    /**
     * Check if training expires within given days.
     *
     * @param  int  $days
     * @return bool
     */
    public function expiresSoon($days = 30)
    {
        $expiryDate = $this->expiry_date;

        if (! $expiryDate) {
            return false;
        }

        return $expiryDate->isBetween(now(), now()->addDays($days));
    }

    /**
     * Get formatted duration display.
     *
     * @return string
     */
    public function getFormattedDurationAttribute()
    {
        if (! $this->duration) {
            return 'Not specified';
        }

        return $this->duration.' day'.($this->duration > 1 ? 's' : '');
    }

    /**
     * Get formatted validity display.
     *
     * @return string
     */
    public function getValidityDisplayAttribute()
    {
        if (! $this->valid_years) {
            return 'No expiry';
        }

        return $this->valid_years.' year'.($this->valid_years > 1 ? 's' : '');
    }

    /**
     * Get the training reference in TRN-{id}/{BRANCH_CODE} format.
     */
    public function getTrainingReferenceAttribute(): string
    {
        $branchCode = 'UNK';

        if ($this->branch) {
            $branchCode = strtoupper($this->branch->code ?? $this->branch->name ?? 'UNK');
        }

        return "TRN-{$this->id}/{$branchCode}";
        // return "TRN-{$this->id}";
    }
}
