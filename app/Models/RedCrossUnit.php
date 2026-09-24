<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class RedCrossUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'division_id',
        'team_leader_user_id',
        'assistant_team_leader_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Opaque id_check_token for the membership certificate's QR verification
     * link — same generation as User's.
     */
    protected static function booted()
    {
        static::creating(function ($unit) {
            if (empty($unit->id_check_token)) {
                $unit->id_check_token = Str::random(32);
            }
        });
    }

    public function certificatePrints(): HasMany
    {
        return $this->hasMany(CertificatePrint::class, 'red_cross_unit_id');
    }

    /**
     * Human-readable reference, RCU-{id}/{BRANCH} — same id grouping and
     * branch-code fallback as MembershipPayment::payment_reference.
     * Display-only; never use for lookups.
     */
    public function getRcuReferenceAttribute(): string
    {
        $branch = $this->division?->branch;
        $branchCode = strtoupper($branch->code ?? $branch->name ?? 'UNK');

        return 'RCU-'.number_format($this->id, 0, '.', "\u{2009}").'/'.$branchCode;
    }

    /**
     * A red cross unit belongs to a division
     */
    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * A red cross unit has a team leader (user)
     */
    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_user_id');
    }

    /**
     * A red cross unit has an assistant team leader (user)
     */
    public function assistantTeamLeader()
    {
        return $this->belongsTo(User::class, 'assistant_team_leader_user_id');
    }

    /**
     * A red cross unit has many users
     */
    public function users()
    {
        return $this->hasMany(User::class, 'red_cross_unit_id');
    }

    /**
     * A red cross unit has many users, excluding archived ones. Use this
     * instead of users() for member lists/counts shown in the UI.
     */
    public function activeUsers()
    {
        return $this->hasMany(User::class, 'red_cross_unit_id')
            ->where('lifecycle_status', '!=', 'archived');
    }

    /**
     * Scope for active red cross units
     */
    public function scopeActive($query)
    {
        return $query->where('red_cross_units.is_active', true);
    }

    /**
     * Scope for red cross units with team leaders
     */
    public function scopeWithTeamLeader($query)
    {
        return $query->whereNotNull('team_leader_user_id');
    }

    /**
     * Scope for red cross units with assistant team leaders
     */
    public function scopeWithAssistantTeamLeader($query)
    {
        return $query->whereNotNull('assistant_team_leader_user_id');
    }

    /**
     * Get the count of users in this red cross unit
     */
    public function getUsersCountAttribute()
    {
        return $this->activeUsers()->count();
    }

    /**
     * Whether the given user is this unit's team leader or assistant team
     * leader — the people allowed to pay the unit's annual fee.
     */
    public function isLedBy(User $user): bool
    {
        return in_array((int) $user->id, array_map('intval', array_filter([
            $this->team_leader_user_id,
            $this->assistant_team_leader_user_id,
        ])), true);
    }

    /**
     * Annual fee payments attributed to this unit (mirrors
     * Organisation::membershipPayments()).
     */
    public function membershipPayments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class, 'red_cross_unit_id');
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(MembershipPayment::class, 'red_cross_unit_id')
            ->where('is_deleted', false)
            ->where('expiry_date', '>=', now())
            ->latest('expiry_date');
    }

    public function latestMembership(): HasOne
    {
        return $this->hasOne(MembershipPayment::class, 'red_cross_unit_id')
            ->where('is_deleted', false)
            ->latest('expiry_date');
    }

    public function isPaid(): bool
    {
        return (bool) $this->activeMembership()->first();
    }

    public function getMembershipExpiryDateAttribute()
    {
        return $this->activeMembership()->first()?->expiry_date;
    }

    /**
     * Check if the unit has leadership assigned
     */
    public function hasLeadership()
    {
        return !is_null($this->team_leader_user_id) || !is_null($this->assistant_team_leader_user_id);
    }

    /**
     * Get the unit's branch through division
     */
    public function branch()
    {
        return $this->hasOneThrough(Branch::class, Division::class, 'id', 'id', 'division_id', 'branch_id');
    }

    /**
     * Check if a given user is authorized to view this Red Cross Unit.
     *
     * @param \App\Models\User $user The user to check.
     * @return bool
     */
    public function isViewableBy(User $user): bool
    {
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        switch ($accessLevel) {
            case 'national':
                // National admins can view all units.
                return true;

            case 'branch':
                // Branch admins can view units within their branch.
                // This requires checking the branch_id of the unit's division.
                return $this->division && $this->division->branch_id == $scopedId;

            case 'division':
                // Division admins can view units within their division.
                return $this->division_id == $scopedId;

            default:
                // Regular users or other roles cannot view units directly through this logic.
                return false;
        }
    }
}
