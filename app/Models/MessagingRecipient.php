<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class MessagingRecipient extends Model
{
    protected $table = 'messaging_recipients';

    /** Counted as sent in campaign stats. */
    public const SENT_STATUSES = ['sent', 'delivered'];

    /** Counted as failed in campaign stats. */
    public const FAILED_STATUSES = ['failed', 'bounced', 'undeliverable', 'expired'];

    /** Deliberately not sent — neither sent nor failed. */
    public const SKIPPED_STATUSES = ['skipped_shared_number', 'skipped_invalid_number'];

    protected $fillable = [
        'messaging_campaign_id',
        'recipient_type',
        'recipient_id',
        'email',
        'phone',
        'payload_json',
        'status',
        'last_error',
        'sent_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the campaign that owns the messaging recipient.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessagingCampaign::class, 'messaging_campaign_id');
    }

    /**
     * Get the parent recipient model (e.g., User).
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
