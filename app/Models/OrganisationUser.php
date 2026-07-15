<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganisationUser extends Pivot
{
    protected $table = 'organisation_user';

    protected $fillable = [
        'organisation_id',
        'user_id',
        'is_primary_contact',
        'linked_at',
        'linked_by',
    ];

    protected $casts = [
        'is_primary_contact' => 'boolean',
        'linked_at'          => 'datetime',
    ];

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
