<?php

namespace App\Domain\Ops;

class InventoryRisk
{
    private int $stock;
    private float $hourlyRate;

    public function __construct(int $stock, float $hourlyRate)
    {
        $this->stock = $stock;
        $this->hourlyRate = $hourlyRate;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getHourlyRate(): float
    {
        return $this->hourlyRate;
    }

    public function getRemainingHours(): float
    {
        if ($this->hourlyRate <= 0) {
            return INF;
        }
        return $this->stock / $this->hourlyRate;
    }

    public function isCritical(): bool
    {
        $hours = $this->getRemainingHours();
        return $hours <= 1.0 || $this->stock <= 2;
    }

    public function isWarning(): bool
    {
        $hours = $this->getRemainingHours();
        return $hours > 1.0 && $hours <= 24.0;
    }

    public function getColor(): string
    {
        if ($this->stock <= 0) {
            return 'danger';
        }
        if ($this->isCritical()) {
            return 'danger';
        }
        if ($this->isWarning()) {
            return 'warning';
        }
        return 'success';
    }

    public function getForecastString(): string
    {
        if ($this->stock <= 0) {
            return 'OUT OF STOCK';
        }

        if ($this->hourlyRate <= 0) {
            return 'Stable (No recent sales)';
        }

        $hours = $this->getRemainingHours();

        if ($hours < 1.0) {
            $mins = round($hours * 60);
            return "CRITICAL: Runs out in ~{$mins} mins!";
        }

        if ($hours < 24.0) {
            $h = round($hours, 1);
            return "Will run out in ~{$h} hrs";
        }

        $days = round($hours / 24.0, 1);
        return "Will run out in ~{$days} days";
    }
}
