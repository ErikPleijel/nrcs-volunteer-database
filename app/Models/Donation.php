<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Donation extends Model
{
    use Approvable, HasFactory, SoftDeletes;

    const DELETED_AT = 'removed_date';

    /** Module key for approval audit actions / notifications. */
    protected $approvalModule = 'donation';

    /** Donations record the submitter in entered_by_user_id, not submitted_by_user_id. */
    public function submitterColumn(): string
    {
        return 'entered_by_user_id';
    }

    /** One-line summary for approval lists. */
    public function approvalSummary(): string
    {
        return $this->formatted_donation.($this->purpose ? ' — '.$this->purpose : '');
    }

    /** A single approved donation alone should not promote a pending_engagement donor to active. */
    protected function promotesFromPendingEngagement(): bool
    {
        return false;
    }

    /** Label => value detail rows for the review page. */
    public function approvalDetailRows(): array
    {
        return [
            'Type' => $this->in_kind_donation ? 'In-kind' : 'Cash',
            'Amount / Item' => $this->formatted_donation,
            'Purpose' => $this->purpose ?: '—',
            'Reference' => $this->reference ?: '—',
            'Date' => optional($this->date_donation)->format('M d, Y') ?? '—',
        ];
    }

    // No need for protected $primaryKey = 'id'; as 'id' is the default
    // No need for public $incrementing = false; as 'id' is auto-incrementing
    // No need for protected $keyType = 'int'; as 'id' is typically int or bigint

    protected $fillable = [
        // 'id', // Removed 'id' from fillable as it's auto-incrementing
        'user_id',
        'organisation_id',
        'amount',
        'date_donation',
        'in_kind_donation',
        'donation_item',
        'reference',
        'purpose',
        'submission_name',
        'anonymous',
        'branch_id',
        'division_id',
        'entered_by_user_id',
        'is_deleted',
        'removed_by_user_id',
        'removed_date',
    ];

    protected $casts = [
        'date_donation' => 'date',
        'in_kind_donation' => 'boolean',
        'anonymous' => 'boolean',
        'is_deleted' => 'boolean',
        'removed_date' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function submittedByUser()
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function removedBy()
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }

    /**
     * Scope a query to only include non-deleted donations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope a query to only include donations belonging to this user
     * personally, not an organisation.
     */
    public function scopePersonal(Builder $query): Builder
    {
        return $query->whereNull('organisation_id');
    }

    /**
     * Scope a query to only include donations attributed to an organisation.
     */
    public function scopeOrganisational(Builder $query): Builder
    {
        return $query->whereNotNull('organisation_id');
    }

    // Accessors
    public function getDonorFullNameAttribute()
    {
        if ($this->anonymous) {
            return 'Anonymous';
        }

        // Safely access user's full name, providing a fallback if user is null
        return $this->user ? $this->user->full_name : 'N/A (User Not Found)';
    }

    /**
     * Get the formatted donation string based on donation type.
     */
    public function getFormattedDonationAttribute(): string
    {
        if ($this->in_kind_donation) {
            return "{$this->amount} {$this->donation_item}";
        } else {
            // Assuming 'amount' is a numeric type, cast to int to remove decimals
            return '₦'.(int) $this->amount;
        }
    }

    public function getDonationReferenceAttribute(): string
    {
        $branchCode = 'UNK';

        if ($this->branch) {
            $branchCode = strtoupper($this->branch->code ?? $this->branch->name ?? 'UNK');
        }

        return "DON-{$this->id}/{$branchCode}";
        //  return "DON-{$this->id}";
    }
}
