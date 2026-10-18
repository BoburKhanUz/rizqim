<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\StoreTopupRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClickWebhookController extends Controller
{
    /**
     * Handle Click callback.
     */
    public function handle(Request $request): JsonResponse
    {
        $clickTransId = (string) $request->input('click_trans_id');
        $merchantTransId = (string) $request->input('merchant_trans_id'); // Order UUID
        $amount = (string) $request->input('amount'); // raw string for perfect precision matching
        $action = (string) $request->input('action');
        $error = (int) $request->input('error');

        Log::info("Click Webhook Received", $request->all());

        // 1. Find Order or Topup Request
        $isTopup = str_starts_with($merchantTransId, 'topup_');
        $topupRequest = null;
        $order = null;

        if ($isTopup) {
            $topupUuid = substr($merchantTransId, 6);
            $topupRequest = Str::isUuid($topupUuid) ? StoreTopupRequest::where('uuid', $topupUuid)->first() : null;
            if (!$topupRequest) {
                return response()->json([
                    'error' => -5,
                    'error_note' => 'Topup request not found'
                ]);
            }
        } else {
            $order = $this->findOrder($merchantTransId);

            if (!$order) {
                return response()->json([
                    'error' => -5,
                    'error_note' => 'User/Order not found'
                ]);
            }
        }

        // 2. Validate Signature
        $secretKey = $this->secretKey($order);
        if ($secretKey === null) {
            Log::error('Click webhook rejected: no merchant secret key is configured for this payment (CLICK_SECRET_KEY or the store\'s own key).');
            return response()->json([
                'error' => -1,
                'error_note' => 'Signature verification failed'
            ]);
        }

        // Click SHOP-API: md5(click_trans_id + service_id + SECRET_KEY + merchant_trans_id [+ merchant_prepare_id] + amount + action + sign_time)
        $expectedSign = md5(
            $clickTransId .
            $request->input('service_id') .
            $secretKey .
            $merchantTransId .
            ($action === '1' ? $request->input('merchant_prepare_id') : '') .
            $amount .
            $action .
            $request->input('sign_time')
        );

        if (!hash_equals($expectedSign, (string) $request->input('sign_string'))) {
            return response()->json([
                'error' => -1,
                'error_note' => 'Signature verification failed'
            ]);
        }

        // 3. Verify Error Status
        if ($error < 0) {
            return response()->json([
                'error' => -9,
                'error_note' => 'Transaction error'
            ]);
        }

        // An order placed for another payment method (e.g. cash) is not a Click payer account
        if (!$isTopup && $order->payment_method !== 'click') {
            return response()->json([
                'error' => -5,
                'error_note' => 'Order is not payable via Click'
            ]);
        }

        // 4. Verify Billing Amount matches exactly
        $expectedAmount = $isTopup ? $topupRequest->amount : $order->total_price;
        if (!is_numeric($amount) || (int) round((float) $amount * 100) !== (int) round((float) $expectedAmount * 100)) {
            return response()->json([
                'error' => -2,
                'error_note' => 'Incorrect amount'
            ]);
        }

        // 5. Handle Action States
        if ($action === '0') {
            // Action 0: Prepare
            $isPaid = $isTopup ? ($topupRequest->status === 'paid') : ($order->payment_status === PaymentStatus::PAID);
            if ($isPaid) {
                return response()->json([
                    'error' => -4,
                    'error_note' => 'Already paid'
                ]);
            }

            if ($isTopup ? $topupRequest->status !== 'pending' : !$this->isPayable($order)) {
                return response()->json([
                    'error' => -9,
                    'error_note' => 'Transaction cancelled'
                ]);
            }

            return response()->json([
                'click_trans_id' => $clickTransId,
                'merchant_trans_id' => $merchantTransId,
                'merchant_prepare_id' => $isTopup ? $topupRequest->id : $order->id,
                'error' => 0,
                'error_note' => 'Success'
            ]);
        }

        if ($action === '1') {
            // Action 1: Complete
            $prepareId = $isTopup ? $topupRequest->id : $order->id;
            if ((string) $request->input('merchant_prepare_id') !== (string) $prepareId) {
                return response()->json([
                    'error' => -6,
                    'error_note' => 'Transaction does not exist'
                ]);
            }

            $outcome = $isTopup
                ? $this->completeTopup($topupRequest, $clickTransId, $request)
                : $this->completeOrderPayment($order, $clickTransId, $request);

            if ($outcome === 'already_paid') {
                return response()->json([
                    'error' => -4,
                    'error_note' => 'Already paid'
                ]);
            }

            if ($outcome === 'cancelled') {
                return response()->json([
                    'error' => -9,
                    'error_note' => 'Transaction cancelled'
                ]);
            }

            // A repeated complete for the same click_trans_id gets the same successful answer.
            return response()->json([
                'click_trans_id' => $clickTransId,
                'merchant_trans_id' => $merchantTransId,
                'merchant_confirm_id' => $prepareId,
                'error' => 0,
                'error_note' => $outcome === 'paid' ? 'Success' : 'Already confirmed'
            ]);
        }

        return response()->json([
            'error' => -3,
            'error_note' => 'Action not found'
        ]);
    }

    /**
     * Mark the top-up paid exactly once: 'paid', 'already_confirmed', 'already_paid' or 'cancelled'.
     */
    protected function completeTopup(StoreTopupRequest $topupRequest, string $clickTransId, Request $request): string
    {
        return DB::transaction(function () use ($topupRequest, $clickTransId, $request) {
            $lockedTopup = StoreTopupRequest::where('id', $topupRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedTopup->status === 'paid') {
                return $lockedTopup->provider_transaction_id === $clickTransId ? 'already_confirmed' : 'already_paid';
            }

            if ($lockedTopup->status !== 'pending') {
                return 'cancelled';
            }

            $lockedTopup->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_transaction_id' => $clickTransId,
                'payload' => $request->all(),
            ]);

            app(LedgerService::class)->creditTopup($lockedTopup, 'Click');

            return 'paid';
        });
    }

    /**
     * Mark the order paid exactly once: 'paid', 'already_confirmed', 'already_paid' or 'cancelled'.
     */
    protected function completeOrderPayment(Order $order, string $clickTransId, Request $request): string
    {
        [$outcome, $payment] = DB::transaction(function () use ($order, $clickTransId, $request) {
            // Every completion for this order is serialised on the order row.
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::where('provider', 'click')
                ->where('provider_transaction_id', $clickTransId)
                ->lockForUpdate()
                ->first();

            if ($payment && (int) $payment->order_id !== (int) $lockedOrder->id) {
                return ['cancelled', null];
            }

            if ($payment?->status === PaymentStatus::PAID) {
                return ['already_confirmed', $payment];
            }

            if ($lockedOrder->payment_status === PaymentStatus::PAID) {
                return ['already_paid', null];
            }

            if (($payment && $payment->status !== PaymentStatus::PENDING) || !$this->isPayable($lockedOrder)) {
                return ['cancelled', null];
            }

            $payment ??= new Payment([
                'order_id' => $lockedOrder->id,
                'provider' => 'click',
                'provider_transaction_id' => $clickTransId,
                'amount' => (float) $request->input('amount'),
                'payload' => $request->all(),
            ]);

            $payment->fill([
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
            ])->save();

            $lockedOrder->update(['payment_status' => PaymentStatus::PAID->value]);

            return ['paid', $payment];
        });

        if ($outcome === 'paid') {
            // Dispatch decoupled paid event (listener handles order states!) only on the real transition.
            event(new PaymentPaid($payment));
        }

        return $outcome;
    }

    protected function findOrder(string $reference): ?Order
    {
        if ($reference === '') {
            return null;
        }

        return Str::isUuid($reference)
            ? Order::where('uuid', $reference)->first()
            : Order::where('short_code', $reference)->first();
    }

    protected function isPayable(Order $order): bool
    {
        return $order->payment_status !== PaymentStatus::PAID
            && !in_array($order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true);
    }

    /**
     * A store's own key may only confirm money paid into the store's own merchant account;
     * platform-collected orders and top-ups must be signed with the platform key.
     */
    protected function secretKey(?Order $order): ?string
    {
        if ($order && $order->payment_owner !== 'PLATFORM') {
            // A STORE-owned order is paid into the store's own merchant: only its own key can confirm it.
            return $order->store?->collectsOwnPayments('click') ? (string) $order->store->payment_settings['click']['secret_key'] : null;
        }

        return PaymentProvider::platformCredential('click', 'secret_key');
    }
}
