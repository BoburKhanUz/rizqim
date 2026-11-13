<?php

namespace App\Domain\Pos\QrPayments\Providers;

use App\Domain\Pos\QrPayments\QrCheckout;
use App\Domain\Pos\QrPayments\QrPaymentProvider;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Services\Payment\PaymentProviderSwitch;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: Payme at the till. The QR is the store cashbox's checkout link (checkout.paycom.uz, `ac.order_id`
 * `pos_qr_<uuid>`, amount in tiyin): Payme calls the Merchant API webhook (CheckPerformTransaction, CreateTransaction,
 * PerformTransaction...) with HTTP Basic `Paycom:<the store's key>`. CheckPerformTransaction answers without `detail`,
 * so Payme registers no receipt of its own (Rizqim issues the sale receipt).
 *
 * A refund: the Subscribe API's `receipts.cancel` (header `X-Auth: merchant_id:key`, https://developer.help.paycom.uz/
 * metody-subscribe-api/receipts.cancel) with the Merchant API transaction id, which is the Payme receipt id (an
 * assumption to confirm with Payme when the first store connects). Payme then calls CancelTransaction for the
 * performed transaction, which the webhook accepts only while this refund asks for it. The whole payment only.
 */
final class PaymeQrProvider implements QrPaymentProvider
{
    public function __construct(private readonly PaymentProviderSwitch $switch) {}

    public function provider(): QrProvider
    {
        return QrProvider::PAYME;
    }

    public function driver(): string
    {
        return 'payme';
    }

    public function configured(Store $store): bool
    {
        return StoreMerchant::enabled($store, 'payme');
    }

    public function checkout(PosQrPayment $qr, Store $store): QrCheckout
    {
        return new QrCheckout($this->switch->paymeUrl(
            StoreMerchant::credential($store, 'payme', 'merchant_id'),
            Money::of($qr->amount)->toTiyin(),
            $qr->account(),
            StoreMerchant::returnUrl(),
            false,
        ));
    }

    public function cancel(PosQrPayment $qr, Store $store): void
    {
        // A pending Payme transaction of a cancelled request is refused at PerformTransaction and timed out by Payme
    }

    public function supportsPartialRefund(): bool
    {
        return false;
    }

    public function refund(PosQrPayment $qr, Store $store, Money $amount, string $refundUuid): QrRefundResult
    {
        $merchant = StoreMerchant::credential($store, 'payme', 'merchant_id');
        $key = StoreMerchant::credential($store, 'payme', 'secret_key');
        if ($merchant === null || $key === null || $qr->provider_transaction_id === null) {
            return QrRefundResult::failed("Payme kassa sozlamalari yoki tranzaksiya raqami yo'q");
        }

        try {
            $response = Http::connectTimeout(5)->timeout(20)->acceptJson()->asJson()
                ->withHeaders(['X-Auth' => "{$merchant}:{$key}"])
                ->post((string) config('qr_payments.payme.api_url'), [
                    'id' => $refundUuid,
                    'method' => 'receipts.cancel',
                    'params' => ['id' => $qr->provider_transaction_id],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Payme receipts.cancel failed: ' . $e->getMessage(), ['qr' => $qr->uuid]);

            return QrRefundResult::failed("Payme bilan aloqa bo'lmadi");
        }

        $error = $response->json('error');
        if ($response->successful() && $error === null && is_array($response->json('result'))) {
            $id = $response->json('result.receipt._id');

            return QrRefundResult::done(is_scalar($id) ? (string) $id : $qr->provider_transaction_id);
        }

        Log::warning('Payme receipts.cancel refused', ['qr' => $qr->uuid, 'status' => $response->status(), 'error' => $error]);
        $message = is_array($error) ? ($error['message'] ?? null) : null;
        if (is_array($message)) {
            $message = $message['uz'] ?? $message['ru'] ?? $message['en'] ?? null;
        }

        return QrRefundResult::failed(is_string($message) && $message !== '' ? "Payme rad etdi: {$message}" : 'Payme rad etdi');
    }

    public function simulated(): bool
    {
        return false;
    }
}
