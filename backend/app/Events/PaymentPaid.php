<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $replay  a provider's repeated completion of a payment already committed (Click's Complete, Payme's
     *                        PerformTransaction, Paylov's read-back): how a late settlement lost earlier catches up
     *                        (SettlementRecoveryService records it as the catch-up's reason)
     */
    public function __construct(
        public Payment $payment,
        public bool $replay = false,
    ) {}
}
