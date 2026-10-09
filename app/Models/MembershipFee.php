<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipFee extends Model
{
    use HasFactory;

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
        'for_red_cross_units',
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
        'for_red_cross_units' => 'boolean',
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
     * Scope a query to only include membership types for Red Cross Units.
     */
    public function scopeForRedCrossUnits($query)
    {
        return $query->where('for_red_cross_units', true);
    }

    /**
     * Scope a query to only include membership types for individuals
     * (neither organisation nor Red Cross Unit fees).
     */
    public function scopeForPersons($query)
    {
        return $query->where('for_organizations', false)
            ->where('for_red_cross_units', false);
    }

    /**
     * The personal fees an individual may choose, on the online payment page
     * and the staff payment form alike: every active personal fee, whatever
     * the person's unit or contribution preference (see Decisions.md,
     * 2026-10-09). Sorted by amount, with the 1-year and 3-year versions of
     * a fee next to each other.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function offeredToPersons(): \Illuminate\Database\Eloquent\Collection
    {
        $fees = self::query()->active()->forPersons()->get();

        // Each name sorts by its cheapest version, so "Gold 1 yr" and
        // "Gold 3 yr" stay together.
        $nameAmount = $fees->groupBy('name')->map(fn ($group) => (float) $group->min('amount'));

        return $fees->sortBy([
            fn ($a, $b) => $nameAmount[$a->name] <=> $nameAmount[$b->name],
            fn ($a, $b) => strcmp($a->name, $b->name),
            fn ($a, $b) => $a->validity_years <=> $b->validity_years,
            fn ($a, $b) => (float) $a->amount <=> (float) $b->amount,
        ])->values();
    }

    /**
     * Split personal fees into the "Member fees" and "Volunteer fees"
     * dropdown groups, volunteer fees first when $volunteerFirst (a person in
     * an active Red Cross Unit). Empty groups are left out.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $fees
     * @return array<int, array{key: string, label: string, fees: \Illuminate\Support\Collection<int, self>}>
     */
    public static function personalFeeGroups(\Illuminate\Support\Collection $fees, bool $volunteerFirst = false): array
    {
        $groups = [
            ['key' => 'member', 'label' => 'Member fees', 'fees' => $fees->where('is_volunteer_fee', false)->values()],
            ['key' => 'volunteer', 'label' => 'Volunteer fees', 'fees' => $fees->where('is_volunteer_fee', true)->values()],
        ];

        if ($volunteerFirst) {
            $groups = array_reverse($groups);
        }

        return array_values(array_filter($groups, fn ($group) => $group['fees']->isNotEmpty()));
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
     * Defaults to the supporting-member fees; pass true for the
     * volunteer-and-member fees instead.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveOneYearMemberships(bool $volunteerFees = false)
    {
        return self::query()
            ->select('name', 'amount', 'description')
            ->where('is_active', true)
            ->forPersons()
            ->where('validity_years', 1)
            ->where('is_volunteer_fee', $volunteerFees)
            ->distinct()
            ->orderBy('amount', 'desc')
            ->get();
    }

    /**
     * Distinct names of active, one-year, individual (non-organisation) fees,
     * cheapest first. One name per entry even if several rows share it.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function activeOneYearPersonFeeNames(bool $volunteerFees): \Illuminate\Support\Collection
    {
        return self::query()
            ->active()
            ->forPersons()
            ->where('validity_years', 1)
            ->where('is_volunteer_fee', $volunteerFees)
            ->select('name')
            ->selectRaw('MIN(amount) as min_amount')
            ->groupBy('name')
            ->orderBy('min_amount')
            ->orderBy('name')
            ->pluck('name')
            ->values();
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
