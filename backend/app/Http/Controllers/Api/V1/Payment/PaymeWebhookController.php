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

class PaymeWebhookController extends Controller
{
    /**
     * Handle Payme JSON-RPC 2.0 Callbacks.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("Payme Webhook Received", $payload);

        $method = $payload['method'] ?? '';
        $params = $payload['params'] ?? [];
        $id = $payload['id'] ?? null;

        // 1. Basic Auth check for Payme (merchant key validation)
        if (!$this->authorizePayme($request)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32504,
                    'message' => 'Unauthorized merchant key'
                ]
            ]);
        }

        try {
            switch ($method) {
                case 'CheckPerformTransaction':
                    return $this->checkPerformTransaction($params, $id);
                case 'CreateTransaction':
                    return $this->createTransaction($params, $id);
                case 'PerformTransaction':
                    return $this->performTransaction($params, $id);
                case 'CancelTransaction':
                    return $this->cancelTransaction($params, $id);
                case 'CheckTransaction':
                    return $this->checkTransaction($params, $id);
                default:
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'error' => [
                            'code' => -32601,
                            'message' => 'Method not found'
                        ]
                    ]);
            }
        } catch (\Exception $e) {
            Log::error("Payme Webhook Exception: " . $e->getMessage());
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -31008,
                    'message' => 'Internal server error'
                ]
            ]);
        }
    }

    /**
     * Authorize Payme via HTTP Basic Authentication.
     */
    protected function authorizePayme(Request $request): bool
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Basic ')) {
            return false;
        }

        $credentials = base64_decode(substr($header, 6));
        $parts = explode(':', $credentials, 2);
        if (count($parts) < 2) {
            return false;
        }

        // Payme sends basic auth as 'Paycom:secret_key'
        $username = $parts[0];
        $key = trim($parts[1]);

        if ($username !== 'Paycom') {
            return false;
        }

        // Parse payload to identify order/store
        $payload = $request->all();
        $params = $payload['params'] ?? [];
        $orderUuid = $params['account']['order_id'] ?? null;

        if (!$orderUuid) {
            $trxId = $params['id'] ?? null;
            if ($trxId) {
                $payment = Payment::where('provider', 'payme')
                    ->where('provider_transaction_id', $trxId)
                    ->first();
                if ($payment && $payment->order) {
                    $orderUuid = $payment->order->uuid;
                }
            }
        }

        if ($orderUuid) {
            $isTopup = str_starts_with($orderUuid, 'topup_');
            if ($isTopup) {
                return $key === env('PAYME_SECRET_KEY', 'payme_sandbox_secret');
            }

            $order = Order::where('uuid', $orderUuid)
                ->orWhere('short_code', $orderUuid)
                ->first();

            if ($order && $order->store) {
                $settings = $order->store->payment_settings ?? [];
                if (($order->store->payment_account_type ?? 'platform') === 'own_account' || !empty($settings['payme']['secret_key'])) {
                    $storeSecretKey = $settings['payme']['secret_key'] ?? null;
                    if ($storeSecretKey) {
                        return $key === trim($storeSecretKey);
                    }
                }
            }
        }

        // Fallback to platform-level secret key
        return $key === env('PAYME_SECRET_KEY', 'payme_sandbox_secret');
    }

    /**
     * Check if order is ready for payment.
     */
    protected function checkPerformTransaction(array $params, $id): JsonResponse
    {
        $amount = (float) ($params['amount'] ?? 0) / 100; // in tiyins
        $orderUuid = $params['account']['order_id'] ?? '';

        $isTopup = str_starts_with($orderUuid, 'topup_');
        $topupRequest = null;
        $order = null;

        if ($isTopup) {
            $topupUuid = substr($orderUuid, 6);
            $topupRequest = \App\Models\StoreTopupRequest::where('uuid', $topupUuid)->first();
            if (!$topupRequest) {
                return $this->rpcError($id, -31008, 'Topup request not found');
            }
            if ($topupRequest->status === 'paid') {
                return $this->rpcError($id, -31008, 'Topup already paid');
            }
            $expectedAmount = (float) $topupRequest->amount;
        } else {
            $order = Order::where('uuid', $orderUuid)
                ->orWhere('short_code', $orderUuid)
                ->first();

            if (!$order) {
                return $this->rpcError($id, -31008, 'Order not found');
            }

            if ($order->payment_status === PaymentStatus::PAID) {
                return $this->rpcError($id, -31008, 'Order already paid');
            }
            $expectedAmount = (float) $order->total_price;
        }

        if (abs($amount - $expectedAmount) > 0.01) {
            return $this->rpcError($id, -31008, 'Incorrect amount');
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'allow' => true
            ]
        ]);
    }

    /**
     * Create payment transaction attempt.
     */
    protected function createTransaction(array $params, $id): JsonResponse
    {
        $trxId = $params['id'] ?? '';
        $time = $params['time'] ?? 0;
        $amount = (float) ($params['amount'] ?? 0) / 100;
        $orderUuid = $params['account']['order_id'] ?? '';

        $isTopup = str_starts_with($orderUuid, 'topup_');
        $topupRequest = null;
        $order = null;

        if ($isTopup) {
            $topupUuid = substr($orderUuid, 6);
            $topupRequest = \App\Models\StoreTopupRequest::where('uuid', $topupUuid)->first();
            if (!$topupRequest) {
                return $this->rpcError($id, -31008, 'Topup request not found');
            }
            if ($topupRequest->status === 'paid') {
                return $this->rpcError($id, -31008, 'Topup already paid');
            }
        } else {
            $order = Order::where('uuid', $orderUuid)
                ->orWhere('short_code', $orderUuid)
                ->first();

            if (!$order) {
                return $this->rpcError($id, -31008, 'Order not found');
            }
        }

        // Idempotency check: find existing transaction
        $payment = Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $trxId)
            ->first();

        if ($payment) {
            if ($payment->status === PaymentStatus::CANCELLED) {
                return $this->rpcError($id, -31008, 'Transaction already cancelled');
            }
            if ($payment->status === PaymentStatus::PAID) {
                return $this->rpcError($id, -31008, 'Transaction already paid');
            }

            // Return current pending state (state = 1)
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'create_time' => (int) $payment->created_at->getTimestamp() * 1000,
                    'transaction' => (string) $payment->id,
                    'state' => 1
                ]
            ]);
        }

        // Create new pending payment
        $paymentData = [
            'provider' => 'payme',
            'provider_transaction_id' => $trxId,
            'amount' => $amount,
            'status' => PaymentStatus::PENDING,
            'payload' => $params,
        ];

        if ($isTopup) {
            $paymentData['order_id'] = null;
        } else {
            $paymentData['order_id'] = $order->id;
        }

        $payment = Payment::create($paymentData);

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'create_time' => (int) $time,
                'transaction' => (string) $payment->id,
                'state' => 1
            ]
        ]);
    }

    /**
     * Finalize payment transaction.
     */
    protected function performTransaction(array $params, $id): JsonResponse
    {
        $trxId = $params['id'] ?? '';

        $payment = Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $trxId)
            ->first();

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        if ($payment->status === PaymentStatus::PAID) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'perform_time' => (int) $payment->paid_at->getTimestamp() * 1000,
                    'transaction' => (string) $payment->id,
                    'state' => 2
                ]
            ]);
        }

        if ($payment->status !== PaymentStatus::PENDING) {
            return $this->rpcError($id, -31008, 'Invalid transaction state');
        }

        // Check if this is a topup request
        $orderUuid = $payment->payload['account']['order_id'] ?? '';
        $isTopup = str_starts_with($orderUuid, 'topup_');

        if ($isTopup) {
            $topupUuid = substr($orderUuid, 6);
            $topupRequest = \App\Models\StoreTopupRequest::where('uuid', $topupUuid)->first();
            if (!$topupRequest) {
                return $this->rpcError($id, -31008, 'Topup request not found');
            }

            // Mark payment and topup request as paid
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $topupRequest, $params) {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                    'payload' => array_merge($payment->payload ?? [], ['perform_params' => $params]),
                ]);

                $lockedTopup = \App\Models\StoreTopupRequest::where('id', $topupRequest->id)->lockForUpdate()->firstOrFail();
                if ($lockedTopup->status !== 'paid') {
                    $lockedTopup->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'provider_transaction_id' => $payment->provider_transaction_id,
                        'payload' => $params,
                    ]);

                    $store = \App\Models\Store::where('id', $lockedTopup->store_id)->lockForUpdate()->firstOrFail();
                    $balanceBefore = $store->balance;
                    $balanceAfter = $balanceBefore - $lockedTopup->amount; // reduce debt

                    \App\Models\StoreTransaction::create([
                        'store_id' => $store->id,
                        'type' => \App\Enums\StoreTransactionType::TOPUP->value,
                        'amount' => -$lockedTopup->amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'notes' => 'Online top-up via Payme',
                        'reference_id' => $lockedTopup->uuid,
                    ]);

                    $updateData = ['balance' => $balanceAfter];

                    if ($store->status === \App\Enums\StoreStatus::PAYMENT_REQUIRED && $balanceAfter <= -100000) {
                        $updateData['status'] = \App\Enums\StoreStatus::ACTIVE->value;
                    }

                    $store->update($updateData);
                }
            });
        } else {
            // Mark payment as paid
            $payment->update([
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'payload' => array_merge($payment->payload ?? [], ['perform_params' => $params]),
            ]);

            // Dispatch decoupled event
            event(new PaymentPaid($payment));
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'perform_time' => (int) $payment->paid_at->getTimestamp() * 1000,
                'transaction' => (string) $payment->id,
                'state' => 2
            ]
        ]);
    }

    /**
     * Cancel/refund payment transaction.
     */
    protected function cancelTransaction(array $params, $id): JsonResponse
    {
        $trxId = $params['id'] ?? '';
        $reason = (int) ($params['reason'] ?? 0);

        $payment = Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $trxId)
            ->first();

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        if ($payment->status === PaymentStatus::CANCELLED) {
            $state = $payment->paid_at !== null ? -2 : -1;
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'cancel_time' => (int) $payment->cancelled_at->getTimestamp() * 1000,
                    'transaction' => (string) $payment->id,
                    'state' => $state
                ]
            ]);
        }

        $state = $payment->status === PaymentStatus::PAID ? -2 : -1;

        $payment->update([
            'status' => PaymentStatus::CANCELLED,
            'cancelled_at' => now(),
            'payload' => array_merge($payment->payload ?? [], ['cancel_reason' => $reason]),
        ]);

        $orderUuid = $payment->payload['account']['order_id'] ?? '';
        $isTopup = str_starts_with($orderUuid, 'topup_');

        if ($isTopup) {
            $topupUuid = substr($orderUuid, 6);
            $topupRequest = \App\Models\StoreTopupRequest::where('uuid', $topupUuid)->first();
            if ($topupRequest) {
                $topupRequest->update(['status' => 'cancelled']);
            }
        } else {
            // If order was already paid, revert back to pending/canceled
            if ($state === -2 && $payment->order) {
                $payment->order->update([
                    'payment_status' => PaymentStatus::PENDING->value,
                ]);
            }
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'cancel_time' => (int) $payment->cancelled_at->getTimestamp() * 1000,
                'transaction' => (string) $payment->id,
                'state' => $state
            ]
        ]);
    }

    /**
     * Check transaction state.
     */
    protected function checkTransaction(array $params, $id): JsonResponse
    {
        $trxId = $params['id'] ?? '';

        $payment = Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $trxId)
            ->first();

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        $state = 1; // pending
        if ($payment->status === PaymentStatus::PAID) {
            $state = 2;
        } elseif ($payment->status === PaymentStatus::CANCELLED) {
            $state = $payment->paid_at !== null ? -2 : -1;
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'create_time' => (int) $payment->created_at->getTimestamp() * 1000,
                'perform_time' => $payment->paid_at ? (int) $payment->paid_at->getTimestamp() * 1000 : 0,
                'cancel_time' => $payment->cancelled_at ? (int) $payment->cancelled_at->getTimestamp() * 1000 : 0,
                'transaction' => (string) $payment->id,
                'state' => $state,
                'reason' => $payment->payload['cancel_reason'] ?? null
            ]
        ]);
    }

    /**
     * Return formatted RPC error payload.
     */
    protected function rpcError($id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ]);
    }
}
