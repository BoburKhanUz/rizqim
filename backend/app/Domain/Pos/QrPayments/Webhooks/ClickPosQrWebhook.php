<?php

namespace App\Domain\Pos\QrPayments\Webhooks;

use App\Domain\Pos\QrPayments\Providers\StoreMerchant;
use App\Domain\Pos\QrPayments\QrPaymentService;
use App\Domain\Pos\QrPayments\QrPaymentStatus;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-41: Click SHOP API Prepare/Complete for a QR shown at a POS till (`merchant_trans_id` = `pos_qr_<uuid>`;
 * ClickWebhookController hands these over before its order logic). A POS QR is always STORE-owned: the signature is
 * checked with the store's own secret key (payment_settings.click.secret_key), never the platform's.
 *
 *  - Prepare (action 0): the request must be pending, in time, its bill still owing its amount (to the tiyin); then
 *    it is held (the expiry waits) and answered with `merchant_prepare_id` = the request's id. Already paid: -4;
 *    expired, cancelled or the bill changed: -9 (Click does not take the money).
 *  - Complete (action 1): the bill takes the `qr` payment (QrPaymentService::confirm; its sale receipt is Rizqim's).
 *    A repeat of the same click_trans_id: the same success (`Already confirmed`); a confirmation the bill cannot
 *    take: -9 — on a Complete error Click cancels the payment and returns the money to the customer.
 *  - Never queues SubmitClickFiscalData: Rizqim issues the receipt, Click must not register another (POS_SPEC §4).
 */
final class ClickPosQrWebhook
{
    public function __construct(private readonly QrPaymentService $service) {}

    public function handle(Request $request): JsonResponse
    {
        $clickTransId = (string) $request->input('click_trans_id');
        $merchantTransId = (string) $request->input('merchant_trans_id');
        $amount = (string) $request->input('amount');
        $action = (string) $request->input('action');

        $qr = PosQrPayment::forAccount($merchantTransId);
        if ($qr === null || $qr->provider !== QrProvider::CLICK) {
            return $this->error(-5, 'User/Order not found');
        }

        // Only the store's own key confirms money paid into the store's own Click account; a sandbox request has none
        $store = Store::find($qr->store_id);
        $secret = $store !== null && $qr->driver === 'click' ? StoreMerchant::credential($store, 'click', 'secret_key') : null;
        $expected = $secret === null ? null : md5(
            $clickTransId . $request->input('service_id') . $secret . $merchantTransId
            . ($action === '1' ? $request->input('merchant_prepare_id') : '') . $amount . $action . $request->input('sign_time')
        );
        if ($expected === null || !hash_equals($expected, (string) $request->input('sign_string'))) {
            return $this->error(-1, 'Signature verification failed');
        }

        if ((int) $request->input('error') < 0) {
            return $this->error(-9, 'Transaction error');
        }
        if (!is_numeric($amount) || !Money::of($amount)->equals($qr->amount)) {
            return $this->error(-2, 'Incorrect amount');
        }

        if ($action === '0') {
            if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
                return $this->error(-4, 'Already paid');
            }
            if (!$this->service->payable($qr)) {
                return $this->error(-9, 'Transaction cancelled');
            }
            $this->service->hold($qr, $clickTransId, ['click' => ['click_trans_id' => $clickTransId, 'click_paydoc_id' => $request->input('click_paydoc_id'), 'sign_time' => $request->input('sign_time')]]);

            return response()->json([
                'click_trans_id' => $clickTransId,
                'merchant_trans_id' => $merchantTransId,
                'merchant_prepare_id' => $qr->id,
                'error' => 0,
                'error_note' => 'Success',
            ]);
        }

        if ($action === '1') {
            if ((string) $request->input('merchant_prepare_id') !== (string) $qr->id
                || ($qr->provider_transaction_id !== null && $qr->provider_transaction_id !== $clickTransId && $qr->status === QrPaymentStatus::PENDING)) {
                return $this->error(-6, 'Transaction does not exist');
            }

            $outcome = $this->service->confirm($qr, $clickTransId, ['click_paydoc_id' => $request->input('click_paydoc_id')]);

            return match ($outcome) {
                'paid', 'already_confirmed' => response()->json([
                    'click_trans_id' => $clickTransId,
                    'merchant_trans_id' => $merchantTransId,
                    'merchant_confirm_id' => $qr->id,
                    'error' => 0,
                    'error_note' => $outcome === 'paid' ? 'Success' : 'Already confirmed',
                ]),
                'already_paid' => $this->error(-4, 'Already paid'),
                default => $this->error(-9, 'Transaction cancelled'),
            };
        }

        return $this->error(-3, 'Action not found');
    }

    private function error(int $code, string $note): JsonResponse
    {
        return response()->json(['error' => $code, 'error_note' => $note]);
    }
}
