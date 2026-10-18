<?php

namespace App\Domain\Ops;

class KitchenState
{
    private int $activePrepCount;

    public function __construct(int $activePrepCount)
    {
        $this->activePrepCount = $activePrepCount;
    }

    public function isCritical(): bool
    {
        return $this->activePrepCount >= 10;
    }

    public function isHigh(): bool
    {
        return $this->activePrepCount >= 6 && $this->activePrepCount < 10;
    }

    public function isSteady(): bool
    {
        return $this->activePrepCount >= 3 && $this->activePrepCount < 6;
    }

    public function isLight(): bool
    {
        return $this->activePrepCount >= 1 && $this->activePrepCount < 3;
    }

    public function getActivePrepCount(): int
    {
        return $this->activePrepCount;
    }

    public function getOnlineChefsNeeded(): int
    {
        return max(1, (int) ceil($this->activePrepCount / 3.0));
    }

    public function getMap(): string
    {
        if ($this->isCritical()) {
            return 'Kitchen: ■■■■■';
        }
        if ($this->isHigh()) {
            return 'Kitchen: ■■■■□';
        }
        if ($this->isSteady()) {
            return 'Kitchen: ■■■□□';
        }
        if ($this->isLight()) {
            return 'Kitchen: ■■□□□';
        }
        return 'Kitchen: ■□□□□';
    }

    public function getColor(): string
    {
        if ($this->isCritical() || $this->isHigh()) {
            return 'danger';
        }
        if ($this->isSteady()) {
            return 'warning';
        }
        return 'success';
    }

    public function getDescription(): string
    {
        if ($this->isCritical()) {
            return "🚨 CRITICAL OVERLOAD: {$this->activePrepCount} active preps. Speed is vital.";
        }
        if ($this->isHigh()) {
            return "⚠️ Peak spike: {$this->activePrepCount} active preps. Speed is vital.";
        }
        if ($this->isSteady()) {
            return "Steady rush: {$this->activePrepCount} active preps. Managing fine.";
        }
        if ($this->isLight()) {
            return "Light prep load: {$this->activePrepCount} active. Flow is steady.";
        }
        return '3 active chefs. Prep pace is normal.';
    }
}
