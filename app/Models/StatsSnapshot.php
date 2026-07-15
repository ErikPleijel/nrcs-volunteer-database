<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatsSnapshot extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $casts = [
        'snapshot_date'            => 'date',
        'is_backfilled'            => 'boolean',
        'dormant_avg_days_inactive' => 'float',
        'created_at'               => 'datetime',
    ];
}
