<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignPurpose extends Model
{
    /**
     * Default trailing-day window used when checking "has this person been
     * contacted for this purpose recently" (wizard's campaign_msg|days param
     * and campaign-planning reports' contact-count queries).
     */
    const CONTACT_WINDOW_DAYS = 180;

    protected $table = 'campaign_purposes';

    protected $fillable = [
        'name',
        'slug',
        'default_subject',
        'default_email_body',
        'default_sms_body',
        'default_channel',
        'default_call_window',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'default_call_window' => 'boolean',
        'sort_order'          => 'integer',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(MessagingCampaign::class, 'purpose_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->default_channel) {
            'email_fallback_sms' => 'Email (fallback to SMS)',
            'email'              => 'Email only',
            'sms'                => 'SMS only',
            'both'               => 'Email and SMS (both)',
            default              => ucfirst((string) $this->default_channel),
        };
    }
}
