<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreWalletTransaction;
use App\Services\Finance\Billing\SubscriptionDebt;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Netting\NettingService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Follow-up of the Click/Payme go-live review (commit 3af45201):
 * - M1: a PaymentPaid listener failing after the payment committed never turns into an error answer (Payme -32400,
 *   Click HTTP 500), and the provider's replay of the same transaction fires the event again, so settlement and the
 *   auto-accept are not lost; the listener books everything once however often it fires.
 * - M2: checkout and store top-ups refuse a platform provider whose merchant ids are missing (422
 *   payment_provider_unconfigured, no row), and app:check-production names each missing platform credential.
 * - LOW: Payme statements of a cashbox key shared by several stores, Click sub-tiyin amounts, webhook rate limit.
 */
class PaymentGoLiveFollowUpTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const PAYME_KEY = 'platform_payme_key';

    private const CLICK_SECRET = 'platform_click_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payme.secret_key' => self::PAYME_KEY,
            'services.payme.merchant_id' => 'platform-cashbox',
            'services.click.secret_key' => self::CLICK_SECRET,
            'services.click.service_id' => 'platform-service',
            'services.click.merchant_id' => 'platform-merchant',
        ]);

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['payme' => ['enabled' => true], 'click' => ['enabled' => true]]),
        ])->save();
    }

    private function order(string $method, array $attributes = [], ?Store $store = null): Order
    {
        return Order::create(array_merge([
            'store_id' => ($store ?? $this->store)->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => $method,
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function payme(string $method, array $params, string $key = self::PAYME_KEY)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $key),
        ]);
    }

    private function click(int $action, Order $order, string $amount, string $clickTransId = '9001')
    {
        $signTime = '2026-11-21 12:00:00';
        $data = [
            'click_trans_id' => $clickTransId,
            'service_id' => 'platform-service',
            'click_paydoc_id' => '555',
            'merchant_trans_id' => $order->uuid,
            'amount' => $amount,
            'action' => $action,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5($clickTransId . 'platform-service' . self::CLICK_SECRET . $order->uuid . ($action === 1 ? $order->id : '') . $amount . $action . $signTime),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $order->id;
        }

        return $this->postJson('/api/v1/payments/click/webhook', $data);
    }

    private function deliverUnpaid(Order $order): void
    {
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
    }

    private function settlements(Order $order): int
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->count();
    }

    /** A LedgerService whose first settlePlatformOrder fails (a deadlock), the next ones work. */
    private function settlementFailingOnce(): LedgerService
    {
        $ledger = new class(app(SubscriptionDebt::class), app(NettingService::class)) extends LedgerService {
            public int $calls = 0;

            public function settlePlatformOrder(Order $order, ?int $performedBy = null): bool
            {
                if (++$this->calls === 1) {
                    throw new \RuntimeException('deadlock detected (simulated, once)');
                }

                return parent::settlePlatformOrder($order, $performedBy);
            }
        };
        $this->app->instance(LedgerService::class, $ledger);

        return $ledger;
    }

    /** A PaymentPaid listener that fails once, registered ahead of MarkOrderAsPaid. */
    private function paidListenerFailingOnce(): void
    {
        $listeners = Event::getRawListeners()[PaymentPaid::class] ?? [];
        Event::forget(PaymentPaid::class);
        $once = true;
        Event::listen(PaymentPaid::class, function () use (&$once) {
            if ($once) {
                $once = false;
                throw new \RuntimeException('telegram/db hiccup (simulated, once)');
            }
        });
        foreach ($listeners as $listener) {
            Event::listen(PaymentPaid::class, $listener);
        }
    }

    // ---- M1 ----

    public function test_payme_perform_answers_success_when_settlement_fails_and_the_replay_settles_once(): void
    {
        $order = $this->order('payme');
        $this->deliverUnpaid($order);
        $ledger = $this->settlementFailingOnce();
        Log::spy();

        $this->payme('CreateTransaction', ['id' => 'm1-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);

        // The money is taken and committed: the answer is the success, never -32400
        $this->payme('PerformTransaction', ['id' => 'm1-1'])->assertJsonPath('result.state', 2)->assertJsonMissingPath('error');
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider_transaction_id', 'm1-1')->sole()->status);
        // Review H1: the failed settlement is booked again in the same listener (bookAfterPayment → book), so the
        // store is credited at once and nothing reaches the provider's handler
        $this->assertSame(1, $this->settlements($order));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Settlement of the late payment'));
        Log::shouldNotHaveReceived('error', [\Mockery::on(fn ($message) => str_contains((string) $message, 'PaymentPaid'))]);

        // Payme's replay of the performed transaction (or a manual re-send) fires the event again: the store is credited
        $this->payme('PerformTransaction', ['id' => 'm1-1'])->assertJsonPath('result.state', 2);
        $this->payme('PerformTransaction', ['id' => 'm1-1'])->assertJsonPath('result.state', 2);

        // The failed call and its retry in book(), then twice per replay: the settlement itself and, review finding 3,
        // the delivery booking run again in its own savepoint (SettlementRecoveryService::book), a no-op each time
        $this->assertSame(6, $ledger->calls);
        $this->assertSame(1, $this->settlements($order));
        $this->assertEquals(45000, (float) $this->store->fresh()->wallet_balance);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_payme_new_order_is_auto_accepted_on_the_replay_after_a_listener_failure(): void
    {
        $order = $this->order('payme');
        $this->paidListenerFailingOnce();

        $this->payme('CreateTransaction', ['id' => 'm1-2', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]]);
        $this->payme('PerformTransaction', ['id' => 'm1-2'])->assertJsonPath('result.state', 2);
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        $this->payme('PerformTransaction', ['id' => 'm1-2'])->assertJsonPath('result.state', 2);
        $this->payme('PerformTransaction', ['id' => 'm1-2'])->assertJsonPath('result.state', 2);

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'accepted')->count());
    }

    public function test_payme_replay_never_marks_paid_again_an_order_no_longer_paid(): void
    {
        $order = $this->order('payme');
        $this->payme('CreateTransaction', ['id' => 'm1-3', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]]);
        $this->payme('PerformTransaction', ['id' => 'm1-3'])->assertJsonPath('result.state', 2);
        $order->forceFill(['status' => OrderStatus::REFUNDED->value, 'payment_status' => PaymentStatus::REFUNDED->value])->save();

        $this->payme('PerformTransaction', ['id' => 'm1-3'])->assertJsonPath('result.state', 2);

        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
    }

    public function test_click_complete_answers_success_when_a_listener_fails_and_the_repeated_complete_settles(): void
    {
        $order = $this->order('click');
        $this->deliverUnpaid($order);
        $ledger = $this->settlementFailingOnce();
        Log::spy();

        // Never an HTTP 500 to Click once the money is taken
        $this->click(1, $order, '50000')->assertOk()->assertJson(['error' => 0, 'error_note' => 'Success']);
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider', 'click')->sole()->status);
        // Review H1: the failed settlement is booked again in the same listener (bookAfterPayment → book), so the
        // store is credited at once and nothing reaches the provider's handler
        $this->assertSame(1, $this->settlements($order));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Settlement of the late payment'));
        Log::shouldNotHaveReceived('error', [\Mockery::on(fn ($message) => str_contains((string) $message, 'PaymentPaid'))]);

        $this->click(1, $order, '50000')->assertOk()->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);
        $this->click(1, $order, '50000')->assertOk()->assertJson(['error' => 0]);

        // The failed call and its retry in book(), then twice per replay: the settlement itself and, review finding 3,
        // the delivery booking run again in its own savepoint (SettlementRecoveryService::book), a no-op each time
        $this->assertSame(6, $ledger->calls);
        $this->assertSame(1, $this->settlements($order));
        $this->assertEquals(45000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_paid_event_fired_twice_settles_accepts_and_notifies_once(): void
    {
        $telegram = $this->mock(TelegramNotificationService::class);
        $telegram->shouldIgnoreMissing();
        $telegram->shouldReceive('sendOrderCreated')->once();

        // A delivered platform order paid late: one settlement however often the event fires
        $delivered = $this->order('payme');
        $this->deliverUnpaid($delivered);
        $delivered->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();
        $late = Payment::create(['order_id' => $delivered->id, 'store_id' => $this->store->id, 'provider' => 'payme', 'provider_transaction_id' => 'twice-1', 'amount' => '50000.00', 'status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($late));
        event(new PaymentPaid($late->fresh()));
        $this->assertSame(1, $this->settlements($delivered));
        $this->assertEquals(45000, (float) $this->store->fresh()->wallet_balance);

        // A new order: accepted once, the kitchen notified once
        $new = $this->order('payme', ['payment_status' => PaymentStatus::PAID->value]);
        $payment = Payment::create(['order_id' => $new->id, 'store_id' => $this->store->id, 'provider' => 'payme', 'provider_transaction_id' => 'twice-2', 'amount' => '50000.00', 'status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));
        event(new PaymentPaid($payment->fresh()));
        $this->assertSame(OrderStatus::ACCEPTED, $new->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $new->id)->where('status', 'accepted')->count());
        $this->assertSame(0, $this->settlements($new));
    }

    // ---- M2 ----

    private function placeOrder(string $method)
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => $method,
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ]);
    }

    public function test_checkout_refuses_a_platform_provider_without_merchant_ids_before_any_row(): void
    {
        config(['services.payme.merchant_id' => null, 'services.click.service_id' => null]);
        Log::spy();

        foreach (['payme', 'click'] as $method) {
            $this->placeOrder($method)
                ->assertStatus(422)
                ->assertJsonPath('code', 'payment_provider_unconfigured')
                ->assertJsonPath('data.reason', 'payment_provider_unconfigured')
                ->assertJsonPath('data.payment_method', $method)
                ->assertJsonPath('errors.payment_method.0', ucfirst($method) . " orqali to'lov hozircha sozlanmagan. Boshqa to'lov usulini tanlang.");
        }

        $this->assertSame(0, Order::count());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'not configured') && ($context['missing'] ?? null) === ['merchant_id'])->once();
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'not configured') && ($context['missing'] ?? null) === ['service_id'])->once();

        // Cash is untouched
        $this->placeOrder('cash')->assertCreated();
    }

    public function test_merchant_ids_stored_in_the_control_center_count(): void
    {
        config(['services.payme.merchant_id' => null]);
        PaymentProvider::forceCreate(['name' => 'Payme', 'slug' => 'payme', 'is_active' => true, 'mode' => 'production', 'credentials' => ['merchant_id' => 'db-cashbox']]);

        $url = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('m=db-cashbox;', base64_decode(substr($url, strlen('https://checkout.paycom.uz/'))));
    }

    public function test_a_store_collecting_into_its_own_account_does_not_need_the_platform_ids(): void
    {
        config(['services.payme.merchant_id' => null]);
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'own-key']],
        ]);

        $url = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('m=own-cashbox;', base64_decode(substr($url, strlen('https://checkout.paycom.uz/'))));
    }

    public function test_store_topup_refuses_a_provider_without_merchant_ids_before_any_row(): void
    {
        config(['services.click.merchant_id' => null]);
        Log::spy();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/finance/top-up', ['amount' => 100000, 'provider' => 'click'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_provider_unconfigured')
            ->assertJsonPath('data.reason', 'payment_provider_unconfigured')
            ->assertJsonPath('errors.provider.0', "Click orqali to'lov hozircha sozlanmagan. Boshqa to'lov usulini tanlang.");

        $this->assertSame(0, StoreTopupRequest::count());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'not configured') && ($context['missing'] ?? null) === ['merchant_id'])->once();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/finance/top-up', ['amount' => 100000, 'provider' => 'payme'])->assertOk();
        $this->assertSame(1, StoreTopupRequest::count());
    }

    public function test_check_production_names_each_missing_platform_credential(): void
    {
        config([
            'app.env' => 'production',
            'services.payme.merchant_id' => null, 'services.payme.secret_key' => null,
            'services.click.service_id' => null, 'services.click.merchant_id' => null, 'services.click.secret_key' => null,
        ]);
        // A key stored in the control center counts as configured
        PaymentProvider::forceCreate(['name' => 'Payme', 'slug' => 'payme', 'is_active' => true, 'mode' => 'production', 'credentials' => ['secret_key' => 'db-secret-value']]);

        Artisan::call('app:check-production');
        $out = Artisan::output();

        $this->assertStringContainsString('payme: merchant_id (PAYME_MERCHANT_ID)', $out);
        $this->assertStringNotContainsString('PAYME_SECRET_KEY', $out);
        $this->assertStringContainsString('click: service_id (CLICK_SERVICE_ID), merchant_id (CLICK_MERCHANT_ID), secret_key (CLICK_SECRET_KEY)', $out);
        // Never a key's value
        $this->assertStringNotContainsString('db-secret-value', $out);

        // A provider the team switched off is not reported
        PaymentProvider::forceCreate(['name' => 'Click', 'slug' => 'click', 'is_active' => false, 'mode' => 'production', 'credentials' => []]);
        Artisan::call('app:check-production');
        $this->assertStringNotContainsString('CLICK_SERVICE_ID', Artisan::output());
    }

    // ---- LOW ----

    public function test_statement_of_a_cashbox_key_shared_by_several_stores_lists_all_of_them(): void
    {
        $settings = ['payme' => ['enabled' => true, 'merchant_id' => 'chain-cashbox', 'secret_key' => 'chain_key']];
        $a = $this->makeStore('Chain A');
        $b = $this->makeStore('Chain B');
        foreach ([$a, $b] as $store) {
            $store->update(['payment_account_type' => 'own_account', 'payment_settings' => $settings]);
        }
        $oa = $this->order('payme', ['payment_owner' => 'STORE'], $a);
        $ob = $this->order('payme', ['payment_owner' => 'STORE'], $b);
        $other = $this->order('payme'); // the platform's

        $this->payme('CreateTransaction', ['id' => 'trx-a', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $oa->uuid]], 'chain_key')->assertJsonPath('result.state', 1);
        $this->payme('CreateTransaction', ['id' => 'trx-b', 'time' => 1760000001000, 'amount' => 5000000, 'account' => ['order_id' => $ob->uuid]], 'chain_key')->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => 'trx-b'], 'chain_key')->assertJsonPath('result.state', 2);
        $this->payme('CreateTransaction', ['id' => 'trx-p', 'time' => 1760000002000, 'amount' => 5000000, 'account' => ['order_id' => $other->uuid]])->assertJsonPath('result.state', 1);

        $ids = array_column($this->payme('GetStatement', ['from' => 1760000000000, 'to' => 1760000010000], 'chain_key')->json('result.transactions'), 'id');
        $this->assertSame(['trx-a', 'trx-b'], $ids);

        $ids = array_column($this->payme('GetStatement', ['from' => 1760000000000, 'to' => 1760000010000])->json('result.transactions'), 'id');
        $this->assertSame(['trx-p'], $ids);
    }

    public function test_statement_cashbox_lookup_failing_answers_a_system_error(): void
    {
        $this->makeStore('Own cashbox')->update(['payment_account_type' => 'own_account', 'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own', 'secret_key' => 'own_key']]]);
        Store::retrieved(function () {
            throw new \RuntimeException('SQLSTATE[08006] connection lost (simulated)');
        });

        // Security review F6: only the stores the key's digest names are read (a key no store has reads none)
        $this->payme('GetStatement', ['from' => 1, 'to' => 2], 'own_key')->assertOk()->assertJsonPath('error.code', -32400);
    }

    public function test_click_refuses_sub_tiyin_amounts_but_takes_whole_and_two_decimal_ones(): void
    {
        $order = $this->order('click');

        foreach (['50000.001', '50000.0001', '49999.999'] as $amount) {
            $this->click(0, $order, $amount)->assertJson(['error' => -2]);
        }
        foreach (['50000', '50000.00', '50000.0', '50000.000'] as $amount) {
            $this->click(0, $order, $amount)->assertJson(['error' => 0]);
        }
    }

    public function test_provider_webhooks_are_rate_limited_generously(): void
    {
        foreach (['api/v1/payments/payme/webhook', 'api/v1/payments/click/webhook'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);
            $this->assertContains('throttle:payment-webhooks', $route->gatherMiddleware(), $uri);
        }

        $limit = \Illuminate\Support\Facades\RateLimiter::limiter('payment-webhooks')(request());
        $limit = is_array($limit) ? $limit[0] : $limit;
        $this->assertGreaterThanOrEqual(600, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);

        // A burst well past any normal minute of callbacks still goes through
        for ($i = 0; $i < 120; $i++) {
            $this->payme('CheckTransaction', ['id' => 'none'])->assertOk()->assertJsonPath('error.code', -31003);
        }
    }
}
