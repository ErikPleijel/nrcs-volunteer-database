<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    use Approvable, HasFactory;

    /** Module key for approval audit actions / notifications. */
    protected $approvalModule = 'payment';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'membership_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'organisation_id',
        'payment_date',
        'expiry_date',
        'membership_fee_id',
        'is_deleted',
        'submitted_at',
        'submission_name',
        'reference',
        'submitted_by_user_id',
        'branch_id',
        'division_id',
        'id_card_included',
        'removed_date',
        'removed_by_user_id',
        'payment_channel',
        'gateway_reference',
        'gateway_response',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_date' => 'date',
        'expiry_date' => 'date',
        'membership_fee_id' => 'integer',
        'is_deleted' => 'boolean',
        'submitted_at' => 'datetime',
        'submitted_by_user_id' => 'integer',
        'branch_id' => 'integer',
        'division_id' => 'integer',
        'id_card_included' => 'boolean',
        'removed_date' => 'datetime',
        'removed_by_user_id' => 'integer',
        'gateway_response' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'payment_date',
        'expiry_date',
        'submitted_at',
        'created_at',
        'updated_at',
        'removed_date',
    ];

    /**
     * Get the user who made this payment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the user who submitted this payment record.
     */
    public function submittedByUser()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** One-line summary for approval lists. */
    public function approvalSummary(): string
    {
        return ($this->membershipFee->name ?? 'Payment').' — '.(optional($this->payment_date)->format('M d, Y') ?? '');
    }

    /**
     * An organisational payment's contact person should not be promoted from
     * pending_engagement to active on approval — the payment belongs to the
     * organisation, not the contact person's own membership. Personal payments
     * (organisation_id null) still promote as before.
     */
    public function promotesFromPendingEngagement(): bool
    {
        return $this->organisation_id === null;
    }

    /** Label => value detail rows for the review page. */
    public function approvalDetailRows(): array
    {
        return [
            'Fee' => $this->membershipFee->name ?? '—',
            'Validity' => $this->membershipFee
                ? $this->membershipFee->validity_years . ' ' .
                  Str::plural('year', $this->membershipFee->validity_years)
                : '—',
            'Payment date' => optional($this->payment_date)->format('M d, Y') ?? '—',
            'Expiry' => optional($this->expiry_date)->format('M d, Y') ?? '—',
            'Reference' => $this->reference ?: '—',
            'ID card included' => $this->id_card_included ? 'Yes' : 'No',
        ];
    }

    private ?MembershipPayment $extendsPaymentCache = null;

    private bool $extendsPaymentResolved = false;

    /**
     * The prior approved, personal, non-deleted payment (if any) for the same
     * user whose expiry_date immediately precedes this payment's payment_date —
     * i.e. the payment this one extends. Memoized: the review page renders this
     * twice (the id and the date range).
     */
    public function extendsPayment(): ?MembershipPayment
    {
        if ($this->extendsPaymentResolved) {
            return $this->extendsPaymentCache;
        }

        $this->extendsPaymentResolved = true;

        return $this->extendsPaymentCache = static::personal()
            ->where('user_id', $this->user_id)
            ->where('is_deleted', false)
            ->where('id', '!=', $this->id)
            ->where('expiry_date', '<', $this->payment_date)
            ->orderByDesc('expiry_date')
            ->first();
    }

    /**
     * Warns the approver when this payment's fee type contradicts the member's
     * stated contribution preference (e.g. they indicated interest in
     * volunteering, but this is a non-volunteer membership fee). Precedence
     * matches profile/show.blade.php: volunteering wins if both flags are set;
     * no note when neither is set (nothing on file to contradict).
     */
    public function contributionMismatchNote(): ?string
    {
        if ($this->organisation_id !== null) {
            return null;
        }

        $wantsVolunteering = (bool) $this->user->can_contribute_volunteering;
        $wantsMember = (bool) $this->user->can_contribute_member && ! $wantsVolunteering;

        if (! $wantsVolunteering && ! $wantsMember) {
            return null;
        }

        $isVolunteerFee = (bool) $this->membershipFee->is_volunteer_fee;

        if ($wantsVolunteering && ! $isVolunteerFee) {
            return 'Note: this person indicated they want to volunteer, but this is a membership fee.';
        }

        if ($wantsMember && $isVolunteerFee) {
            return 'Note: this person indicated they want to be a member, but this is a volunteer fee.';
        }

        return null;
    }

    public function removedByUser()
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    /**
     * Get the membership fee type for this payment.
     */
    public function membershipFee()
    {
        return $this->belongsTo(MembershipFee::class, 'membership_fee_id');
    }

    /**
     * Get the branch for this payment.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the division for this payment.
     */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function certificatePrints()
    {
        return $this->hasMany(CertificatePrint::class, 'user_id', 'user_id');
    }

    /**
     * Get the payment reference in FEE-{id}/{BRANCH_CODE} format.
     */
    public function getPaymentReferenceAttribute()
    {
        $branchCode = 'UNK';

        if ($this->branch) {
            $branchCode = strtoupper($this->branch->code ?? $this->branch->name ?? 'UNK');
        }

        return "FEE-{$this->id}/{$branchCode}";
    }

    /**
     * The payment reference, wrapped as a link to this record's own page.
     */
    public function getPaymentReferenceLinkAttribute(): string
    {
        $reference = $this->payment_reference;
        $url = $this->review_or_show_url;

        return "<a href=\"{$url}\" class=\"db-code underline\">{$reference}</a>";
    }

    /**
     * Common-named alias for the shared approvals.show view, which handles
     * four different Approvable models via one $record variable.
     */
    public function getSystemReferenceAttribute(): string
    {
        return $this->payment_reference;
    }

    /**
     * Get remaining time until expiry in years, months, and days.
     */
    public function getRemainingTimeAttribute()
    {
        if (! $this->expiry_date) {
            return null;
        }

        $now = Carbon::now();
        $expiry = Carbon::parse($this->expiry_date);

        if ($expiry->isPast()) {
            return 'Expired';
        }

        $diff = $now->diff($expiry);

        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' '.($diff->y == 1 ? 'year' : 'years');
        }

        if ($diff->m > 0) {
            $parts[] = $diff->m.' '.($diff->m == 1 ? 'month' : 'months');
        }

        if ($diff->d > 0) {
            $parts[] = $diff->d.' '.($diff->d == 1 ? 'day' : 'days');
        }

        if (empty($parts)) {
            return 'Expires today';
        }

        return implode(', ', $parts).' remaining';
    }

    /**
     * Check if this is a multi-year membership fee type.
     *
     * @return bool
     */
    public function isMultiYearMembership()
    {
        return $this->validity_years > 1;
    }

    /**
     * Scope a query to only include non-deleted payments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope a query to only include deleted payments.
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    /**
     * Scope a query to only include payments that are still valid (not expired).
     */
    public function scopeValid($query)
    {
        return $query->where('expiry_date', '>=', now()->toDateString())
            ->where('is_deleted', false);
    }

    /**
     * Scope a query to only include expired payments.
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now()->toDateString())
            ->where('is_deleted', false);
    }

    /**
     * Scope a query to only include payments belonging to this user
     * personally, not an organisation.
     */
    public function scopePersonal(Builder $query): Builder
    {
        return $query->whereNull('organisation_id');
    }

    /**
     * Scope a query to only include payments attributed to an organisation.
     */
    public function scopeOrganisational(Builder $query): Builder
    {
        return $query->whereNotNull('organisation_id');
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
     * Scope a query to only include payments with ID card included.
     */
    public function scopeWithIdCard($query)
    {
        return $query->where('id_card_included', true);
    }

    /**
     * Check if this membership payment is currently valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return ! $this->is_deleted &&
               $this->expiry_date &&
               $this->expiry_date->isFuture();
    }

    /**
     * Check if this membership payment has expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Get days until expiry.
     *
     * @return int|null
     */
    public function getDaysUntilExpiryAttribute()
    {
        if (! $this->expiry_date) {
            return null;
        }

        return now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Check if membership expires within given days.
     *
     * @param  int  $days
     * @return bool
     */
    public function expiresSoon($days = 30)
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }
}
