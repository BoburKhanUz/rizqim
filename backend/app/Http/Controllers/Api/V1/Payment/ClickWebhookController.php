<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use Illuminate\Support\Facades\Log;

class ClickWebhookController extends Controller
{
    /**
     * Handle Click callback.
     */
    public function handle(Request $request): JsonResponse
    {
        $clickTransId = $request->input('click_trans_id');
        $serviceId = $request->input('service_id');
        $clickPaydocId = $request->input('click_paydoc_id');
        $merchantTransId = $request->input('merchant_trans_id'); // Order UUID
        $amount = (float) $request->input('amount');
        $action = (int) $request->input('action');
        $error = (int) $request->input('error');
        $signTime = $request->input('sign_time');
        $signString = $request->input('sign_string');

        Log::info("Click Webhook Received", $request->all());

        // 1. Find Order or Topup Request
        $isTopup = str_starts_with($merchantTransId, 'topup_');
        $topupRequest = null;
        $order = null;

        if ($isTopup) {
            $topupUuid = substr($merchantTransId, 6);
            $topupRequest = \App\Models\StoreTopupRequest::where('uuid', $topupUuid)->first();
            if (!$topupRequest) {
                return response()->json([
                    'error' => -5,
                    'error_note' => 'Topup request not found'
                ]);
            }
        } else {
            $order = Order::where('uuid', $merchantTransId)
                ->orWhere('short_code', $merchantTransId)
                ->first();

            if (!$order) {
                return response()->json([
                    'error' => -5,
                    'error_note' => 'User/Order not found'
                ]);
            }
        }

        // 2. Validate Signature
        if ($isTopup) {
            $secretKey = env('CLICK_SECRET_KEY', 'click_sandbox_secret');
        } else {
            $settings = $order->store?->payment_settings ?? [];
            $secretKey = $settings['click']['secret_key'] ?? env('CLICK_SECRET_KEY', 'click_sandbox_secret');
        }

        $expectedSign = md5(
            $clickTransId .
            $serviceId .
            $clickPaydocId .
            $merchantTransId .
            $request->input('amount') . // raw string for perfect precision matching
            $action .
            $signTime .
            $secretKey
        );

        if ($signString !== $expectedSign) {
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

        // 4. Verify Billing Amount matches exactly
        $expectedAmount = $isTopup ? (float) $topupRequest->amount : (float) $order->total_price;
        if (abs($amount - $expectedAmount) > 0.01) {
            return response()->json([
                'error' => -2,
                'error_note' => 'Incorrect amount'
            ]);
        }

        // 5. Handle Action States
        if ($action === 0) {
            // Action 0: Prepare
            $isPaid = $isTopup ? ($topupRequest->status === 'paid') : ($order->payment_status === PaymentStatus::PAID);
            if ($isPaid) {
                return response()->json([
                    'error' => -4,
                    'error_note' => 'Already paid'
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

        if ($action === 1) {
            // Action 1: Complete
            if ($isTopup) {
                if ($topupRequest->status === 'paid') {
                    return response()->json([
                        'click_trans_id' => $clickTransId,
                        'merchant_trans_id' => $merchantTransId,
                        'merchant_confirm_id' => $topupRequest->id,
                        'error' => 0,
                        'error_note' => 'Already confirmed'
                    ]);
                }

                // Complete Topup
                \Illuminate\Support\Facades\DB::transaction(function () use ($topupRequest, $amount, $clickTransId, $request) {
                    $lockedTopup = \App\Models\StoreTopupRequest::where('id', $topupRequest->id)->lockForUpdate()->firstOrFail();
                    if ($lockedTopup->status === 'paid') {
                        return;
                    }

                    $lockedTopup->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'provider_transaction_id' => $clickTransId,
                        'payload' => $request->all(),
                    ]);

                    $store = \App\Models\Store::where('id', $lockedTopup->store_id)->lockForUpdate()->firstOrFail();
                    $balanceBefore = $store->balance;
                    $balanceAfter = $balanceBefore - $amount; // reduce debt

                    \App\Models\StoreTransaction::create([
                        'store_id' => $store->id,
                        'type' => \App\Enums\StoreTransactionType::TOPUP->value,
                        'amount' => -$amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'notes' => 'Online top-up via Click',
                        'reference_id' => $lockedTopup->uuid,
                    ]);

                    $updateData = ['balance' => $balanceAfter];

                    if ($store->status === \App\Enums\StoreStatus::PAYMENT_REQUIRED && $balanceAfter <= -100000) {
                        $updateData['status'] = \App\Enums\StoreStatus::ACTIVE->value;
                    }

                    $store->update($updateData);
                });

                return response()->json([
                    'click_trans_id' => $clickTransId,
                    'merchant_trans_id' => $merchantTransId,
                    'merchant_confirm_id' => $topupRequest->id,
                    'error' => 0,
                    'error_note' => 'Success'
                ]);
            } else {
                // Idempotency: Find or create the payment attempt
                $payment = Payment::firstOrCreate([
                    'provider' => 'click',
                    'provider_transaction_id' => $clickTransId,
                ], [
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'status' => PaymentStatus::PENDING,
                    'payload' => $request->all(),
                ]);

                if ($payment->status === PaymentStatus::PAID) {
                    return response()->json([
                        'click_trans_id' => $clickTransId,
                        'merchant_trans_id' => $merchantTransId,
                        'merchant_confirm_id' => $order->id,
                        'error' => 0,
                        'error_note' => 'Already confirmed'
                    ]);
                }

                // Mark payment as paid
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                    'payload' => array_merge($payment->payload ?? [], ['completed_webhook' => $request->all()]),
                ]);

                // Dispatch decoupled paid event (listener handles order states!)
                event(new PaymentPaid($payment));

                return response()->json([
                    'click_trans_id' => $clickTransId,
                    'merchant_trans_id' => $merchantTransId,
                    'merchant_confirm_id' => $order->id,
                    'error' => 0,
                    'error_note' => 'Success'
                ]);
            }
        }

        return response()->json([
            'error' => -3,
            'error_note' => 'Action not found'
        ]);
    }
}
