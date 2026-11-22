<?php

namespace Tests\Feature\Paylov;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovAccount;
use App\Services\Payment\Paylov\PaylovApi;
use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Review 2 (security) of the Paylov wave: each test asserts the secure behaviour.
 */
class PaylovSecurityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PaylovFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->setUpPaylov();
    }

    /**
     * H1: a store owner on its own Paylov account knows its own callback login/password (it typed them into
     * POST /owner/settings). transaction.cancel is applied on Basic Auth alone, never read back from Paylov, so the owner
     * can "refund" every delivered order: the platform's commission is reversed (store debt goes down) and the order shows
     * refunded, while the customer's money stays in the store's Paylov account (Paylov still reports `payed`).
     */
    public function test_a_forged_cancel_webhook_is_not_applied_while_paylov_still_reports_the_transaction_payed(): void
    {
        $own = self::makeAccount();
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true] + $own]),
        ])->save();

        $order = $this->paylovOrder($this->store, ['payment_owner' => 'STORE']);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn, account: $own)->assertExactJson(self::answer());
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
        $debtAfterDelivery = (string) $this->store->fresh()->debt_balance;
        $readsBefore = count($this->sentTo('/merchant/getTransactions/'));

        // The owner forges the cancel (Paylov's getTransactions keeps answering `payed`: $this->paylovStatus)
        $this->cancelWebhook($order, $txn, account: $own)->assertOk();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status, 'order refunded on an unverified cancel');
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider_transaction_id', $txn)->first()->status);
        $this->assertSame($debtAfterDelivery, (string) $this->store->fresh()->debt_balance, 'commission reversed on an unverified cancel');
        $this->assertGreaterThan($readsBefore, count($this->sentTo('/merchant/getTransactions/')), 'cancel never read back from Paylov');
    }

    /**
     * M1: the token cache key is base_url|consumer_key|username only. An account with the same consumer_key and username
     * but a WRONG consumer_secret and password is handed the other account's cached bearer token, without any token
     * request. A store that learns another account's consumer_key + API login (no secret, no password) acts on that
     * account (getTransactions, payment/cancel, fiscalization) with its token.
     */
    public function test_an_account_with_wrong_secret_and_password_never_gets_another_accounts_cached_token(): void
    {
        $victim = self::makeAccount();
        $victimStore = $this->makeStore('Victim');
        $victimStore->forceFill(['payment_account_type' => 'own_account', 'payment_settings' => ['paylov' => ['enabled' => true] + $victim]])->save();
        $victimOrder = $this->paylovOrder($victimStore, ['payment_owner' => 'STORE']);

        $attacker = ['consumer_secret' => 'wrong' . Str::random(30), 'password' => 'wrong' . Str::random(16)] + $victim;
        $attacker['callback_login'] = 'cb' . Str::random(8);
        $attacker['callback_password'] = Str::random(28);
        $attackerStore = $this->makeStore('Attacker');
        $attackerStore->forceFill(['payment_account_type' => 'own_account', 'payment_settings' => ['paylov' => ['enabled' => true] + $attacker]])->save();
        $attackerOrder = $this->paylovOrder($attackerStore, ['payment_owner' => 'STORE']);

        $api = app(PaylovApi::class);
        $api->transaction(PaylovAccount::forOrder($victimOrder->fresh()), (string) Str::uuid()); // the victim's token is cached
        $this->assertCount(1, $this->sentTo('/merchant/oauth2/token/'));

        $api->transaction(PaylovAccount::forOrder($attackerOrder->fresh()), (string) Str::uuid());

        $this->assertCount(2, $this->sentTo('/merchant/oauth2/token/'), "the attacker's call reused the victim's cached token");
    }

    /**
     * H1 follow-up: a cancel Paylov cannot be asked about right now (getTransactions does not answer) is answered 2xx,
     * changes nothing yet, and is verified by the scheduled re-check once Paylov answers.
     */
    public function test_a_cancel_webhook_paylov_cannot_read_back_now_is_verified_by_the_recheck(): void
    {
        $this->store->forceFill(['payment_account_type' => 'platform_account', 'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true]])])->save();
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());

        $this->paylovStatus = 'connection';
        $this->cancelWebhook($order, $txn)->assertOk();
        $payment = Payment::where('provider_transaction_id', $txn)->first();
        $this->assertSame(PaymentStatus::PAID, $payment->status, 'applied before Paylov said so');
        $this->assertNotEmpty(PaylovPayments::state($payment)['cancel_reported_at'] ?? null);

        // Still not answering: the re-check keeps it
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);

        // Paylov answers: it was cancelled for real
        $this->paylovStatus = 'cancel';
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        $this->assertArrayNotHasKey('cancel_reported_at', PaylovPayments::state($payment->fresh()));
    }

    /**
     * L1: what the webhook logs before the Basic Auth check comes from anyone: each field is capped.
     */
    public function test_what_is_logged_before_the_auth_check_is_capped(): void
    {
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            if ($event->message === 'Paylov Webhook Received') {
                $logged[] = $event->context;
            }
        });
        $long = str_repeat('a', 5000);

        $this->postJson('/api/v1/payments/paylov/webhook', [
            'jsonrpc' => '2.0',
            'id' => $long,
            'method' => $long,
            'params' => ['transaction_id' => $long, 'account' => ['order_id' => $long]],
        ])->assertStatus(401);

        $this->assertCount(1, $logged);
        foreach ($logged[0] as $key => $value) {
            $this->assertLessThanOrEqual(64, mb_strlen((string) $value), $key);
        }
    }

    /**
     * L2: a request without the right Basic Auth costs a few queries only (the platform's provider row read once, not
     * once per key).
     */
    public function test_a_request_refused_by_the_auth_check_reads_the_database_a_few_times_only(): void
    {
        $order = $this->paylovOrder($this->store);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->perform($order, (string) Str::uuid(), account: self::makeAccount())->assertStatus(401);

        $this->assertLessThanOrEqual(4, $queries);
    }

    /**
     * L3: perform's params are kept on the payment for the record: only the fields Paylov documents, each capped, never
     * whatever size the body had.
     */
    public function test_perform_params_are_kept_bounded(): void
    {
        $this->store->forceFill(['payment_account_type' => 'platform_account', 'credit_limit' => -1000000])->save();
        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();

        $this->perform($order, $txn, overrides: [
            'junk' => str_repeat('j', 200000),
            'account' => ['order_id' => $order->uuid, 'extra' => str_repeat('e', 200000)],
        ])->assertExactJson(self::answer());

        $kept = Payment::where('provider_transaction_id', $txn)->first()->payload['perform_params'];
        $this->assertLessThan(1024, strlen((string) json_encode($kept)));
        $this->assertSame($txn, $kept['transaction_id']);
        $this->assertSame($order->uuid, $kept['account']['order_id']);
        $this->assertArrayNotHasKey('junk', $kept);
    }

    /**
     * L4: the webhook password an owner sets for its own Paylov account guards its money: at least 16 characters.
     */
    public function test_an_owners_paylov_webhook_password_needs_sixteen_characters(): void
    {
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();
        $own = self::makeAccount();

        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['paylov' => ['enabled' => true, 'callback_password' => 'short' . Str::random(5)] + $own],
        ])->assertStatus(422)->assertJsonValidationErrors('payment_settings.paylov.callback_password');
        $this->assertArrayNotHasKey('paylov', $this->store->fresh()->payment_settings ?? []);

        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['paylov' => ['enabled' => true] + $own],
        ])->assertOk();
        // A masked password keeps the stored one
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['paylov' => ['enabled' => true, 'callback_password' => '********']],
        ])->assertOk();
        $this->assertSame($own['callback_password'], $this->store->fresh()->payment_settings['paylov']['callback_password']);
    }

    /**
     * Review 12 of the UzQR wave (MEDIUM, the same exposure): the token request carries the API login's password in
     * its body (and Basic auth), the calls a bearer token; a redirect (307 re-sends the body, to http too) is never
     * followed: a 3xx is a refusal.
     */
    public function test_no_paylov_request_follows_a_redirect(): void
    {
        $elsewhere = 'http://attacker.example.test/steal';
        foreach (['/merchant/oauth2/token/', '/merchant/getTransactions/'] as $path) {
            $this->paylovRoutes = [$path => fn () => Http::response('', 307, ['Location' => $elsewhere])];
            \Illuminate\Support\Facades\Cache::flush();
            if ($path !== '/merchant/oauth2/token/') {
                $this->paylovRoutes['/merchant/oauth2/token/'] = fn () => Http::response(['access_token' => $this->accessToken, 'expires_in' => 3600]);
            }

            $result = app(PaylovApi::class)->transaction(PaylovAccount::platform(), (string) Str::uuid());

            $this->assertFalse($result->ok, $path);
        }
        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_starts_with($request->url(), 'http://attacker.'));
    }
}
