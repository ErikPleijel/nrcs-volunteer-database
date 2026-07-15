<?php

namespace App\Campaigns\Delivery;

use App\Models\MessagingRecipient;

interface DeliveryChannel
{
    /**
     * e.g. "email" or "sms"
     */
    public function name(): string;

    /**
     * Can this channel deliver for this recipient? (e.g. has email/phone, not opted out, etc.)
     */
    public function supports(MessagingRecipient $recipient): bool;

    /**
     * Attempt a delivery. Never throw for normal failures — return DeliveryAttempt.
     * Throw only for truly unexpected/systemic errors if you prefer to fail-fast.
     */
    public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt;
}
