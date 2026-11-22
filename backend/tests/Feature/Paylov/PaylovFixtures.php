<?php

namespace Tests\Feature\Paylov;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Wave P: Paylov as the third online provider. Every credential is made at runtime (never a key-shaped literal), every
 * Paylov call is answered by Http::fake (no network): the token (subscribe/authorization), getTransactions
 * (subscribe/payment/transaction-status), payment/cancel (subscribe/cancel-payment) and the fiscalization pages.
 */
trait PaylovFixtures
{
    protected const BASE = 'https://gw2.paylov.uz';

    /** @var array<string, string> the platform account */
    protected array $paylov = [];

    protected string $accessToken = '';

    /** What the fake getTransactions answers: status, or null for an empty list; 'http500' / 'connection' for failures */
    protected ?string $paylovStatus = 'payed';

    /** amount_in_tiyin getTransactions reports (null: the payment's own amount) */
    protected ?int $paylovAmount = null;

    /** What the fake payment/cancel answers: null for success, else an error code; 'connection' for a network failure */
    protected ?string $paylovCancelError = null;

    /** @var array<string, \Closure(Request): mixed> extra answers by path (fiscalization...) */
    protected array $paylovRoutes = [];

    /** @var array<string, int> tiyin by transaction id, as perform reported it (what getTransactions echoes back) */
    protected array $performed = [];

    protected function setUpPaylov(): void
    {
        $this->paylov = self::makeAccount();
        $this->accessToken = Str::random(48);
        foreach ($this->paylov as $key => $value) {
            config(["services.paylov.{$key}" => $value]);
        }
        config(['services.paylov.base_url' => self::BASE]);

        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->answerPaylov($request));
    }

    /**
     * @return array<string, string>
     */
    protected static function makeAccount(): array
    {
        return [
            'merchant_id' => (string) Str::uuid(),
            'consumer_key' => 'ck' . Str::random(24),
            'consumer_secret' => Str::random(44),
            'username' => 'user' . Str::random(6),
            'password' => Str::random(20),
            'callback_login' => 'cb' . Str::random(8),
            'callback_password' => Str::random(28),
        ];
    }

    protected function answerPaylov(Request $request): mixed
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if (isset($this->paylovRoutes[$path])) {
            return ($this->paylovRoutes[$path])($request);
        }

        if ($path === '/merchant/oauth2/token/') {
            return Http::response([
                'access_token' => $this->accessToken,
                'refresh_token' => Str::random(48),
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_expires_in' => 2592000,
            ]);
        }

        if ($path === '/merchant/getTransactions/') {
            if ($this->paylovStatus === 'connection') {
                return Http::failedConnection('Connection timed out (simulated)');
            }
            if ($this->paylovStatus === 'http500') {
                return Http::response(['result' => null, 'error' => ['code' => 'server_error', 'message' => 'An unexpected server error.']], 500);
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $id = (string) ($query['id'] ?? '');
            if ($this->paylovStatus === null) {
                return Http::response(['result' => ['totalTransaction' => 0, 'transactions' => []], 'error' => null]);
            }
            $tiyin = $this->paylovAmount ?? ($this->performed[$id] ?? 0);

            return Http::response(['result' => ['totalTransaction' => 1, 'transactions' => [[
                'id' => $id,
                'external_id' => null,
                'userId' => null,
                'card_id' => (string) Str::uuid(),
                'cardNumber' => '986030******9999',
                'owner' => 'Levi A.',
                'amount' => intdiv($tiyin, 100) + 0.0,
                'amount_in_tiyin' => $tiyin,
                'status' => $this->paylovStatus,
                'account' => ['order_id' => 'x'],
                'cancel_time' => null,
                'error_message' => null,
                'date' => '2026-11-22 06:38:38',
                'is_split' => false,
                'is_hold' => false,
                'is_a2c' => false,
                'transaction_type' => "To'lov",
                'via_link' => true,
                'ofd' => [],
            ]]], 'error' => null]);
        }

        if ($path === '/merchant/payment/cancel/') {
            if ($this->paylovCancelError === 'connection') {
                return Http::failedConnection('Connection timed out (simulated)');
            }
            if ($this->paylovCancelError !== null) {
                return Http::response(['error' => ['code' => $this->paylovCancelError, 'message' => $this->paylovCancelError], 'result' => null], 400);
            }

            return Http::response(['error' => null, 'result' => [
                'status' => 'cancelled',
                'cancelTime' => '2026-11-22 11:24:03',
                'transactionId' => $request['transactionId'],
            ]]);
        }

        return Http::response(['result' => null, 'error' => ['code' => 'not_found', 'message' => 'not found']], 404);
    }

    /**
     * @param  array<string, string>|null  $account  whose callback login/password signs the request (platform's by default)
     */
    protected function paylovRpc(string $method, array $params, ?array $account = null, bool $withId = true): TestResponse
    {
        $account ??= $this->paylov;
        $body = ['jsonrpc' => '2.0'] + ($withId ? ['id' => 1234567] : []) + ['method' => $method, 'params' => $params];

        return $this->postJson('/api/v1/payments/paylov/webhook', $body, [
            'Authorization' => 'Basic ' . base64_encode($account['callback_login'] . ':' . $account['callback_password']),
        ]);
    }

    protected function check(Order $order, ?int $tiyin = null, array $overrides = [], ?array $account = null): TestResponse
    {
        $tiyin ??= (int) round((float) $order->total_price * 100);

        return $this->paylovRpc('transaction.check', array_merge([
            'account' => ['order_id' => $order->uuid],
            'amount' => intdiv($tiyin, 100),
            'amount_tiyin' => $tiyin,
            'currency' => 860,
        ], $overrides), $account);
    }

    protected function perform(Order $order, string $transactionId, ?int $tiyin = null, array $overrides = [], ?array $account = null): TestResponse
    {
        $tiyin ??= (int) round((float) $order->total_price * 100);
        $this->performed[$transactionId] = $tiyin;

        return $this->paylovRpc('transaction.perform', array_merge([
            'transaction_id' => $transactionId,
            'account' => ['order_id' => $order->uuid],
            'amount' => intdiv($tiyin, 100),
            'amount_tiyin' => $tiyin,
            'currency' => 860,
        ], $overrides), $account);
    }

    protected function cancelWebhook(Order $order, string $transactionId, ?int $tiyin = null, ?array $account = null): TestResponse
    {
        $tiyin ??= (int) round((float) $order->total_price * 100);

        return $this->paylovRpc('transaction.cancel', [
            'transaction_id' => $transactionId,
            'status' => 'cancelled',
            'account' => ['order_id' => $order->uuid],
            'amount' => intdiv($tiyin, 100),
            'amount_in_tiyin' => $tiyin,
            'cancel_time' => 1767162243,
        ], $account, false);
    }

    /**
     * @return array{jsonrpc: string, id: int, result: array{status: string, statusText: string}}
     */
    protected static function answer(string $status = '0', string $text = 'OK'): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1234567, 'result' => ['status' => $status, 'statusText' => $text]];
    }

    protected function paylovOrder(Store $store, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'total_price' => '50000.00',
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => 'paylov',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    /**
     * Requests sent to Paylov's API (the token and getTransactions...), by path.
     *
     * @return list<Request>
     */
    protected function sentTo(string $path): array
    {
        return Http::recorded(fn (Request $request) => parse_url($request->url(), PHP_URL_PATH) === $path)
            ->map(fn (array $pair) => $pair[0])->values()->all();
    }
}
