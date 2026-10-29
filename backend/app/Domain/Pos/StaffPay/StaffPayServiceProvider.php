<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Support\ServiceProvider;

/**
 * P6-23: staff pay — the tips source the `tips` component reads and the bootstrap's `staff_pay` section.
 */
class StaffPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(StaffTipsSource::class, PosTipsStaffTips::class);
        $this->app->tag([StaffPayBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }
}
