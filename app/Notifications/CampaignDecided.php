<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class CampaignDecided extends Notification
{
    public function __construct(
        protected string $decision,
        protected int    $campaignId,
        protected string $campaignTitle,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = match ($this->decision) {
            'approved'  => "Your campaign \"{$this->campaignTitle}\" was approved and is ready to be sent.",
            'rejected'  => "Your campaign \"{$this->campaignTitle}\" was rejected." . ($this->reason ? " Reason: {$this->reason}" : ''),
            'cancelled' => "Your campaign \"{$this->campaignTitle}\" was cancelled before it was sent.",
            default     => "Your campaign \"{$this->campaignTitle}\" status changed.",
        };

        return [
            'type'           => 'campaign_decided',
            'decision'       => $this->decision,
            'campaign_id'    => $this->campaignId,
            'campaign_title' => $this->campaignTitle,
            'reason'         => $this->reason,
            'message'        => $message,
        ];
    }
}
