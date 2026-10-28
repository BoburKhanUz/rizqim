<?php

namespace Tests\Feature\BillingV2;

use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\TariffPlan;
use App\Services\Finance\SubscriptionService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Tests\Feature\BillingFixtures;

/**
 * P4-21: the v2 catalog plans, commission booked on the ledger at a given moment, and the billing service.
 */
trait BillingV2Fixtures
{
    use BillingFixtures;

    protected function v2(string $code): TariffPlan
    {
        return TariffPlan::where('code', $code)->sole();
    }

    /**
     * The catalog without its 14-day trial, for the tests about billed months.
     */
    protected function withoutTrials(): void
    {
        TariffPlan::query()->where('version', 2)->update(['trial_days' => 0]);
    }

    protected function billing(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    /**
     * A commission booked on the store's ledger at a Tashkent moment (debt or wallet), optionally for an order.
     */
    protected function bookCommission(Store $store, string $amount, string $at, bool $fromWallet = false, ?Order $order = null, ?string $notes = null): void
    {
        $attributes = [
            'store_id' => $store->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => $fromWallet ? Money::of($amount)->negated()->toString() : Money::of($amount)->toString(),
            'balance_before' => '0.00',
            'balance_after' => '0.00',
            'order_id' => $order?->id,
            'notes' => $notes ?? 'Commission deduction',
        ];
        $row = $fromWallet ? new StoreWalletTransaction() : new StoreTransaction();
        $row->forceFill($attributes + ['created_at' => $this->tashkent($at), 'updated_at' => $this->tashkent($at)]);
        $row->timestamps = false;
        $row->save();
    }

    protected function v2Store(string $name = 'V2 Cafe', array $attributes = []): Store
    {
        return $this->billingStore($name, $attributes + ['commission_rate' => null]);
    }

    protected function at(string $time): Carbon
    {
        return $this->tashkent($time);
    }
}
