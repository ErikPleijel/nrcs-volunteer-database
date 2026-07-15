<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import the SoftDeletes trait

class IdCardPrint extends Model
{
    use HasFactory, SoftDeletes; // Use the SoftDeletes trait

    protected $fillable = [
        'user_id',
        'printed_by_user_id',
        'printed_at',
        'status',
        'validity_months',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    // Define relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
