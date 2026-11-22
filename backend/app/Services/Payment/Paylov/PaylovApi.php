<?php

namespace App\Services\Payment\Paylov;

use App\Models\Payment;
use App\Services\Payment\PaymentProviderSwitch;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wave P: the Paylov merchant API calls RIZQIM makes (BASE_URL = config services.paylov.base_url). Each method cites
 * the developer.paylov.uz page it follows. Answers come back as a PaylovResult; nothing here throws on a network or
 * Paylov error, and no credential, token or card detail is ever logged.
 *
 * Paylov has no public sandbox (the docs show production hosts only). The platform provider in `test` mode on a
 * non-production server (PaymentProviderSwitch::paylovFake) uses the in-process fake below instead of any host; on
 * APP_ENV=production the fake is never used, whatever the switch says.
 */
class PaylovApi
{
    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 20;

    public function __construct(private readonly PaymentProviderSwitch $providers) {}

    public function baseUrl(): string
    {
        return rtrim((string) config('services.paylov.base_url'), '/');
    }

    public function isFake(): bool
    {
        return $this->providers->paylovFake();
    }

    /**
     * subscribe/payment/transaction-status: GET BASE_URL/merchant/getTransactions/?id=<uuid>, Bearer token. The answer
     * is {result: {totalTransaction, transactions: [{id, amount, amount_in_tiyin, status, account, ...}]}, error}.
     */
    public function transaction(PaylovAccount $account, string $transactionId): PaylovResult
    {
        if ($this->isFake()) {
            return $this->fakeTransaction($transactionId);
        }

        return $this->call($account, fn (PendingRequest $http) => $http->get($this->baseUrl() . '/merchant/getTransactions/', ['id' => $transactionId]));
    }

    /**
     * subscribe/cancel-payment: POST BASE_URL/merchant/payment/cancel/ {transactionId}. A full refund: the page has no
     * amount field. Success: {error: null, result: {status: "cancelled", cancelTime, transactionId}}; errors
     * permission_denied, transaction_not_found, transaction_not_available_for_cancel.
     */
    public function cancel(PaylovAccount $account, string $transactionId): PaylovResult
    {
        if ($this->isFake()) {
            return PaylovResult::success(['status' => 'cancelled', 'cancelTime' => now()->format('Y-m-d H:i:s'), 'transactionId' => $transactionId]);
        }

        return $this->call($account, fn (PendingRequest $http) => $http->post($this->baseUrl() . '/merchant/payment/cancel/', ['transactionId' => $transactionId]));
    }

    /**
     * subscribe/ofd/register: POST BASE_URL/merchant/fiscalization/register/ {transactionId, items} for a card payment.
     * Success: {result: {ofd: {receiptUrl, terminalId, receiptId, fiscalSign}}}.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function registerReceipt(PaylovAccount $account, string $transactionId, array $items): PaylovResult
    {
        if ($this->isFake()) {
            return PaylovResult::success(['ofd' => $this->fakeReceipt()]);
        }

        return $this->call($account, fn (PendingRequest $http) => $http->post($this->baseUrl() . '/merchant/fiscalization/register/', [
            'transactionId' => $transactionId,
            'items' => $items,
        ]));
    }

    /**
     * subscribe/ofd/get-receipt: GET BASE_URL/merchant/fiscalization/status/?refunded=<bool>&transactionId=<uuid>.
     * Answer: {result: {success, isRefund, receiptUrl, terminalId, receiptId, fiscalSign}}.
     */
    public function receiptStatus(PaylovAccount $account, string $transactionId, bool $refunded): PaylovResult
    {
        if ($this->isFake()) {
            return PaylovResult::success(['success' => true, 'isRefund' => $refunded] + $this->fakeReceipt());
        }

        return $this->call($account, fn (PendingRequest $http) => $http->get($this->baseUrl() . '/merchant/fiscalization/status/', [
            'refunded' => $refunded ? 'true' : 'false',
            'transactionId' => $transactionId,
        ]));
    }

    /**
     * subscribe/ofd/refund-receipt: POST BASE_URL/merchant/fiscalization/refund/ {receiptId}. Answer:
     * {result: {ofd: {refund: {receiptUrl, terminalId, receiptId, fiscalSign}}, sale: {...}}}.
     */
    public function refundReceipt(PaylovAccount $account, int $receiptId): PaylovResult
    {
        if ($this->isFake()) {
            return PaylovResult::success(['ofd' => ['refund' => $this->fakeReceipt()], 'sale' => $this->fakeReceipt()]);
        }

        return $this->call($account, fn (PendingRequest $http) => $http->post($this->baseUrl() . '/merchant/fiscalization/refund/', ['receiptId' => $receiptId]));
    }

    /**
     * One authenticated call; a 401 with a cached token (revoked, or expired early) gets one more try with a new one.
     *
     * @param  \Closure(PendingRequest): Response  $send
     */
    private function call(PaylovAccount $account, \Closure $send): PaylovResult
    {
        if (!$account->hasApiCredentials()) {
            return PaylovResult::failure('credentials_missing', 0, false);
        }

        foreach ([false, true] as $fresh) {
            $token = $this->token($account, $fresh);
            if ($token instanceof PaylovResult) {
                return $token;
            }

            try {
                $response = $send($this->http()->withToken($token));
            } catch (ConnectionException) {
                return PaylovResult::unreachable();
            }

            if ($response->status() === 401 && !$fresh) {
                Cache::forget($account->tokenCacheKey($this->baseUrl()));

                continue;
            }

            return $this->result($response);
        }

        return PaylovResult::failure('permission_denied', 401, false);
    }

    /**
     * subscribe/authorization §2: POST BASE_URL/merchant/oauth2/token/ with Basic base64(consumer_key:consumer_secret)
     * and the JSON body {grant_type: "password", username, password}; access_token lives `expires_in` seconds (1 hour).
     * Kept encrypted in the cache until a minute before it expires. A new password grant is asked for then instead of
     * the refresh grant: one request either way, and no refresh token to keep.
     */
    private function token(PaylovAccount $account, bool $fresh): string|PaylovResult
    {
        $key = $account->tokenCacheKey($this->baseUrl());
        if (!$fresh && is_string($cached = Cache::get($key))) {
            try {
                return Crypt::decryptString($cached);
            } catch (\Throwable) {
                Cache::forget($key);
            }
        }

        try {
            $response = $this->http()
                ->withBasicAuth((string) $account->get('consumer_key'), (string) $account->get('consumer_secret'))
                ->post($this->baseUrl() . '/merchant/oauth2/token/', [
                    'grant_type' => 'password',
                    'username' => $account->get('username'),
                    'password' => $account->get('password'),
                ]);
        } catch (ConnectionException) {
            return PaylovResult::unreachable();
        }

        $token = $response->successful() ? $response->json('access_token') : null;
        if (!is_string($token) || $token === '') {
            // "this account not activated", "IP address not in whitelist", "Invalid credentials": the message names
            // the cause, never a value
            Log::error('Paylov token request refused', [
                'status' => $response->status(),
                'code' => self::code($response),
                'message' => mb_substr((string) $response->json('error.message'), 0, 200),
                'store_account' => $account->isStore,
            ]);

            return PaylovResult::failure(self::code($response) ?? 'token_failed', $response->status());
        }

        $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);
        Cache::put($key, Crypt::encryptString($token), $ttl);

        return $token;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->connectTimeout(self::CONNECT_TIMEOUT)->timeout(self::TIMEOUT);
    }

    private function result(Response $response): PaylovResult
    {
        $error = $response->json('error');
        if ($response->successful() && ($error === null || $error === [])) {
            $result = $response->json('result');

            return PaylovResult::success(is_array($result) ? $result : [], $response->status());
        }

        return PaylovResult::failure(self::code($response) ?? 'http_' . $response->status(), $response->status());
    }

    private static function code(Response $response): ?string
    {
        $code = $response->json('error.code');

        return is_string($code) && $code !== '' ? strtolower($code) : null;
    }

    // --- The fake driver (no sandbox): a payment we recorded is `payed` for exactly its amount ------------------

    private function fakeTransaction(string $transactionId): PaylovResult
    {
        $payment = Payment::where('provider', 'paylov')->where('provider_transaction_id', $transactionId)->first();
        if (!$payment) {
            return PaylovResult::success(['totalTransaction' => 0, 'transactions' => []]);
        }
        $tiyin = Money::of($payment->amount)->toTiyin();

        return PaylovResult::success(['totalTransaction' => 1, 'transactions' => [[
            'id' => $transactionId,
            'amount' => $tiyin / 100,
            'amount_in_tiyin' => $tiyin,
            'status' => 'payed',
            'account' => ['order_id' => $payment->order?->uuid],
            'via_link' => true,
            'ofd' => [],
        ]]]);
    }

    /**
     * @return array{receiptUrl: string, terminalId: string, receiptId: int, fiscalSign: string}
     */
    private function fakeReceipt(): array
    {
        return [
            'receiptUrl' => 'https://ofd.soliq.uz/epi?t=FAKE&r=' . Str::random(6),
            'terminalId' => 'FAKE-PAYLOV',
            'receiptId' => random_int(100000, 999999),
            'fiscalSign' => 'FAKE' . random_int(10000000, 99999999),
        ];
    }
}
