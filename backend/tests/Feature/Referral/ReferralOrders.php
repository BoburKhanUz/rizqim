<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Support\Str;

/**
 * Owner decision 3: a referred customer's orders paid in each way the referral rule tells apart, delivered by a courier,
 * and what the referral bonus rows say (over OrderLifecycleFixtures; $referrer invited $customer).
 */
trait ReferralOrders
{
    private User $referrer;

    /**
     * An order on its way, paid as $how says, delivered by the courier.
     *
     * @param  'cash'|'platform_click'|'platform_payme'|'store_click'|'store_payme'|'store_uzum'|'store_paynet'|'store_paylov'|'platform_paylov'|'store_paylov_unconfirmed'|'platform_click_unpaid'|'keshbek'  $how
     */
    private function deliveredOrder(string $how, array $attributes = [], ?Store $store = null): Order
    {
        $order = $this->paidOrder($how, $attributes, $store);
        $courier = $store === null ? $this->courier : $this->makeStaff(\App\Enums\UserRole::COURIER, null, $store);
        $order->forceFill(['courier_id' => $courier->id])->saveQuietly();
        $this->assertTrue(app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, $courier)->changed);

        return $order->fresh();
    }

    private function paidOrder(string $how, array $attributes = [], ?Store $store = null): Order
    {
        [$owner, $method] = match ($how) {
            'cash' => ['STORE', 'cash'],
            'keshbek' => ['PLATFORM', 'keshbek'],
            'platform_click', 'platform_click_unpaid' => ['PLATFORM', 'click'],
            'platform_payme' => ['PLATFORM', 'payme'],
            'platform_paylov' => ['PLATFORM', 'paylov'],
            'store_click' => ['STORE', 'click'],
            'store_payme' => ['STORE', 'payme'],
            'store_uzum' => ['STORE', 'uzum'],
            'store_paynet' => ['STORE', 'paynet'],
            'store_paylov', 'store_paylov_unconfirmed' => ['STORE', 'paylov'],
        };
        $paid = $method !== 'cash' && $how !== 'platform_click_unpaid';
        $store ??= $this->store;
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, array_merge([
            'store_id' => $store->id,
            'branch_id' => $store->is($this->store) ? $this->branch->id : $this->makeBranch($store, 'Filial ' . Str::random(4))->id,
            'payment_method' => $method,
            'payment_owner' => $owner,
            'payment_status' => $paid ? PaymentStatus::PAID->value : PaymentStatus::PENDING->value,
        ], $attributes));
        if ($method !== 'cash') {
            $payload = match ($how) {
                'store_paylov', 'platform_paylov' => ['paylov' => ['state' => PaylovPayments::STATE_CONFIRMED, 'confirmed_at' => now()->toIso8601String(), 'refused' => null]],
                'store_paylov_unconfirmed' => ['paylov' => ['state' => PaylovPayments::STATE_UNCONFIRMED]],
                default => null,
            };
            Payment::create([
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'merchant_scope' => Payment::merchantScopeOf($order),
                'provider' => $method,
                'provider_transaction_id' => $method === 'keshbek' ? null : 'txn-' . Str::random(10),
                'amount' => $order->total_price,
                'status' => $paid ? PaymentStatus::PAID->value : PaymentStatus::PENDING->value,
                'paid_at' => $paid ? now() : null,
                'payload' => $payload,
            ]);
        }

        return $order->fresh();
    }

    /**
     * @return list<array{order_id: int, store_id: int, amount: string}>
     */
    private function rewards(): array
    {
        return \App\Support\Tenancy\TenantRls::asApp(fn () => StoreTransaction::query()->withoutGlobalScopes()
            ->where('type', StoreTransactionType::REFERRAL_BONUS->value)
            ->orderBy('id')->get(['order_id', 'store_id', 'amount'])
            ->map(fn (StoreTransaction $row) => ['order_id' => (int) $row->order_id, 'store_id' => (int) $row->store_id, 'amount' => (string) $row->amount])->all());
    }

    private function referrerBalance(): float
    {
        return (float) $this->referrer->fresh()->balance;
    }
}
