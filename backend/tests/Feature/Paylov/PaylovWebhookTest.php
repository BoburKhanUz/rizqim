<?php

namespace Tests\Feature\Paylov;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Jobs\CancelUnpaidOnlineOrdersJob;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Services\Finance\Billing\SubscriptionDebt;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Netting\NettingService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P: the Paylov Checkout API callbacks (developer.paylov.uz/merchants/paylov-payment-gateway, transaction-check,
 * transaction-perform, statuses) and the cancel webhook (subscribe/payment/cancel-webhook). Paylov's callbacks carry
 * only Basic Auth, no signature: a perform is settled only once getTransactions (subscribe/payment/transaction-status)
 * says the transaction is `payed`; until then the payment stays `unconfirmed` and the scheduled re-check resolves it.
 */
class PaylovWebhookTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PaylovFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true]]),
        ])->save();
        $this->setUpPaylov();
    }

    private function settlements(Order $order): int
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->count();
    }

    private function paylovPayment(string $transactionId): ?Payment
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $transactionId)->first();
    }

    // ---- transaction.check ----

    public function test_check_accepts_the_order_and_its_exact_amount_in_tiyin(): void
    {
        $order = $this->paylovOrder($this->store, ['total_price' => '50000.50']);

        $this->check($order)->assertOk()->assertExactJson(self::answer());
        // A check creates nothing: Paylov has not taken money yet
        $this->assertSame(0, Payment::where('provider', 'paylov')->whereNotNull('provider_transaction_id')->count());
        Http::assertNothingSent();
    }

    public function test_check_refuses_another_amount_a_fraction_of_a_tiyin_and_a_missing_amount(): void
    {
        $order = $this->paylovOrder($this->store);

        $this->check($order, 4999999)->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->check($order, 5000001)->assertExactJson(self::answer('5', 'Invalid amount'));
        // amount_tiyin must be a whole number of tiyin: never rounded into a match
        $this->check($order, overrides: ['amount_tiyin' => 5000000.5])->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->check($order, overrides: ['amount_tiyin' => '5000000.5'])->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->check($order, overrides: ['amount_tiyin' => null])->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->check($order, overrides: ['amount_tiyin' => 'abc'])->assertExactJson(self::answer('5', 'Invalid amount'));
        // The whole-so'm `amount` alone is not enough (it drops the tiyin)
        $this->check($order, overrides: ['amount_tiyin' => null, 'amount' => 50000])->assertExactJson(self::answer('5', 'Invalid amount'));
        // Only so'm is taken
        $this->check($order, overrides: ['currency' => 840])->assertExactJson(self::answer('+1', 'unsupported_currency'));
    }

    public function test_check_refuses_an_unknown_order_and_one_not_paid_through_paylov(): void
    {
        $cash = $this->paylovOrder($this->store, ['payment_method' => 'cash']);
        $this->check($cash)->assertExactJson(self::answer('303', 'Order not found'));

        $this->paylovRpc('transaction.check', ['account' => ['order_id' => (string) Str::uuid()], 'amount' => 500, 'amount_tiyin' => 50000, 'currency' => 860])
            ->assertExactJson(self::answer('303', 'Order not found'));
        $this->paylovRpc('transaction.check', ['account' => [], 'amount' => 500, 'amount_tiyin' => 50000, 'currency' => 860])
            ->assertExactJson(self::answer('303', 'Order not found'));
    }

    public function test_check_refuses_a_paid_and_a_cancelled_order(): void
    {
        $paid = $this->paylovOrder($this->store, ['payment_status' => PaymentStatus::PAID->value]);
        $this->check($paid)->assertExactJson(self::answer('+1', 'order_already_paid'));

        $cancelled = $this->paylovOrder($this->store, ['status' => OrderStatus::CANCELED->value]);
        $this->check($cancelled)->assertExactJson(self::answer('+1', 'order_not_payable'));
    }

    // ---- Basic Auth ----

    public function test_a_request_without_the_right_basic_auth_is_refused_and_changes_nothing(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $params = ['transaction_id' => $txn, 'account' => ['order_id' => $order->uuid], 'amount' => 50000, 'amount_tiyin' => 5000000, 'currency' => 860];
        $body = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'transaction.perform', 'params' => $params];

        $this->postJson('/api/v1/payments/paylov/webhook', $body)->assertStatus(401);
        $this->postJson('/api/v1/payments/paylov/webhook', $body, ['Authorization' => 'Basic ' . base64_encode($this->paylov['callback_login'] . ':' . Str::random(28))])->assertStatus(401);
        $this->postJson('/api/v1/payments/paylov/webhook', $body, ['Authorization' => 'Basic ' . base64_encode(Str::random(8) . ':' . $this->paylov['callback_password'])])->assertStatus(401);
        $this->postJson('/api/v1/payments/paylov/webhook', $body, ['Authorization' => 'Bearer ' . $this->paylov['callback_password']])->assertStatus(401);
        $this->postJson('/api/v1/payments/paylov/webhook', $body, ['Authorization' => 'Basic not-base64!'])->assertStatus(401);
        // The API credentials are not the callback's
        $this->perform($order, $txn, account: ['callback_login' => $this->paylov['username'], 'callback_password' => $this->paylov['password']])->assertStatus(401);

        $this->assertNull($this->paylovPayment($txn));
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_unset_callback_credentials_fail_closed_with_a_log_naming_no_value(): void
    {
        config(['services.paylov.callback_password' => null]);
        Log::spy();
        $order = $this->paylovOrder($this->store);

        $this->check($order)->assertStatus(401);
        $this->paylovRpc('transaction.check', ['account' => ['order_id' => $order->uuid]], ['callback_login' => $this->paylov['callback_login'], 'callback_password' => ''])->assertStatus(401);

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'Paylov webhook rejected')
            && !str_contains(json_encode($context), $this->paylov['callback_login']));
    }

    public function test_a_store_owned_order_is_authorised_by_the_stores_own_callback_credentials_only(): void
    {
        $own = self::makeAccount();
        $store = $this->makeStore('Own Paylov');
        $store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['paylov' => ['enabled' => true] + $own],
        ])->save();
        $order = $this->paylovOrder($store, ['payment_owner' => 'STORE']);

        $this->check($order)->assertStatus(401);
        $this->check($order, account: $own)->assertExactJson(self::answer());

        // A platform order is never confirmed with a store's credentials
        $platformOrder = $this->paylovOrder($this->store);
        $this->check($platformOrder, account: $own)->assertStatus(401);
    }

    public function test_a_stores_own_callback_credentials_never_check_or_perform_another_stores_order(): void
    {
        $mine = self::makeAccount();
        $theirs = self::makeAccount();
        $stores = [];
        foreach (['Mine' => $mine, 'Theirs' => $theirs] as $name => $account) {
            $stores[$name] = $this->makeStore("{$name} Paylov");
            $stores[$name]->forceFill(['payment_account_type' => 'own_account', 'payment_settings' => ['paylov' => ['enabled' => true] + $account]])->save();
        }
        $order = $this->paylovOrder($stores['Theirs'], ['payment_owner' => 'STORE']);
        $transactionId = (string) Str::uuid();

        $this->check($order, account: $mine)->assertStatus(401);
        $this->perform($order, $transactionId, account: $mine)->assertStatus(401);
        $this->cancelWebhook($order, $transactionId, account: $mine)->assertStatus(401);

        $this->assertNull($this->paylovPayment($transactionId));
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    // ---- transaction.perform ----

    public function test_perform_is_confirmed_with_paylov_and_marks_the_order_paid_exactly_once(): void
    {
        Event::fake([PaymentPaid::class]);
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn)->assertOk()->assertExactJson(self::answer());

        $payment = $this->paylovPayment($txn);
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame('50000.00', $payment->amount);
        $this->assertSame('confirmed', $payment->payload['paylov']['state']);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        Event::assertDispatchedTimes(PaymentPaid::class, 1);

        // The token request: Basic consumer_key:consumer_secret, a JSON password grant (subscribe/authorization)
        $token = $this->sentTo('/merchant/oauth2/token/');
        $this->assertCount(1, $token);
        $this->assertSame('Basic ' . base64_encode($this->paylov['consumer_key'] . ':' . $this->paylov['consumer_secret']), $token[0]->header('Authorization')[0]);
        $this->assertSame(['grant_type' => 'password', 'username' => $this->paylov['username'], 'password' => $this->paylov['password']], $token[0]->data());
        // The status read with the bearer token (subscribe/payment/transaction-status)
        $status = $this->sentTo('/merchant/getTransactions/');
        $this->assertCount(1, $status);
        $this->assertSame('GET', $status[0]->method());
        $this->assertSame(self::BASE . '/merchant/getTransactions/?id=' . $txn, $status[0]->url());
        $this->assertSame('Bearer ' . $this->accessToken, $status[0]->header('Authorization')[0]);

        // Paylov's replay of the perform: the same answer, no second payment, the event fired again (the listener books
        // everything once), no second status read
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->assertSame(1, Payment::where('provider', 'paylov')->whereNotNull('provider_transaction_id')->count());
        Event::assertDispatchedTimes(PaymentPaid::class, 2);
        $this->assertCount(1, $this->sentTo('/merchant/getTransactions/'));
    }

    public function test_the_access_token_is_reused_until_it_expires(): void
    {
        $first = $this->paylovOrder($this->store);
        $second = $this->paylovOrder($this->store);

        $this->perform($first, (string) Str::uuid())->assertExactJson(self::answer());
        $this->perform($second, (string) Str::uuid())->assertExactJson(self::answer());
        $this->assertCount(1, $this->sentTo('/merchant/oauth2/token/'));

        $this->travel(3600)->seconds();
        $this->perform($this->paylovOrder($this->store), (string) Str::uuid())->assertExactJson(self::answer());
        $this->assertCount(2, $this->sentTo('/merchant/oauth2/token/'));
    }

    public function test_perform_answers_success_when_settlement_fails_and_the_replay_settles_once(): void
    {
        $order = $this->paylovOrder($this->store);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
        // The first two settlements fail (the listener's own and book()'s retry of it) and the retry marker cannot be
        // written either: the first Perform credits nothing and leaves no marker, only the replay can heal it
        $ledger = new class(app(SubscriptionDebt::class), app(NettingService::class)) extends LedgerService {
            public int $calls = 0;

            public function settlePlatformOrder(Order $order, ?int $performedBy = null): bool
            {
                if (++$this->calls <= 2) {
                    throw new \RuntimeException('deadlock detected (simulated, twice)');
                }

                return parent::settlePlatformOrder($order, $performedBy);
            }
        };
        $this->app->instance(LedgerService::class, $ledger);
        Order::saving(function (Order $order) use ($ledger) {
            if ($ledger->calls <= 2 && $order->isDirty('settlement_failed_at') && $order->settlement_failed_at !== null) {
                throw new \RuntimeException('orders unavailable (simulated): the retry marker cannot be written');
            }
        });
        Log::spy();
        $txn = (string) Str::uuid();

        $this->perform($order, $txn)->assertOk()->assertExactJson(self::answer());
        $this->assertSame(PaymentStatus::PAID, $this->paylovPayment($txn)->status);
        $this->assertSame(0, $this->settlements($order));
        $this->assertNull($order->fresh()->settlement_failed_at);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains((string) $message, 'PaymentPaid'));

        // Paylov's repeated Perform credits the store, once
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->assertSame(1, $this->settlements($order));
        $this->perform($order, $txn)->assertExactJson(self::answer());

        // The two failed calls, then twice per replay: the settlement itself and the delivery booking run again in
        // its own savepoint (SettlementRecoveryService::book), a no-op
        $this->assertSame(6, $ledger->calls);
        $this->assertSame(1, $this->settlements($order));
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->settlement_failed_at);
    }

    public function test_a_new_order_is_auto_accepted_once(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->perform($order, $txn)->assertExactJson(self::answer());

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'accepted')->count());
    }

    public function test_perform_refuses_another_amount_and_records_nothing(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn, 4999900)->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->perform($order, $txn, overrides: ['amount_tiyin' => 5000000.25])->assertExactJson(self::answer('5', 'Invalid amount'));
        $this->perform($order, $txn, overrides: ['transaction_id' => null])->assertExactJson(self::answer('+1', 'transaction_id_required'));

        $this->assertNull($this->paylovPayment($txn));
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_a_transaction_of_one_order_never_pays_another(): void
    {
        $first = $this->paylovOrder($this->store);
        $second = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($first, $txn)->assertExactJson(self::answer());
        $this->perform($second, $txn)->assertExactJson(self::answer('+1', 'transaction_belongs_to_another_order'));

        $this->assertSame(PaymentStatus::PENDING, $second->fresh()->payment_status);
        $this->assertSame((int) $first->id, (int) $this->paylovPayment($txn)->order_id);
    }

    // ---- unconfirmed payments ----

    public function test_a_perform_paylov_cannot_confirm_stays_unconfirmed_and_the_recheck_settles_it_once(): void
    {
        Event::fake([PaymentPaid::class]);
        Log::spy();
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovStatus = 'connection';

        // The answer never depends on the status read: the perform is recorded and answered
        $this->perform($order, $txn)->assertOk()->assertExactJson(self::answer());

        $payment = $this->paylovPayment($txn);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame('unconfirmed', $payment->payload['paylov']['state']);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        Event::assertNotDispatched(PaymentPaid::class);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'unconfirmed'));

        // Paylov's status endpoint answers an error: still unconfirmed
        $this->paylovStatus = 'http500';
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame('unconfirmed', $payment->fresh()->payload['paylov']['state']);

        // Not settled yet at Paylov (pending, waiting...): still unconfirmed
        foreach (['created', 'transfer', 'pending', 'waiting'] as $status) {
            $this->paylovStatus = $status;
            Artisan::call('payments:paylov-reconcile');
            $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status, $status);
        }

        // The unpaid-order timeout never cancels an order whose money may have been taken
        $this->travel(31)->minutes();
        (new CancelUnpaidOnlineOrdersJob())->handle(app(OrderStatusService::class));
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        $this->paylovStatus = 'payed';
        Artisan::call('payments:paylov-reconcile');
        Artisan::call('payments:paylov-reconcile');

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertSame('confirmed', $payment->fresh()->payload['paylov']['state']);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        Event::assertDispatchedTimes(PaymentPaid::class, 1);
    }

    public function test_a_payment_unconfirmed_for_over_an_hour_is_alerted_once_and_still_checked(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovStatus = 'http500';
        $this->perform($order, $txn)->assertExactJson(self::answer());

        Log::spy();
        Artisan::call('payments:paylov-reconcile');
        Log::shouldNotHaveReceived('critical');

        $this->travel(61)->minutes();
        Artisan::call('payments:paylov-reconcile');
        Artisan::call('payments:paylov-reconcile');
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'unconfirmed for over an hour'))->once();
        $this->assertCount(4, $this->sentTo('/merchant/getTransactions/'));

        // Scheduled every five minutes
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'payments:paylov-reconcile'));
        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events->first()->expression);
    }

    public function test_the_stale_alert_never_overwrites_what_was_written_meanwhile(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovStatus = 'http500';
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->travel(61)->minutes();

        // Another writer (a cancel webhook, a refund) changes the payment while the alert goes out
        Log::listen(function ($event) use ($txn) {
            if ($event->level === 'critical' && str_contains($event->message, 'unconfirmed for over an hour')) {
                $payment = $this->paylovPayment($txn);
                $payload = $payment->payload;
                $payload['paylov']['written_meanwhile'] = true;
                $payment->forceFill(['payload' => $payload])->save();
            }
        });
        Artisan::call('payments:paylov-reconcile');

        $state = $this->paylovPayment($txn)->payload['paylov'];
        $this->assertTrue($state['written_meanwhile'] ?? false, 'the alert saved a stale payload');
        $this->assertNotEmpty($state['stale_alerted_at'] ?? null);
    }

    public function test_a_transaction_paylov_reports_failed_or_cancelled_is_closed_and_the_order_stays_unpaid(): void
    {
        foreach (['failed' => PaymentStatus::FAILED, 'cancel' => PaymentStatus::CANCELLED, 'refunded' => PaymentStatus::CANCELLED] as $status => $expected) {
            $order = $this->paylovOrder($this->store);
            $txn = (string) Str::uuid();
            $this->paylovStatus = $status;

            $this->perform($order, $txn)->assertExactJson(self::answer());

            $payment = $this->paylovPayment($txn);
            $this->assertSame($expected, $payment->status, $status);
            $this->assertSame('rejected', $payment->payload['paylov']['state'], $status);
            $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status, $status);
        }

        // An empty list (Paylov does not know the transaction) is not a rejection: it stays unconfirmed
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovStatus = null;
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->assertSame('unconfirmed', $this->paylovPayment($txn)->payload['paylov']['state']);
    }

    public function test_a_confirmation_for_another_amount_is_never_settled_and_given_back_in_full(): void
    {
        Log::spy();
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovAmount = 100;

        $this->perform($order, $txn)->assertExactJson(self::answer());

        // Review 1 note: never left pending with the money in it. Paylov took another amount for this order: never the
        // order's payment, given back whole through payment/cancel, and the team is alerted
        $payment = $this->paylovPayment($txn);
        $this->assertSame('mismatch', $payment->payload['paylov']['state']);
        $this->assertSame('amount_mismatch', $payment->payload['paylov']['refused']);
        $this->assertSame('100', $payment->payload['paylov']['reported_tiyin']);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'Paylov'));

        // A replay of the perform gets the first answer; the re-check never settles it
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->paylovAmount = null;
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
    }

    public function test_a_second_transaction_for_a_paid_order_is_recorded_and_refunded_never_counted(): void
    {
        Event::fake([PaymentPaid::class]);
        $order = $this->paylovOrder($this->store);
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();

        $this->perform($order, $first)->assertExactJson(self::answer());
        $this->perform($order, $second)->assertExactJson(self::answer('+1', 'order_already_paid'));

        // Paylov took the money anyway: kept, given back in full through payment/cancel, never a second payment of the order
        $duplicate = $this->paylovPayment($second);
        $this->assertSame(PaymentStatus::REFUNDED, $duplicate->status);
        $this->assertSame('order_already_paid', $duplicate->payload['paylov']['refused']);
        $cancel = $this->sentTo('/merchant/payment/cancel/');
        $this->assertCount(1, $cancel);
        $this->assertSame(['transactionId' => $second], $cancel[0]->data());
        $this->assertSame('Bearer ' . $this->accessToken, $cancel[0]->header('Authorization')[0]);

        $this->assertSame(PaymentStatus::PAID, $this->paylovPayment($first)->status);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        Event::assertDispatchedTimes(PaymentPaid::class, 1);
    }

    public function test_a_perform_for_an_order_cancelled_meanwhile_is_recorded_and_given_back(): void
    {
        $order = $this->paylovOrder($this->store, ['status' => OrderStatus::CANCELED->value]);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn)->assertExactJson(self::answer('+1', 'order_not_payable'));

        $this->assertSame(PaymentStatus::REFUNDED, $this->paylovPayment($txn)->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));

        // Paylov never took it: closed, nothing given back
        $other = $this->paylovOrder($this->store, ['status' => OrderStatus::CANCELED->value]);
        $refused = (string) Str::uuid();
        $this->paylovStatus = 'failed';
        $this->perform($other, $refused)->assertExactJson(self::answer('+1', 'order_not_payable'));
        $this->assertSame(PaymentStatus::FAILED, $this->paylovPayment($refused)->status);
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
    }

    public function test_an_unexpected_failure_answers_maintenance_and_records_nothing(): void
    {
        $order = $this->paylovOrder($this->store);
        Payment::creating(function () {
            throw new \RuntimeException('database is down (simulated)');
        });

        $this->perform($order, (string) Str::uuid())->assertOk()->assertExactJson(self::answer('3', 'Maintenance is in process'));
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
    }

    public function test_an_unknown_method_and_a_body_that_is_not_json_are_refused(): void
    {
        $this->paylovRpc('transaction.refund', [])->assertExactJson(self::answer('+1', 'method_not_found'));
        $this->call('POST', '/api/v1/payments/paylov/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($this->paylov['callback_login'] . ':' . $this->paylov['callback_password']),
            'CONTENT_TYPE' => 'application/json',
        ], '{not json')->assertExactJson(['jsonrpc' => '2.0', 'id' => null, 'result' => ['status' => '+1', 'statusText' => 'parse_error']]);
    }

    // ---- transaction.cancel webhook ----

    public function test_the_cancel_webhook_refunds_a_paid_order_once_whatever_the_retries(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);

        // Retried up to 3 times by Paylov: every delivery is a 2xx, the first one counts (Paylov reports it cancelled)
        $this->paylovStatus = 'cancel';
        foreach (range(1, 3) as $attempt) {
            $this->cancelWebhook($order, $txn)->assertOk();
        }

        $payment = $this->paylovPayment($txn);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertNotNull($payment->cancelled_at);
        $this->assertSame('webhook', $payment->payload['paylov']['cancelled_by']);
        // As Payme's CancelTransaction: the order is no longer paid
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        $this->assertSame(1, Payment::where('provider', 'paylov')->where('status', PaymentStatus::REFUNDED->value)->count());
        // Security review F1: money given back before delivery is no part of what delivery books the commission on
        $this->assertTrue($payment->payload['refunded_before_delivery'] ?? false);
    }

    public function test_the_cancel_webhook_of_a_delivered_order_refunds_the_order_and_reverses_the_store_once(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());
        foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
        $this->assertSame(1, $this->settlements($order));
        $wallet = (string) $this->store->fresh()->wallet_balance;

        $this->paylovStatus = 'refunded';
        $this->cancelWebhook($order, $txn)->assertOk();
        $this->cancelWebhook($order, $txn)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertNotSame($wallet, (string) $this->store->fresh()->wallet_balance);
        // Paylov already returned the money: nothing is flagged for a manual refund, and Paylov is not asked again
        $this->assertArrayNotHasKey('manual_refund_required', $this->paylovPayment($txn)->payload);
        $this->assertCount(0, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->count());
    }

    public function test_the_cancel_webhook_closes_an_unconfirmed_payment_and_ignores_unknown_transactions(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->paylovStatus = 'pending';
        $this->perform($order, $txn)->assertExactJson(self::answer());

        $this->paylovStatus = 'cancel';
        $this->cancelWebhook($order, $txn)->assertOk();
        $payment = $this->paylovPayment($txn);
        $this->assertSame(PaymentStatus::CANCELLED, $payment->status);
        $this->assertSame('cancelled', $payment->payload['paylov']['state']);

        // The re-check never revives it
        $this->paylovStatus = 'payed';
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(PaymentStatus::CANCELLED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);

        // A cancel for a transaction we never recorded: a 2xx, nothing written
        $this->cancelWebhook($order, (string) Str::uuid())->assertOk();
        $this->assertSame(1, Payment::where('provider', 'paylov')->whereNotNull('provider_transaction_id')->count());
    }

    public function test_the_cancel_webhook_is_authorised_by_the_account_of_the_payments_order(): void
    {
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());

        $this->cancelWebhook($order, $txn, account: self::makeAccount())->assertStatus(401);
        $this->assertSame(PaymentStatus::PAID, $this->paylovPayment($txn)->status);
    }

    public function test_the_webhook_is_rate_limited_like_the_other_providers(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'api/v1/payments/paylov/webhook');
        $this->assertCount(1, $routes);
        $this->assertContains('throttle:payment-webhooks', $routes->first()->gatherMiddleware());
    }

    public function test_secrets_never_reach_the_logs(): void
    {
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event->message . ' ' . json_encode($event->context);
        });
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn);
        $this->paylovStatus = 'connection';
        $this->perform($this->paylovOrder($this->store), (string) Str::uuid());
        $this->cancelWebhook($order, $txn);

        $all = implode("\n", $logged);
        foreach (['consumer_key', 'consumer_secret', 'password', 'callback_password'] as $key) {
            $this->assertStringNotContainsString($this->paylov[$key], $all, $key);
        }
        $this->assertStringNotContainsString($this->accessToken, $all);
    }
}
