<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Services\Admin\System\PaymentProviders;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wave P: the real UzQR driver (`uzqr_operator`): the national UzQR operator's API, called by Rizqim's server for the
 * till with THAT till's own API key (devices.uzqr_api_key; UzQrTillKeys) and the store's STIR (stores.tin). The money
 * goes to the restaurant's own account; Rizqim issues the sale receipt (a card payment with the RRN) and then sends
 * its link here. The shapes are UzQrOperatorProtocol's (one class to swap when the operator's spec comes).
 *
 * The connection address: qr_payments.uzqr.operator.url (production) or .test_url while .test is on. The test
 * contour is used only where test payments are allowed (PaymentProviders::testModeAllowed, as the QR sandboxes); on
 * production a test flag, a production URL equal to the test one, or a non-https URL leaves the driver unconfigured.
 * Timeouts as SetRetail10's defaults (3 s connect, 20 s answer). No retry inside a call: the till's poll, the minute
 * job and the receipt-link job ask again. Nothing it logs or throws holds the key or a request body.
 */
final class UzQrOperatorGateway implements UzQrGateway
{
    public function __construct(private readonly UzQrTillKeys $keys) {}

    public function readyFor(?Device $device, Store $store): ?string
    {
        $other = ' Hozircha boshqa to\'lov usulini tanlang.';
        if (self::baseUrl() === null) {
            return "UzQR ulanmagan: operator manzili sozlanmagan (administrator)." . $other;
        }
        if ($this->keys->tin($store) === null) {
            return "UzQR uchun do'kon STIRi kiritilmagan: egasi fiskal sozlamalarda STIRni kiritsin." . $other;
        }
        if ($device === null || $device->type !== DeviceType::POS || $device->isRevoked() || (int) $device->store_id !== (int) $store->id) {
            return "UzQR faqat kassa (POS) qurilmasidan yaratiladi." . $other;
        }
        if ($this->keys->key($device) === null) {
            return "Bu kassaga UzQR API kaliti kiritilmagan: egasi uzqr.uz da shu kassa uchun dinamik QR va API kalit yaratib, Rizqim'dagi UzQR sozlamalarida kassaga kiritsin." . $other;
        }

        return null;
    }

    public function createDynamicQr(Money $amount, PosQrPayment $qr, Store $store): UzQrDynamicQr
    {
        [$key, $tin] = $this->credentials($qr, $store);
        $answer = $this->call('create', UzQrOperatorProtocol::PATH_CREATE, UzQrOperatorProtocol::create($amount, $qr, $tin), $key, $qr);

        return UzQrOperatorProtocol::created($answer)
            ?? throw $this->failure('create', 'bad_answer', 'the answer has no invoice id or QR', false, $qr);
    }

    public function status(PosQrPayment $qr, Store $store): array
    {
        [$key, $tin] = $this->credentials($qr, $store);
        $state = UzQrOperatorProtocol::paymentState($this->call('status', UzQrOperatorProtocol::PATH_STATUS, UzQrOperatorProtocol::status($qr, $tin), $key, $qr), $qr);
        if ($state['status'] === 'mismatch') {
            Log::error('UzQR reports a payment of another amount: reconcile', ['qr' => $qr->uuid, 'amount' => $qr->amount, 'paid' => $state['details']['amount'] ?? null]);
        }

        return $state;
    }

    public function cancel(PosQrPayment $qr, Store $store): void
    {
        [$key, $tin] = $this->credentials($qr, $store);
        $this->call('cancel', UzQrOperatorProtocol::PATH_CANCEL, UzQrOperatorProtocol::cancel($qr, $tin), $key, $qr);
    }

    /**
     * The operator's cabinet refunds any amount up to the payment (manual v1.1), and SetRetail10 refunds a receipt's
     * lines: a part may be given back.
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function refund(PosQrPayment $qr, Money $amount, Store $store, string $refundUuid): QrRefundResult
    {
        // Never more than was paid minus what went back already (RefundService checks it too; this is the last guard)
        $left = Money::of($qr->amount)->minus($qr->refunded_amount);
        if (!$amount->isPositive() || $amount->isGreaterThan($left)) {
            return QrRefundResult::failed("qaytarish summasi to'lovdan qolganidan ({$left->toString()} so'm) ko'p");
        }
        try {
            [$key, $tin] = $this->credentials($qr, $store);
            $answer = $this->call('refund', UzQrOperatorProtocol::PATH_REFUND, UzQrOperatorProtocol::refund($qr, $amount, $refundUuid, $tin), $key, $qr);
        } catch (UzQrOperatorException $e) {
            return $e->transient ? QrRefundResult::unknown('UzQR javob bermadi') : QrRefundResult::failed("UzQR rad etdi: {$e->errorCode}");
        }

        return self::refundResult(UzQrOperatorProtocol::refundState($answer));
    }

    /**
     * Only an explicit answer about this refund settles it: an error (even a 4xx: the guessed status call may not be
     * the operator's) leaves it unknown, so a reversal is never sent twice on a misread answer.
     */
    public function refundStatus(PosQrPayment $qr, Money $amount, Store $store, string $refundUuid): QrRefundResult
    {
        try {
            [$key, $tin] = $this->credentials($qr, $store);
            $answer = $this->call('refund_status', UzQrOperatorProtocol::PATH_REFUND_STATUS, UzQrOperatorProtocol::refundStatus($qr, $refundUuid, $tin), $key, $qr);
        } catch (UzQrOperatorException) {
            return QrRefundResult::unknown('UzQR javob bermadi');
        }

        return self::refundResult(UzQrOperatorProtocol::refundState($answer));
    }

    public function sendReceiptLink(PosQrPayment $qr, string $url, Store $store): void
    {
        [$key, $tin] = $this->credentials($qr, $store);
        $this->call('receipt', UzQrOperatorProtocol::PATH_RECEIPT, UzQrOperatorProtocol::receipt($qr, $url, $tin), $key, $qr);
    }

    /**
     * The address requests go to now, or null (not configured, or the test contour where it may not be used).
     */
    public static function baseUrl(): ?string
    {
        $clean = fn (mixed $url) => is_string($url) && preg_match('~^https://[^\s/]+~i', trim($url)) === 1 ? rtrim(trim($url), '/') : null;
        $url = $clean(config('qr_payments.uzqr.operator.url'));
        $testUrl = $clean(config('qr_payments.uzqr.operator.test_url'));

        if ((bool) config('qr_payments.uzqr.operator.test', false)) {
            return PaymentProviders::testModeAllowed() ? $testUrl : null;
        }
        if ($url === null || ($testUrl !== null && strcasecmp($url, $testUrl) === 0 && !PaymentProviders::testModeAllowed())) {
            return null;
        }

        return $url;
    }

    /**
     * The key of the till that made the request and the store's STIR.
     *
     * @return array{0: string, 1: string}
     */
    private function credentials(PosQrPayment $qr, Store $store): array
    {
        // Tenancy: the till must be the request's store's (the request carries its device id)
        $device = $qr->device_id !== null ? Device::where('store_id', $store->id)->whereKey($qr->device_id)->first() : null;
        $key = $device !== null ? $this->keys->key($device) : null;
        $tin = $this->keys->tin($store);
        if ($key === null || $tin === null) {
            throw $this->failure('credentials', $key === null ? 'till_key_missing' : 'tin_missing', 'the till has no UzQR key or the store no STIR', false, $qr);
        }

        return [$key, $tin];
    }

    /**
     * One POST to the operator; its decoded answer, or UzQrOperatorException.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function call(string $operation, string $path, array $body, string $key, PosQrPayment $qr): array
    {
        $base = self::baseUrl() ?? throw $this->failure($operation, 'not_configured', 'the operator address is not configured', false, $qr);
        try {
            $response = Http::withHeaders(UzQrOperatorProtocol::headers($key))
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('qr_payments.uzqr.operator.connect_timeout', 3))
                ->timeout((int) config('qr_payments.uzqr.operator.timeout', 20))
                ->post($base . $path, $body);
        } catch (ConnectionException) {
            throw $this->failure($operation, 'no_answer', 'no answer', true, $qr);
        }

        $answer = $response->json();
        $answer = is_array($answer) ? $answer : [];
        if ($response->serverError() || $response->status() === 429) {
            throw $this->failure($operation, "http_{$response->status()}", "HTTP {$response->status()}", true, $qr);
        }
        $error = UzQrOperatorProtocol::error($answer);
        if (!$response->successful() || $error !== null) {
            $code = $error['code'] ?? "http_{$response->status()}";
            throw $this->failure($operation, $code, trim("HTTP {$response->status()} {$code} " . ($error['message'] ?? '')), false, $qr);
        }

        return $answer;
    }

    private function failure(string $operation, string $code, string $detail, bool $transient, PosQrPayment $qr): UzQrOperatorException
    {
        Log::warning("UzQR operator {$operation} failed", ['qr' => $qr->uuid, 'code' => $code, 'transient' => $transient]);

        return new UzQrOperatorException("UzQR {$operation}: {$detail}", $code, $transient);
    }

    /**
     * @param  array{state: 'done'|'failed'|'pending', reference: string|null, message: string|null}  $state
     */
    private static function refundResult(array $state): QrRefundResult
    {
        return match ($state['state']) {
            'done' => QrRefundResult::done($state['reference']),
            'failed' => QrRefundResult::failed('UzQR rad etdi' . ($state['message'] !== null ? ": {$state['message']}" : '')),
            // "from several seconds to several hours" (manual v1.1): the minute job asks until it is done
            'pending' => QrRefundResult::unknown("UzQR qaytarishni bajarmoqda (bir necha soatgacha)"),
        };
    }
}
