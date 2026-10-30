<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Support\ServiceProvider;

/**
 * P6-22: tips (choy puli) — the bootstrap's `tips` section. Tips are taken by PaymentService, shown on the X/Z
 * (ShiftTips), split by TipPoolDistributor and reported by TipsReport.
 */
class PosTipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([TipsBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }
}
