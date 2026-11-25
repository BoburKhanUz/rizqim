<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Jobs\RetryFailedSettlementsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\AlertRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\Checks\SettlementFailuresCheck;
use App\Services\Admin\Today\Attention\UnbookedSettlementsSource;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Review H1: a payment that lands only after delivery (MarkOrderAsPaid) is credited to the store there. If that
 * settlement fails (a lock timeout, a deadlock, any error) it must never be lost silently: the providers (Click,
 * Payme, Paylov) answer the committed payment's success whatever the listener does, so nothing replays it. The
 * failure is retried in the same listener (SettlementRecoveryService::bookAfterPayment → book()) and, failing again,
 * marked on the order (settlement_failed_at) for RetryFailedSettlementsJob; if even the marker cannot be written the
 * listener throws (logged critical, alerted) and the monitoring check finds the paid, unsettled, unmarked order.
 *
 * The failures are real PostgreSQL errors: a trigger refuses every settlement row (and the debt payoff the netting
 * writes first) while it is installed, another refuses the marker. The payment goes through Click's real Complete.
 * 100 000 paid to RIZQIM's Click after delivery, 10 000 commission booked at delivery (the store's debt).
 */
class FinanceLatePaymentSettlementFailureTest extends TestCase
{
    // Installs and drops triggers (DDL): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    private Store $store;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        config(['services.click.secret_key' => 'platform-click-key', 'orders.settlement_retry_max_attempts' => 12]);
        $this->store = Store::forceCreate([
            'name' => 'Late Pay Cafe',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
        ]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998909990002', 'password' => bcrypt('password')]);
    }

    private function failSettlements(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_refuse_settlement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'settlement ledger unavailable (test)'; END $$;
            CREATE TRIGGER test_refuse_settlement_wallet BEFORE INSERT ON store_wallet_transactions
                FOR EACH ROW WHEN (NEW.type = 'settlement') EXECUTE FUNCTION test_refuse_settlement();
            CREATE TRIGGER test_refuse_settlement_payoff BEFORE INSERT ON store_transactions
                FOR EACH ROW WHEN (NEW.type = 'topup') EXECUTE FUNCTION test_refuse_settlement();
            SQL);
    }

    private function repairSettlements(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER test_refuse_settlement_wallet ON store_wallet_transactions;
            DROP TRIGGER test_refuse_settlement_payoff ON store_transactions;
            DROP FUNCTION test_refuse_settlement();
            SQL);
    }

    /** The marker (settlement_failed_at set on an unmarked order) is refused too. */
    private function failMarker(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_refuse_marker() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'orders unavailable (test)'; END $$;
            CREATE TRIGGER test_refuse_marker BEFORE UPDATE ON orders
                FOR EACH ROW WHEN (OLD.settlement_failed_at IS NULL AND NEW.settlement_failed_at IS NOT NULL) EXECUTE FUNCTION test_refuse_marker();
            SQL);
    }

    private function repairMarker(): void
    {
        DB::unprepared('DROP TRIGGER test_refuse_marker ON orders; DROP FUNCTION test_refuse_marker();');
    }

    /** Delivered unpaid: the commission is booked (10 000 debt), the settlement waits for the payment. */
    private function deliveredUnpaid(): Order
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 100000,
            'commission_amount' => 10000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => 'click',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }

        return $order->fresh();
    }

    /** Click's Complete for the order (the first, or a repeated one for the same click_trans_id). */
    private function clickComplete(Order $order): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '4242', 'service_id' => '1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '100000', 'action' => 1, 'error' => 0, 'sign_time' => '2026-11-25 12:00:00',
            'sign_string' => md5('4242' . '1' . 'platform-click-key' . $order->uuid . $order->id . '100000' . '1' . '2026-11-25 12:00:00'),
        ]);
    }

    private function retry(): void
    {
        app()->call([new RetryFailedSettlementsJob, 'handle']);
    }

    /** @return array{0: string, 1: string} [debt, wallet] */
    private function balances(): array
    {
        $store = $this->store->fresh();

        return [(string) $store->debt_balance, (string) $store->wallet_balance];
    }

    /** @return array{0: list<string>, 1: list<string>} [settlement rows (wallet), debt payoff rows] of the order */
    private function settlementRows(Order $order): array
    {
        return [
            StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->orderBy('id')->pluck('amount')->map(fn ($a) => \App\Support\Money::of((string) $a)->toString())->all(),
            StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::TOPUP->value)->orderBy('id')->pluck('amount')->map(fn ($a) => \App\Support\Money::of((string) $a)->toString())->all(),
        ];
    }

    private function ledgerRows(): int
    {
        return StoreTransaction::count() + StoreWalletTransaction::count();
    }

    /** @return list<string> uuids of the orders the monitoring check reports as paid, unsettled and unmarked */
    private function unmarkedFindings(): array
    {
        $rule = (new AlertRule)->forceFill(['kind' => AlertKind::SETTLEMENT_FAILURES->value] + AlertKind::SETTLEMENT_FAILURES->defaults());
        $finding = collect(app(SettlementFailuresCheck::class)->findings($rule))->first(fn ($finding) => $finding->subjectKey === 'unmarked');

        return $finding?->details['orders'] ?? [];
    }

    public function test_a_late_payments_settlement_that_fails_is_marked_and_the_retry_credits_it_exactly_once(): void
    {
        $order = $this->deliveredUnpaid();
        $this->assertSame(['10000.00', '0.00'], $this->balances());

        $this->failSettlements();
        $this->clickComplete($order)->assertJson(['error' => 0]);

        // The payment stands, nothing of the settlement was booked, and the order is marked for the retry
        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame(PaymentStatus::PAID, Payment::where('order_id', $order->id)->sole()->status);
        $this->assertNotNull($order->settlement_failed_at, 'a failed late settlement must leave the retry marker');
        $this->assertStringContainsString('settlement ledger unavailable', (string) $order->settlement_error);
        $this->assertSame([[], []], $this->settlementRows($order));
        $this->assertSame(['10000.00', '0.00'], $this->balances());

        // Still failing: the retry keeps the marker and books nothing
        $this->retry();
        $this->assertNotNull($order->fresh()->settlement_failed_at);
        $this->assertSame([[], []], $this->settlementRows($order));

        // Repaired: the retry pays the 10 000 debt and credits 90 000, once
        $this->repairSettlements();
        $this->retry();
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
        $this->assertSame(['0.00', '90000.00'], $this->balances());

        // The job again and Click's repeated Complete: nothing moves
        $rows = $this->ledgerRows();
        $this->retry();
        $this->clickComplete($order)->assertJson(['error' => 0]);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(['0.00', '90000.00'], $this->balances());
        $this->assertSame([], $this->unmarkedFindings());
    }

    /**
     * Neither the settlement nor the marker can be written: the listener throws (nothing of the settlement committed,
     * a critical log), Click still gets its success (the payment is committed), and the monitoring check reports the
     * order once it has waited 15 minutes. Click's repeated Complete books it then, once.
     */
    public function test_a_settlement_that_cannot_even_be_marked_fails_loudly_and_is_found_by_monitoring(): void
    {
        $order = $this->deliveredUnpaid();
        $this->assertSame(['10000.00', '0.00'], $this->balances());
        $this->failSettlements();
        $this->failMarker();
        Log::spy();

        $this->clickComplete($order)->assertJson(['error' => 0]);

        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertNull($order->settlement_failed_at);
        $this->assertSame([[], []], $this->settlementRows($order));
        $this->assertSame(['10000.00', '0.00'], $this->balances());
        Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'could not be marked for a retry'))->atLeast()->once();
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Click PaymentPaid listener failed'))->once();

        // The event itself throws: the failure is never logged-and-forgotten inside the listener
        $thrown = null;
        try {
            event(new PaymentPaid(Payment::where('order_id', $order->id)->sole()));
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'an unmarkable failure must surface from the listener');
        $this->assertSame([[], []], $this->settlementRows($order));
        $this->assertNull($order->fresh()->settlement_failed_at);

        // Monitoring: not yet (a payment being booked right now), then after 15 minutes
        $this->assertSame([], $this->unmarkedFindings());
        $this->travel(16)->minutes();
        $this->assertSame([$order->uuid], $this->unmarkedFindings());
        $attention = app(UnbookedSettlementsSource::class)->collect(5);
        $this->assertSame(1, $attention['count']);
        $this->assertSame('/orders/' . $order->uuid, $attention['items'][0]->href);

        // Repaired: Click's repeated Complete credits it, once
        $this->repairMarker();
        $this->repairSettlements();
        $this->clickComplete($order)->assertJson(['error' => 0]);
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
        $this->assertSame(['0.00', '90000.00'], $this->balances());
        $this->clickComplete($order)->assertJson(['error' => 0]);
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
        $this->assertSame([], $this->unmarkedFindings());
    }

    /** Only paid, platform-collected, delivered orders without a settlement or a marker, after 15 minutes. */
    public function test_the_monitoring_check_ignores_settled_marked_unpaid_and_store_collected_orders(): void
    {
        $unpaid = $this->deliveredUnpaid();
        $settled = $this->deliveredUnpaid();
        $this->clickComplete($settled)->assertJson(['error' => 0]);
        $marked = $this->deliveredUnpaid();
        $marked->forceFill(['payment_status' => PaymentStatus::PAID->value, 'settlement_failed_at' => now(), 'settlement_error' => 'x'])->saveQuietly();
        $storeOwned = $this->deliveredUnpaid();
        $storeOwned->forceFill(['payment_status' => PaymentStatus::PAID->value, 'payment_owner' => 'STORE'])->saveQuietly();
        $missing = $this->deliveredUnpaid();
        $missing->forceFill(['payment_status' => PaymentStatus::PAID->value])->saveQuietly();

        $this->travel(16)->minutes();

        $this->assertSame([$missing->uuid], $this->unmarkedFindings());
        $this->assertSame(1, app(UnbookedSettlementsSource::class)->collect(5)['count']);
        $this->assertNotNull($unpaid);
    }
}
