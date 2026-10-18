<?php

namespace App\Domain\Ops;

class RushWindow
{
    private int $hour;

    public function __construct(?int $hour = null)
    {
        $this->hour = $hour ?? (int) now()->format('H');
    }

    public function isLunchPeak(): bool
    {
        return $this->hour >= 12 && $this->hour <= 14;
    }

    public function isDinnerPeak(): bool
    {
        return $this->hour >= 18 && $this->hour <= 21;
    }

    public function isLateNight(): bool
    {
        return $this->hour >= 22 || $this->hour <= 2;
    }

    public function isPeak(): bool
    {
        return $this->isLunchPeak() || $this->isDinnerPeak();
    }

    public function getAcceptDelayThreshold(): int
    {
        return $this->isPeak() ? 8 : 5;
    }

    public function getPrepDelayThreshold(): int
    {
        return $this->isPeak() ? 20 : 12;
    }

    public function getLabel(): string
    {
        if ($this->isLunchPeak()) {
            return 'Lunch Rush 🍔';
        }
        if ($this->isDinnerPeak()) {
            return 'Dinner Peak 🍕';
        }
        if ($this->isLateNight()) {
            return 'Late Night Ops 🌙';
        }
        return 'Standard Off-Peak 🟢';
    }
}
