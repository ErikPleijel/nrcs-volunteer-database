<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{


    protected $fillable = [
        'name',
        'branch_id',
        'physical_address',
        'postal_address',
        'telephone',
        'email',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    /**
     * A division belongs to a branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * A division has many red cross units
     */
    public function redCrossUnits()
    {
        return $this->hasMany(RedCrossUnit::class);
    }
}
