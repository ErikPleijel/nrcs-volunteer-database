<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class MessagingRecipient extends Model
{
    protected $table = 'messaging_recipients';

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
