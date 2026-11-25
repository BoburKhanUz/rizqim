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
        Payment::create(['order_id' => $missing->id, 'provider' => 'click', 'amount' => 100000, 'status' => PaymentStatus::PAID, 'paid_at' => now()]);

        $this->travel(16)->minutes();

        $this->assertSame([$missing->uuid], $this->unmarkedFindings());
        $this->assertSame(1, app(UnbookedSettlementsSource::class)->collect(5)['count']);
        $this->assertNotNull($unpaid);
    }

    private const SUPERADMIN_CHAT = '-100700700';

    private function configureAlerts(): void
    {
        config(['services.telegram.bot_token' => '123456:late-pay-token', 'services.telegram.superadmin_chat_id' => self::SUPERADMIN_CHAT]);
    }

    /** @return \Illuminate\Support\Collection<int, string> the superadmin alerts queued so far */
    private function alerts(): \Illuminate\Support\Collection
    {
        return Queue::pushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => ($job->params['chat_id'] ?? null) === self::SUPERADMIN_CHAT)
            ->map(fn (SendTelegramRequest $job) => (string) $job->params['text'])->values();
    }

    /**
     * Neither the settlement nor the marker could be written when Click's Complete landed; both repaired since. The
     * order is paid, delivered, uncredited and unmarked: nothing books it by itself.
     */
    private function unbookedUnmarked(): Order
    {
        $order = $this->deliveredUnpaid();
        $this->failSettlements();
        $this->failMarker();
        $this->clickComplete($order)->assertJson(['error' => 0]);
        $this->repairMarker();
        $this->repairSettlements();
        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertNull($order->settlement_failed_at);
        $this->assertSame([[], []], $this->settlementRows($order));

        return $order;
    }

    /**
     * Review (double credit): finance may already have credited such an order by hand (a manual adjustment carries no
     * order_id). The command refuses an unmarked, unbooked order without --confirm-unbooked: it books nothing, names
     * what it found and the checks to make (the store's adjustments since the order). With the flag it books it once;
     * the catch-up reason is `manual`.
     */
    public function test_the_command_refuses_an_unmarked_unbooked_order_without_confirmation_and_books_it_once_with_it(): void
    {
        $order = $this->unbookedUnmarked();
        $adjustment = \App\Models\FinanceAdjustment::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'store_id' => $this->store->id, 'kind' => 'wallet_credit',
            'amount' => '90000.00', 'reason' => 'Click to\'lovi do\'konga yozilmagan edi', 'status' => \App\Models\FinanceAdjustment::APPLIED,
            'applied_at' => now(),
        ]);
        $this->travel(16)->minutes();
        $rows = $this->ledgerRows();

        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id]])
            ->expectsOutputToContain('--confirm-unbooked')
            ->expectsOutputToContain((string) $adjustment->uuid)
            ->assertFailed();
        $this->assertSame($rows, $this->ledgerRows(), 'nothing is booked without --confirm-unbooked');
        $this->assertSame(['10000.00', '0.00'], $this->balances());
        $this->assertNull($order->fresh()->settlement_failed_at);

        $this->artisan('orders:retry-settlements', ['orders' => [$order->uuid], '--confirm-unbooked' => true])
            ->expectsOutputToContain('booked')->assertSuccessful();
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
        $this->assertSame(['0.00', '90000.00'], $this->balances());

        // Again: nothing left to book
        $rows = $this->ledgerRows();
        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id], '--confirm-unbooked' => true])
            ->expectsOutputToContain('no failed ledger booking');
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame([], $this->unmarkedFindings());
    }

    /** A marked order: the command books it as before, without the flag, once. */
    public function test_the_command_books_a_marked_order_without_confirmation_once(): void
    {
        $order = $this->deliveredUnpaid();
        $this->failSettlements();
        $this->clickComplete($order)->assertJson(['error' => 0]);
        $this->assertNotNull($order->fresh()->settlement_failed_at);
        $this->repairSettlements();

        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id]])->expectsOutputToContain('booked')->assertSuccessful();
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));

        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id]])->expectsOutputToContain('no failed ledger booking');
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
        $this->assertSame(['0.00', '90000.00'], $this->balances());
    }

    /**
     * The marker could not be written in book()'s transaction nor in bookAfterPayment's own: one alert, sent once the
     * outcome is known, saying to check the store's adjustments first and only then run the command with the flag.
     */
    public function test_an_unmarkable_late_settlement_alerts_superadmins_once_with_the_checks_to_make(): void
    {
        $this->configureAlerts();
        $order = $this->unbookedUnmarked();

        $alerts = $this->alerts()->filter(fn (string $text) => str_contains($text, '#' . $order->short_code))->values();
        $this->assertCount(1, $alerts, 'one alert for one unmarkable failure: ' . $alerts->implode(' | '));
        $this->assertStringContainsString("orders:retry-settlements {$order->id} --confirm-unbooked", $alerts[0]);
        $this->assertStringContainsString('tuzatish', $alerts[0]);
    }

    /**
     * Review (query cost): the check and the Today list look at payments paid in the last 30 days (and 15 minutes ago
     * at least), by the payment's paid time; the command still finds an older one (the pre-deploy SQL lists them all).
     */
    public function test_unbooked_orders_are_reported_by_the_payments_paid_time_within_30_days(): void
    {
        $order = $this->unbookedUnmarked();
        $payment = Payment::where('order_id', $order->id)->sole();

        // Paid 5 minutes ago (the order itself untouched for an hour): not yet
        $order->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
        $payment->forceFill(['paid_at' => now()->subMinutes(5)])->saveQuietly();
        $this->assertSame([], $this->unmarkedFindings());

        $payment->forceFill(['paid_at' => now()->subDays(20)])->saveQuietly();
        $this->assertSame([$order->uuid], $this->unmarkedFindings());

        $payment->forceFill(['paid_at' => now()->subDays(31)])->saveQuietly();
        $this->assertSame([], $this->unmarkedFindings());
        $this->assertSame(0, app(UnbookedSettlementsSource::class)->collect(5)['count']);

        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id]])->expectsOutputToContain('--confirm-unbooked')->assertFailed();
        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id], '--confirm-unbooked' => true])->expectsOutputToContain('booked')->assertSuccessful();
        $this->assertSame([['90000.00'], ['-10000.00']], $this->settlementRows($order));
    }

    /** The Today item: the admin retry button cannot book it; the subtitle names the check and the server command. */
    public function test_the_attention_item_names_the_check_and_the_server_command(): void
    {
        $order = $this->unbookedUnmarked();
        $this->travel(16)->minutes();

        $item = app(UnbookedSettlementsSource::class)->collect(5)['items'][0];
        $this->assertStringContainsString("php artisan orders:retry-settlements {$order->id} --confirm-unbooked", (string) $item->subtitle);
        $this->assertStringContainsString('tuzatish', (string) $item->subtitle);
        $this->assertStringContainsString('server', (string) $item->subtitle);
    }

    /**
     * Review (query cost): the window is read through payments_paid_at_index (paid payments by paid time), never by
     * reading every payment; EXPLAIN of the query the check and the Today list run.
     */
    public function test_the_window_reads_payments_through_the_paid_at_index(): void
    {
        $query = \App\Services\Finance\SettlementRecoveryService::unbookedLatePayments()->toBase()->selectRaw('count(*)');

        $plan = DB::transaction(function () use ($query) {
            DB::statement('SET LOCAL enable_seqscan = off');

            return implode("\n", array_column(array_map(fn ($row) => (array) $row, DB::select('EXPLAIN ' . $query->toSql(), $query->getBindings())), 'QUERY PLAN'));
        });
        $this->assertStringContainsString('payments_paid_at_index', $plan);
    }
}
