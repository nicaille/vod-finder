<?php

namespace App\Services;

/** A shared limit across notification channels for short HTTP-triggered batches. */
class NotificationDeliveryBudget
{
    private int $attempts = 0;
    private float $startedAt;

    public function __construct(private int $maximumAttempts, private float $seconds = 20)
    {
        $this->startedAt = hrtime(true) / 1e9;
    }

    public function available(): bool
    {
        return $this->attempts < $this->maximumAttempts && hrtime(true) / 1e9 - $this->startedAt < $this->seconds;
    }

    public function acquire(): bool
    {
        if (!$this->available()) return false;
        $this->attempts++;
        return true;
    }
}
