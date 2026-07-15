<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipFee extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'membership_fees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'amount',
        'id_card_fee',
        'validity_years',
        'for_organizations',
        'is_active',
        'is_volunteer_fee',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'id_card_fee' => 'decimal:2',
        'validity_years' => 'integer',
        'for_organizations' => 'boolean',
        'is_active' => 'boolean',
        'is_volunteer_fee' => 'boolean',
    ];

    /**
     * Get the membership payments for this membership fee type.
     */
    public function membershipPayments()
    {
        return $this->hasMany(MembershipPayment::class);
    }

    /**
     * Scope a query to only include active membership fee types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive membership fee types.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include membership types for organizations.
     */
    public function scopeForOrganizations($query)
    {
        return $query->where('for_organizations', true);
    }

    /**
     * Scope a query to only include membership types for individuals.
     */
    public function scopeForPersons($query)
    {
        return $query->where('for_organizations', false);
    }

    /**
     * Get the total fee including ID card fee.
     *
     * @return float
     */
    public function getTotalFeeAttribute()
    {
        return $this->amount + $this->id_card_fee;
    }

    /**
     * Check if this membership type is currently available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->is_active;
    }

    /**
     * Get an array of active one-year memberships with name and amount.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveOneYearMemberships()
    {
        return self::query()
            ->select('name', 'amount', 'description')
            ->where('is_active', true)
            ->where('validity_years', 1)
            ->where('is_volunteer_fee', false)
            ->distinct()
            ->orderBy('amount', 'desc')
            ->get();
    }



    /**
     * Get the 33rd-percentile amount among active, one-year, non-volunteer person fees.
     *
     * @return float
     */
    public static function highValueFeeThreshold(): float
    {
        $amounts = self::query()
            ->active()
            ->forPersons()
            ->where('is_volunteer_fee', false)
            ->where('validity_years', 1)
            ->pluck('amount')
            ->sort()
            ->values();

        $count = $amounts->count();
        if ($count === 0) {
            return 0;
        }

        if ($count === 1) {
            return $amounts[0];
        }

        // 33rd percentile, linear interpolation between the two nearest values
        $rank = 0.33 * ($count - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);
        $fraction = $rank - $lowerIndex;

        if ($lowerIndex === $upperIndex) {
            return $amounts[$lowerIndex];
        }

        return $amounts[$lowerIndex] + $fraction * ($amounts[$upperIndex] - $amounts[$lowerIndex]);
    }

    /**
     * Get the maximum validity years for active memberships.
     *
     * @return int
     */
    public static function getMaxValidityYears()
    {
        return self::query()
            ->where('is_active', true)
            ->max('validity_years');
    }


    /**
     * Scope a query to only include active individual membership fee types.
     */
    public function scopeActivePersonFeeNames($query)
    {
        return $query
            ->active()
            ->forPersons()
            ->select('name')
            ->selectRaw('MAX(amount) as max_amount')
            ->groupBy('name')
            ->orderBy('max_amount', 'asc')   // most expensive LAST
            ->pluck('name')
            ->values();
    }
}
