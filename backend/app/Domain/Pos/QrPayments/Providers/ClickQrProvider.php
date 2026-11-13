<?php

namespace App\Domain\Pos\QrPayments\Providers;

use App\Domain\Pos\QrPayments\QrCheckout;
use App\Domain\Pos\QrPayments\QrPaymentProvider;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Services\Payment\Fiscal\ClickMerchantApi;
use App\Services\Payment\PaymentProviderSwitch;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: Click at the till. The QR is Click's checkout link (my.click.uz/services/pay) with `transaction_param`
 * `pos_qr_<uuid>`: the Click app opens it and Click calls the SHOP API webhook (Prepare/Complete, `merchant_trans_id`)
 * signed with the store's secret key. A refund is Click's Merchant API payment reversal
 * (DELETE /payment/reversal/{service_id}/{payment_id}, header `Auth: merchant_user_id:sha1(timestamp + secret_key):
 * timestamp`; https://docs.click.uz/merchant-api-request/): the whole payment only, so a partial refund is refused
 * before any money moves. It needs `merchant_user_id` from the Click merchant cabinet in payment_settings.click.
 * Review P6W4 C2: no answer (a timeout, a connection error, a 5xx) is `unknown`, not a refusal; the status of a
 * reversal is asked with GET /payment/status/{service_id}/{payment_id} (`payment_status` below 0: cancelled, i.e.
 * reversed; 2: still paid; an assumption to confirm with Click when the first store connects).
 */
final class ClickQrProvider implements QrPaymentProvider
{
    public function __construct(private readonly PaymentProviderSwitch $switch) {}

    public function provider(): QrProvider
    {
        return QrProvider::CLICK;
    }

    public function driver(): string
    {
        return 'click';
    }

    public function configured(Store $store): bool
    {
        return StoreMerchant::enabled($store, 'click');
    }

    public function checkout(PosQrPayment $qr, Store $store): QrCheckout
    {
        return new QrCheckout($this->switch->clickUrl(
            StoreMerchant::credential($store, 'click', 'service_id'),
            StoreMerchant::credential($store, 'click', 'merchant_id'),
            Money::of($qr->amount)->toString(),
            $qr->account(),
            StoreMerchant::returnUrl(),
        ));
    }

    public function cancel(PosQrPayment $qr, Store $store): void
    {
        // Click keeps nothing until Prepare: a cancelled request simply answers Prepare/Complete with -9
    }

    public function supportsPartialRefund(): bool
    {
        return false;
    }

    public function refund(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        $credentials = $this->credentials($store);
        if (($refused = $this->unusable($qr, $credentials)) !== null) {
            return $refused;
        }

        try {
            $response = Http::connectTimeout(5)->timeout(20)->acceptJson()
                ->withHeaders(['Auth' => app(ClickMerchantApi::class)->authHeader($credentials, now()->getTimestamp())])
                ->delete(rtrim((string) config('qr_payments.click.api_url'), '/') . "/payment/reversal/{$credentials['service_id']}/{$qr->provider_transaction_id}");
        } catch (\Throwable $e) {
            // Sent, maybe done: only Click's status says (review P6W4 C2)
            Log::warning('Click reversal not answered: ' . $e->getMessage(), ['qr' => $qr->uuid]);

            return QrRefundResult::unknown("Click javob bermadi");
        }
        if ($response->serverError()) {
            Log::warning('Click reversal answered ' . $response->status(), ['qr' => $qr->uuid]);

            return QrRefundResult::unknown("Click javob bermadi");
        }

        $code = $response->json('error_code');
        if ($response->successful() && is_numeric($code) && (int) $code === 0) {
            return QrRefundResult::done(is_scalar($response->json('payment_id')) ? (string) $response->json('payment_id') : null);
        }

        Log::warning('Click reversal refused', ['qr' => $qr->uuid, 'status' => $response->status(), 'error_code' => $code, 'error_note' => $response->json('error_note')]);
        $note = $response->json('error_note');

        return QrRefundResult::failed(is_string($note) && $note !== '' ? "Click rad etdi: {$note}" : 'Click rad etdi');
    }

    public function refundStatus(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        $credentials = $this->credentials($store);
        if (($refused = $this->unusable($qr, $credentials)) !== null) {
            return QrRefundResult::unknown((string) $refused->message);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(20)->acceptJson()
                ->withHeaders(['Auth' => app(ClickMerchantApi::class)->authHeader($credentials, now()->getTimestamp())])
                ->get(rtrim((string) config('qr_payments.click.api_url'), '/') . "/payment/status/{$credentials['service_id']}/{$qr->provider_transaction_id}");
        } catch (\Throwable $e) {
            Log::warning('Click payment status not answered: ' . $e->getMessage(), ['qr' => $qr->uuid]);

            return QrRefundResult::unknown("Click javob bermadi");
        }

        $code = $response->json('error_code');
        $status = $response->json('payment_status');
        if ($response->successful() && is_numeric($code) && (int) $code === 0 && is_numeric($status)) {
            if ((int) $status < 0) {
                return QrRefundResult::done((string) $qr->provider_transaction_id);
            }
            if ((int) $status === 2) {
                return QrRefundResult::failed("Click to'lovni qaytarmagan");
            }
        }

        return QrRefundResult::unknown("Click holatni aytmadi");
    }

    /**
     * @return array{merchant_user_id: string, secret_key: string, service_id: string}
     */
    private function credentials(Store $store): array
    {
        return [
            'merchant_user_id' => (string) StoreMerchant::credential($store, 'click', 'merchant_user_id'),
            'secret_key' => (string) StoreMerchant::credential($store, 'click', 'secret_key'),
            'service_id' => (string) StoreMerchant::credential($store, 'click', 'service_id'),
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function unusable(PosQrPayment $qr, array $credentials): ?QrRefundResult
    {
        if (in_array('', $credentials, true)) {
            return QrRefundResult::failed("Click merchant sozlamalarida merchant_user_id, service_id yoki kalit yo'q");
        }
        if (!is_numeric($qr->provider_transaction_id)) {
            return QrRefundResult::failed("Click to'lov raqami topilmadi");
        }

        return null;
    }

    public function simulated(): bool
    {
        return false;
    }
}
