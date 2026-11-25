<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Auth\ReferralService;
use App\Services\Finance\LedgerService;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Owner decision 3: the referral bonus (5 000 ball to the referrer, its cost charged to the store of the qualifying
 * order, as before) is granted only for an order whose payment the platform itself verified
 * (ReferralService::paymentVerifiedByPlatform): an online payment into the platform's own merchant account, or a
 * Paylov payment (the store's own account too) settled on Paylov's read-back. Cash and store-owned Click / Payme /
 * Uzum / Paynet never earn it, and never use the referral up: a later verified order still earns it, exactly once.
 *
 * Numbers: 50 000 so'm orders, commission 5 000 (10 %).
 */
class ReferralVerifiedPaymentTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private User $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['credit_limit' => -1000000])->save();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
    }

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

    public function test_which_payments_the_platform_verified(): void
    {
        $expected = [
            'cash' => false,
            'store_click' => false,
            'store_payme' => false,
            'store_uzum' => false,
            'store_paynet' => false,
            'store_paylov_unconfirmed' => false,
            'platform_click_unpaid' => false,
            'platform_click' => true,
            'platform_payme' => true,
            'platform_paylov' => true,
            'store_paylov' => true,
            'keshbek' => true,
        ];

        foreach ($expected as $how => $verified) {
            $this->assertSame($verified, ReferralService::paymentVerifiedByPlatform($this->paidOrder($how)), $how);
        }
    }

    public function test_a_paylov_payment_refused_at_perform_is_not_the_orders(): void
    {
        $order = $this->paidOrder('store_paylov');
        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->forceFill(['payload' => ['paylov' => ['state' => PaylovPayments::STATE_CONFIRMED, 'refused' => 'order_already_paid']]])->save();

        $this->assertFalse(ReferralService::paymentVerifiedByPlatform($order->fresh()));
    }

    public function test_cash_and_store_owned_click_payme_uzum_paynet_orders_earn_no_bonus(): void
    {
        foreach (['cash', 'store_click', 'store_payme', 'store_uzum', 'store_paynet', 'store_paylov_unconfirmed'] as $how) {
            $this->deliveredOrder($how);
        }

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
        // Only the commissions: 6 × 5 000
        $this->assertSame('30000.00', (string) $this->store->fresh()->debt_balance);
    }

    public function test_a_later_verified_order_still_earns_the_bonus_once_after_unverified_ones(): void
    {
        $this->deliveredOrder('cash');
        $this->deliveredOrder('store_click');
        $this->assertSame([], $this->rewards());

        $paylov = $this->deliveredOrder('store_paylov');

        // Recorded on the verified order's store ledger, paid by the platform: the debt is the 3 commissions only
        $this->assertSame([['order_id' => $paylov->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertSame('15000.00', (string) $this->store->fresh()->debt_balance);

        // The next verified one earns nothing: once per referred customer
        $this->deliveredOrder('store_paylov');
        $this->deliveredOrder('platform_click');
        $this->assertCount(1, $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
    }

    public function test_a_platform_collected_order_earns_it_and_the_platform_pays(): void
    {
        $order = $this->deliveredOrder('platform_payme');

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        // P4-23 netting: the 50 000 collected paid the 5 000 commission, 45 000 reached the wallet; no bonus on the store
        $this->assertSame(['0.00', '45000.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
    }

    /**
     * Owner decision 3's addition, with numbers: a 50 000 order, commission 5 000. After the first qualifying delivery the
     * store's debt is 5 000 (the commission alone, never 10 000), the referrer has 5 000 more, and the platform's referral
     * cost of 5 000 is recorded once — a row that does not move the store's balance. A retry, a second booking and the
     * next delivery change nothing of it.
     */
    public function test_the_platform_pays_the_bonus_and_the_store_only_its_commission(): void
    {
        $order = $this->deliveredOrder('store_paylov');

        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);
        $this->assertSame(5000.0, $this->referrerBalance());
        $row = StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFERRAL_BONUS->value)->sole();
        // amount: what the platform paid; the store's debt is the same before and after it (its commission, 5 000)
        $this->assertSame(['5000.00', '5000.00', '5000.00'], [(string) $row->amount, (string) $row->balance_before, (string) $row->balance_after]);
        $this->assertSame("Referral bonus paid by the platform for referred user #{$this->customer->id}", $row->notes);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->count());

        app(LedgerService::class)->processOrderDelivered($order);
        app(\App\Services\Finance\SettlementRecoveryService::class)->book($order);
        $this->deliveredOrder('platform_click');

        $this->assertCount(1, $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        // The second order's 50 000 settlement pays its own 5 000 commission and the first one's 5 000 debt (netting):
        // 40 000 reach the wallet. Had the store been charged the bonus, 5 000 more would have been taken (35 000)
        $this->assertSame(['0.00', '40000.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
    }

    public function test_a_legacy_store_charged_bonus_still_counts_as_granted(): void
    {
        // Before owner decision 3's addition the store paid it: a commission row with the referral note (kept as is)
        $earlier = $this->deliveredOrder('cash');
        StoreTransaction::create([
            'store_id' => $this->store->id, 'type' => StoreTransactionType::COMMISSION->value, 'amount' => '5000.00',
            'balance_before' => '5000.00', 'balance_after' => '10000.00', 'reference_id' => $earlier->uuid, 'order_id' => $earlier->id,
            'notes' => "Referral cashback reward cost for referred user #{$this->customer->id}",
        ]);

        $this->deliveredOrder('platform_click');

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
    }

    public function test_a_platform_order_paid_only_after_delivery_earns_it_when_the_payment_lands_once(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $this->assertSame([], $this->rewards());

        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));
        event(new PaymentPaid($payment)); // a replayed event

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_the_first_verified_delivery_uses_the_referral_up_even_below_the_minimum(): void
    {
        // Unchanged rule: the bonus is for the first (now: first verified) delivered order, and only from 50 000
        $this->deliveredOrder('platform_click', ['total_price' => 30000, 'commission_amount' => 3000]);
        $this->deliveredOrder('platform_click');

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
    }

    public function test_an_unverified_order_below_the_minimum_does_not_use_it_up(): void
    {
        $this->deliveredOrder('cash', ['total_price' => 30000, 'commission_amount' => 3000]);
        $order = $this->deliveredOrder('platform_click');

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
    }

    public function test_a_bonus_granted_at_another_store_is_never_granted_again_here(): void
    {
        $other = $this->makeStore('Boshqa ovqatxona');
        $first = $this->deliveredOrder('platform_payme', [], $other);
        $this->deliveredOrder('store_paylov');

        $this->assertSame([['order_id' => $first->id, 'store_id' => $other->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
    }

    public function test_a_second_booking_of_the_same_order_grants_nothing(): void
    {
        $order = $this->deliveredOrder('store_paylov');
        app(LedgerService::class)->processOrderDelivered($order);
        app(LedgerService::class)->grantReferralReward($order);

        $this->assertCount(1, $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
    }

    public function test_a_fully_refunded_verified_order_keeps_its_bonus_and_the_next_one_earns_none(): void
    {
        // Kept as before (OrderLifecycleRefundTest): the refund reverses the commission, not the bonus
        $first = $this->deliveredOrder('platform_click');
        $this->assertCount(1, $this->rewards());

        $admin = $this->makeStaff(\App\Enums\UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        app(OrderStatusService::class)->refund($first, $admin, 'Mijoz shikoyati');
        $this->assertSame(OrderStatus::REFUNDED, $first->fresh()->status);

        $this->deliveredOrder('platform_click');
        $this->assertCount(1, $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
    }
}
