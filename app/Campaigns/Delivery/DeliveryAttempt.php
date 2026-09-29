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
        public readonly ?string $provider = null,   // e.g. "log-only", "smslive247"
    ) {}

    public static function success(string $channel, ?string $providerMessageId = null, array $debug = [], ?string $provider = null): self
    {
        return new self(true, $channel, $providerMessageId, null, null, $debug, $provider);
    }

    public static function failed(string $channel, string $errorMessage, ?string $errorCode = null, array $debug = [], ?string $provider = null): self
    {
        return new self(false, $channel, null, $errorCode, $errorMessage, $debug, $provider);
    }
}
