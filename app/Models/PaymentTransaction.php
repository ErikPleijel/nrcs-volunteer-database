<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organisation_id',
        'payable_type',
        'reference',
        'amount',
        'status',
        'meta',
        'raw_payload',
        'donation_id',
        'membership_payment_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'raw_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function membershipPayment(): BelongsTo
    {
        return $this->belongsTo(MembershipPayment::class);
    }
}
