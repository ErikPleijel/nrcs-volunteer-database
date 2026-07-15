<?php

namespace App\Campaigns\Delivery;

final class DeliveryOutcome
{
    /** @var DeliveryAttempt[] */
    public readonly array $attempts;

    public function __construct(array $attempts)
    {
        $this->attempts = $attempts;
    }

    /**
     * Define success for a "both" recipient:
     * - If wanted is "both", you may want "at least one ok" OR "all ok".
     * Pick your rule.
     */
    public function okAtLeastOne(): bool
    {
        foreach ($this->attempts as $a) {
            if ($a->ok) return true;
        }
        return false;
    }

    public function okAll(): bool
    {
        if (count($this->attempts) === 0) return false;
        foreach ($this->attempts as $a) {
            if (!$a->ok) return false;
        }
        return true;
    }

    public function firstFailure(): ?DeliveryAttempt
    {
        foreach ($this->attempts as $a) {
            if (!$a->ok) return $a;
        }
        return null;
    }
}
