<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Filters\UserFilterDescriber;

class MessagingCampaign extends Model
{


    protected $table = 'messaging_campaigns';

    protected $fillable = [
        // Core
        'channel',
        'audience_type',
        'purpose_id',
        'title',
        'subject',
        'body',
        'filter_json',

        // Sender identity
        'from_email',
        'from_name',
        'from_phone',

        // Governance + scope
        'scope_level',
        'scope_id',
        'lifecycle',

        // Origin (durable creation-time snapshot, independent of scope_level/scope_id)
        'origin_level',
        'origin_branch_id',

        // Submission + approval workflow
        'submitted_at',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejected_by',
        'review_note',

        // Status + stats
        'status',
        'stats_total',
        'stats_sent',
        'stats_failed',

        // Ownership
        'created_by',

        'send_started_at',
        'send_completed_at',
        'last_send_run_at',
        'daily_sent_count',
        'daily_sent_date',

        'reply_to_email',
    ];

    protected $casts = [
        'filter_json'   => 'array',
        'submitted_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',

        'send_started_at' => 'datetime',
        'send_completed_at' => 'datetime',
        'last_send_run_at' => 'datetime',
        'daily_sent_date' => 'date',

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function recipients(): HasMany
    {
        return $this->hasMany(MessagingRecipient::class, 'messaging_campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function originBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'origin_branch_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function purpose(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CampaignPurpose::class, 'purpose_id');
    }

    public function getFilterDescriptionHtmlAttribute(): string
    {
        $filters = is_array($this->filter_json) ? $this->filter_json : [];
        return UserFilterDescriber::description($filters);
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience accessors / helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Returns the user who reviewed the campaign:
     * - approver if approved
     * - rejector if rejected
     * - null otherwise
     */
    public function getReviewedByUserAttribute(): ?User
    {
        if ($this->status === 'approved') {
            return $this->approver;
        }

        if ($this->status === 'rejected') {
            return $this->rejector;
        }

        return null;
    }

    /**
     * Returns the review timestamp:
     * - approved_at if approved
     * - rejected_at if rejected
     * - null otherwise
     */
    public function getReviewedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if ($this->status === 'approved') {
            return $this->approved_at;
        }

        if ($this->status === 'rejected') {
            return $this->rejected_at;
        }

        return null;
    }

    /**
     * Current V1 throttling settings stored inside filter_json meta.
     */
    public function getThrottlingAttribute(): array
    {
        $filter = is_array($this->filter_json) ? $this->filter_json : [];
        return $filter['_throttling'] ?? [];
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'email_fallback_sms' => 'Email (fallback to SMS)',
            'email' => 'Email only',
            'sms' => 'SMS only',
            'both' => 'Email and SMS (both)',
            default => ucfirst((string)$this->channel),
        };
    }

    public function getCodeAttribute(): string
    {
        if ($this->origin_level === 'national') {
            return 'CAMP-' . $this->id . '-NAT';
        }
        if ($this->origin_level === 'branch' && $this->originBranch?->code) {
            return 'CAMP-' . $this->id . '-' . $this->originBranch->code;
        }
        return 'CAMP-' . $this->id;
    }
}
