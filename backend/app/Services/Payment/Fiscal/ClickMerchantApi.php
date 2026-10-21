<?php

namespace App\Services\Payment\Fiscal;

use App\Models\Order;
use App\Models\PaymentProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Click Merchant API calls for fiscal data (https://docs.click.uz/merchant-api/fiscalization): every request carries
 * `Auth: merchant_user_id:sha1(timestamp + secret_key):timestamp`.
 */
class ClickMerchantApi
{
    public const BASE_URL = 'https://api.click.uz/v2/merchant';

    /**
     * The merchant credentials the order's money went to: the store's own Click account (payment_settings.click,
     * with merchant_user_id from the Click merchant cabinet), else the platform's (services.click / superadmin).
     *
     * @return array{merchant_user_id: string, secret_key: string, service_id: string}|null
     */
    public function credentials(Order $order): ?array
    {
        if ($order->payment_owner !== 'PLATFORM') {
            $store = $order->store;
            if (!$store?->collectsOwnPayments('click')) {
                return null;
            }
            $config = $store->payment_settings['click'];
            $credentials = [
                'merchant_user_id' => trim((string) ($config['merchant_user_id'] ?? '')),
                'secret_key' => trim((string) $config['secret_key']),
                'service_id' => trim((string) $config['service_id']),
            ];
        } else {
            $credentials = [
                'merchant_user_id' => (string) PaymentProvider::platformCredential('click', 'merchant_user_id'),
                'secret_key' => (string) PaymentProvider::platformCredential('click', 'secret_key'),
                'service_id' => (string) PaymentProvider::platformCredential('click', 'service_id'),
            ];
        }

        return in_array('', $credentials, true) ? null : $credentials;
    }

    /**
     * POST ofd_data/submit_items: Click registers the receipt with these items; answers {error_code, error_note}.
     *
     * @param  array{merchant_user_id: string, secret_key: string, service_id: string}  $credentials
     * @param  list<array<string, mixed>>  $items
     */
    public function submitItems(array $credentials, int $serviceId, int $paymentId, array $items, int $receivedCard): Response
    {
        return $this->request($credentials)->post(self::BASE_URL . '/payment/ofd_data/submit_items', [
            'service_id' => $serviceId,
            'payment_id' => $paymentId,
            'items' => $items,
            'received_ecash' => 0,
            'received_cash' => 0,
            'received_card' => $receivedCard,
        ]);
    }

    /**
     * GET ofd_data/:service_id/:payment_id: {paymentId, qrCodeURL} once the receipt is registered.
     *
     * @param  array{merchant_user_id: string, secret_key: string, service_id: string}  $credentials
     */
    public function fiscalData(array $credentials, int $serviceId, int $paymentId): Response
    {
        return $this->request($credentials)->get(self::BASE_URL . "/payment/ofd_data/{$serviceId}/{$paymentId}");
    }

    /**
     * @param  array{merchant_user_id: string, secret_key: string, service_id: string}  $credentials
     */
    public function authHeader(array $credentials, int $timestamp): string
    {
        return $credentials['merchant_user_id'] . ':' . sha1($timestamp . $credentials['secret_key']) . ':' . $timestamp;
    }

    /**
     * @param  array{merchant_user_id: string, secret_key: string, service_id: string}  $credentials
     */
    protected function request(array $credentials): PendingRequest
    {
        return Http::connectTimeout(5)
            ->timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Auth' => $this->authHeader($credentials, now()->getTimestamp())]);
    }

    /**
     * Terminal, receipt number, date and fiscal sign as the OFD receipt link carries them
     * (https://ofd.soliq.uz/epi?t=EZ000000000030&r=123456789&c=20221028171340&s=854971301623).
     *
     * @return array{terminal_id: ?string, receipt_id: ?string, date: ?string, fiscal_sign: ?string}
     */
    public static function parseQrUrl(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $value = fn (string $key) => isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '' ? $query[$key] : null;

        return [
            'terminal_id' => $value('t'),
            'receipt_id' => $value('r'),
            'date' => $value('c'),
            'fiscal_sign' => $value('s'),
        ];
    }
}
