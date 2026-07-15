<?php

namespace App\Campaigns\Delivery;

final class DeliveryMessage
{
    public function __construct(
        public readonly ?string $subject,
        public readonly string $body,
        public readonly ?string $emailBody = null,
        public readonly ?string $smsBody = null,
        public readonly array $meta = [],
    ) {}
}
