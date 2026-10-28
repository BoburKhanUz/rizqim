<?php

namespace App\Providers;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Finance\Adjustments\AdjustmentSecondApprovalSource;
use App\Services\Finance\Payouts\PayoutSecondApprovalSource;
use Illuminate\Support\ServiceProvider;

/**
 * P4-23: the Moliya section. Its "Diqqat talab qiladi" sources: payouts and manual adjustments waiting for a second
 * member's approval (the pending payouts, top-ups and settlement failures are Bugun's own sources).
 */
class AdminFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            PayoutSecondApprovalSource::class,
            AdjustmentSecondApprovalSource::class,
        ], AttentionSource::TAG);
    }
}
