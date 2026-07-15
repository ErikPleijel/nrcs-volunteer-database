<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificatePrint extends Model
{
    use SoftDeletes;

    protected $table = 'certificates_print';

    protected $fillable = [
        'user_id',
        'organisation_id',
        'training_id',
        'printed_by_user_id',
        'certificate_type',
        'printed_at',
        'notes',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    // --- Relationships ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
