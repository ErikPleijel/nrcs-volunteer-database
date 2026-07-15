<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'zone',
        'is_active',
        'physical_address',
        'postal_address',
        'telephone',
        'email',
        'projects',
        'latitude',
        'longitude',

        // Public contact users + positions
        'public_contact_user_id_1',
        'public_contact_user_id_2',
        'public_contact_user_id_3',
        'public_contact_user_id_4',
        'public_contact_user_id_5',
        'public_contact_user_id_6',

        'public_contact_position_1',
        'public_contact_position_2',
        'public_contact_position_3',
        'public_contact_position_4',
        'public_contact_position_5',
        'public_contact_position_6',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Contact person relations (6 slots)
     */
    public function publicContactUser1() { return $this->belongsTo(User::class, 'public_contact_user_id_1'); }
    public function publicContactUser2() { return $this->belongsTo(User::class, 'public_contact_user_id_2'); }
    public function publicContactUser3() { return $this->belongsTo(User::class, 'public_contact_user_id_3'); }
    public function publicContactUser4() { return $this->belongsTo(User::class, 'public_contact_user_id_4'); }
    public function publicContactUser5() { return $this->belongsTo(User::class, 'public_contact_user_id_5'); }
    public function publicContactUser6() { return $this->belongsTo(User::class, 'public_contact_user_id_6'); }

    /**
     * Helper: return all contacts in a clean array
     */
    public function publicContacts()
    {
        $contacts = [];

        for ($i = 1; $i <= 6; $i++) {
            $user = $this->{"publicContactUser$i"}()->first();
            $position = $this->{"public_contact_position_$i"};

            if ($user) {
                $contacts[] = [
                    'user'     => $user,
                    'position' => $position,
                ];
            }
        }

        return $contacts;
    }

    public function getPublicContactsCountAttribute(): int
    {
        $count = 0;
        for ($i = 1; $i <= 6; $i++) {
            if (!empty($this->{"public_contact_user_id_$i"})) {
                $count++;
            }
        }
        return $count;
    }

    // --- Your existing relations and methods ---

    public function divisions() { return $this->hasMany(Division::class); }

    public function users() { return $this->hasMany(User::class); }

    public function donations() { return $this->hasMany(Donation::class); }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function scopeWithCoordinates($query)
    {
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude');
    }

    public function usersInDivisions()
    {
        return User::whereIn('division_id', $this->divisions()->pluck('id'));
    }

    public function getTotalDonationAmountAttribute()
    {
        return $this->donations()->sum('amount') ?? 0;
    }

    public function getDonationCountAttribute()
    {
        return $this->donations()->count();
    }

    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function getFormattedAddressAttribute(): string
    {
        return $this->physical_address ?: ($this->postal_address ?: 'Address not available');
    }

    public function getBranchCodeForReference(): string
    {
        if (!empty($this->code)) {
            return $this->code;
        }

        if (empty($this->name)) {
            return 'UNK';
        }

        $formatted = strtoupper(str_replace(' ', '', $this->name));
        return substr($formatted, 0, 10);
    }
}
