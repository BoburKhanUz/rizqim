<?php

namespace App\Domain\Ops;

class CourierPressure
{
    private int $activeTransitCount;

    public function __construct(int $activeTransitCount)
    {
        $this->activeTransitCount = $activeTransitCount;
    }

    public function getActiveTransitCount(): int
    {
        return $this->activeTransitCount;
    }

    public function isCritical(): bool
    {
        return $this->activeTransitCount >= 6;
    }

    public function isHigh(): bool
    {
        return $this->activeTransitCount >= 3 && $this->activeTransitCount < 6;
    }

    public function isSteady(): bool
    {
        return $this->activeTransitCount >= 1 && $this->activeTransitCount < 3;
    }

    public function getMap(): string
    {
        if ($this->isCritical()) {
            return 'Couriers: ■■■■■';
        }
        if ($this->isHigh()) {
            return 'Couriers: ■■■■□';
        }
        if ($this->isSteady()) {
            return 'Couriers: ■■□□□';
        }
        return 'Couriers: ■□□□□';
    }

    public function getColor(): string
    {
        if ($this->isCritical()) {
            return 'danger';
        }
        if ($this->isHigh()) {
            return 'warning';
        }
        return 'success';
    }

    public function getDescription(): string
    {
        if ($this->isCritical()) {
            return "🚨 Delivery bottleneck: {$this->activeTransitCount} en route. Handovers delayed.";
        }
        if ($this->isHigh()) {
            return "⚠️ High transit volume: {$this->activeTransitCount} active dispatches. Courier standby.";
        }
        if ($this->isSteady()) {
            return "{$this->activeTransitCount} en route. Flow is steady.";
        }
        return 'All couriers dispatched or ready.';
    }
}
