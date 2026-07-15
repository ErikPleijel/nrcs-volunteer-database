<?php

namespace App\Campaigns\Delivery;

final class DeliveryAttempt
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $channel,           // "email" / "sms"
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $debug = [],
    ) {}

    public static function success(string $channel, ?string $providerMessageId = null, array $debug = []): self
    {
        return new self(true, $channel, $providerMessageId, null, null, $debug);
    }

    public static function failed(string $channel, string $errorMessage, ?string $errorCode = null, array $debug = []): self
    {
        return new self(false, $channel, null, $errorCode, $errorMessage, $debug);
    }
}
