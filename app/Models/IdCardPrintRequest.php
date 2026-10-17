<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Mark as ready to print": branch/division staff who cannot print flag a
 * complete card for the national printers. One pending request per user at a
 * time (enforced in IdCardController). Closed as 'printed' by Mark as Printed,
 * or 'cancelled' when removed or when a photo/signature is rejected.
 */
class IdCardPrintRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CANCEL_REMOVED = 'removed';

    public const CANCEL_IMAGE_REJECTED = 'image_rejected';

    protected $fillable = [
        'user_id',
        'requested_by_user_id',
        'requested_at',
        'status',
        'cancel_reason',
        'id_card_print_id',
        'closed_by_user_id',
        'closed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function idCardPrint()
    {
        return $this->belongsTo(IdCardPrint::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
