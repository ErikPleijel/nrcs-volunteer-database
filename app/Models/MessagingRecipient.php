<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /** A queued row older than this was claimed by a runner that never recorded a result. */
    public const STUCK_QUEUED_MINUTES = 15;

    /**
     * last_error for stuck rows an admin marked failed. "Reset failed" skips rows with this
     * error: the message may already have gone out, so retrying could double-send.
     */
    public const STUCK_MARKED_FAILED_ERROR = 'Outcome unknown: stuck in queued, marked failed by an admin. The message may have been sent, so it is not retried.';

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
     * Rows claimed by the send runner more than STUCK_QUEUED_MINUTES ago with no result.
     */
    public function scopeStuckQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued')
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_QUEUED_MINUTES));
    }

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
