<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'registration_number',
        'address',
        'description',
        'email',
        'phone',
        'branch_id',
        'deactivated_date',
        'deactivated_by_id',
    ];

    protected $casts = [
        'deactivated_date' => 'date',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withTrashed()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user', 'organisation_id', 'user_id')
            ->using(OrganisationUser::class)
            ->withPivot(['is_primary_contact', 'linked_at', 'linked_by'])
            ->withTimestamps();
    }

    public function primaryContact(): ?User
    {
        return $this->users()
            ->wherePivot('is_primary_contact', true)
            ->first();
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'organisation_id')
            ->where('is_deleted', false);
    }

    public function totalDonated(): int
    {
        return (int) $this->donations()
            ->where('in_kind_donation', false)
            ->sum('amount');
    }

    public function membershipPayments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class, 'organisation_id');
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(MembershipPayment::class, 'organisation_id')
            ->where('is_deleted', false)
            ->where('expiry_date', '>=', now())
            ->latest('expiry_date');
    }

    public function latestMembership(): HasOne
    {
        return $this->hasOne(MembershipPayment::class, 'organisation_id')
            ->where('is_deleted', false)
            ->latest('expiry_date');
    }

    public function isMember(): bool
    {
        return (bool) $this->activeMembership()->first();
    }

    public function getOrgReferenceAttribute(): string
    {
        if ($this->branch?->code) {
            return 'ORG-' . $this->id . '-' . strtoupper($this->branch->code);
        }
        return 'ORG-' . $this->id;
    }

    public function getMembershipExpiryDateAttribute()
    {
        return $this->activeMembership()->first()?->expiry_date;
    }

    public function certificatePrints(): HasMany
    {
        return $this->hasMany(CertificatePrint::class, 'organisation_id');
    }

    public function campaignRecipients(): MorphMany
    {
        return $this->morphMany(MessagingRecipient::class, 'recipient');
    }
}
