<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
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
use App\Services\Order\OrderStatusService;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Services\Payment\Fiscal\OnlineFiscalReceipt;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use App\Services\Payment\Fiscal\PaymeReceiptDetail;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Group('Webhooks')]
class PaymeWebhookController extends Controller
{
    // Payme Merchant API: a transaction that is not performed within 12 hours is cancelled with reason 4.
    private const TRANSACTION_TIMEOUT_MS = 43200000;

    /**
     * Payme: Merchant API (JSON-RPC 2.0)
     *
     * Payme Merchant API methods (CheckPerformTransaction, CreateTransaction, PerformTransaction, CancelTransaction,
     * CheckTransaction, GetStatement, SetFiscalData) with HTTP Basic auth of the merchant key. Always HTTP 200; errors
     * are JSON-RPC `error` objects. For a store with fiscal receipts on, CheckPerformTransaction answers with `detail`
     * (receipt lines with MXIK, package code and VAT) and SetFiscalData records the receipt Payme registered.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("Payme Webhook Received", $payload);

        $method = $payload['method'] ?? '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $id = $payload['id'] ?? null;

        // 1. Basic Auth check for Payme (merchant key validation)
        if (!$this->authorizePayme($request, $params)) {
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
                case 'GetStatement':
                    return $this->getStatement($params, $id);
                case 'SetFiscalData':
                    return $this->setFiscalData($params, $id);
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
    protected function authorizePayme(Request $request, array $params): bool
    {
        $header = (string) $request->header('Authorization');
        if (!str_starts_with($header, 'Basic ')) {
            return false;
        }

        $credentials = base64_decode(substr($header, 6), true);
        if ($credentials === false) {
            return false;
        }

        // Payme sends basic auth as 'Paycom:secret_key'
        $parts = explode(':', $credentials, 2);
        if (count($parts) < 2 || $parts[0] !== 'Paycom') {
            return false;
        }

        $expectedKey = $this->secretKey($this->orderForAuthorization($params));
        if ($expectedKey === null) {
            Log::error('Payme webhook rejected: no merchant key is configured for this payment (PAYME_SECRET_KEY or the store\'s own key).');
            return false;
        }

        return hash_equals($expectedKey, trim($parts[1]));
    }

    /**
     * The order whose merchant key must sign this request (null means the platform key).
     */
    protected function orderForAuthorization(array $params): ?Order
    {
        $payment = $this->findPayment($params);
        if ($payment) {
            // An existing transaction is always authorised by its own order, whatever `account` claims.
            return $payment->order;
        }

        return $this->findTarget($this->accountReference($params))[0];
    }

    /**
     * A store's own key may only confirm money paid into the store's own merchant account;
     * platform-collected orders and top-ups must be signed with the platform key.
     */
    protected function secretKey(?Order $order): ?string
    {
        if ($order && $order->payment_owner !== 'PLATFORM') {
            // A STORE-owned order is paid into the store's own Payme cashbox: only its own key can confirm it.
            return $this->storeKey($order);
        }

        return PaymentProvider::platformCredential('payme', 'secret_key');
    }

    protected function storeKey(?Order $order): ?string
    {
        if (!$order || $order->payment_owner === 'PLATFORM' || !$order->store?->collectsOwnPayments('payme')) {
            return null;
        }

        return trim((string) $order->store->payment_settings['payme']['secret_key']);
    }

    /**
     * Check if order is ready for payment.
     */
    protected function checkPerformTransaction(array $params, $id): JsonResponse
    {
        [$order, $topupRequest] = $this->findTarget($this->accountReference($params));

        if ($error = $this->accountError($order, $topupRequest, $params['amount'] ?? null)) {
            return $this->rpcError($id, ...$error);
        }

        $result = ['allow' => true];

        // P3-14: Payme registers the fiscal receipt from `detail`; left out when the store has fiscal receipts off
        // or a line is incomplete (the payment itself is never refused for it).
        if ($order && ($receipt = $this->fiscalReceipt($order)) !== null) {
            if ($receipt->isIssuable()) {
                $result['detail'] = PaymeReceiptDetail::from($receipt);
            } else {
                app(OnlineFiscalRecorder::class)->blocked($order, 'payme', $receipt);
            }
        }

        return $this->result($id, $result);
    }

    /**
     * The order's receipt for Payme when the store has fiscal receipts on, else null. A store's own Payme cashbox
     * registers it with the store as komitent; the platform cashbox would register the platform's own sale (the
     * Merchant API has no per-receipt komitent field), so it is flagged instead of sent.
     */
    protected function fiscalReceipt(Order $order): ?OnlineFiscalReceipt
    {
        try {
            $receipt = app(OnlineFiscalReceiptBuilder::class)->build($order, false);
        } catch (\Throwable $e) {
            Log::error('Payme fiscal detail could not be built: ' . $e->getMessage(), ['order_id' => $order->id]);

            return null;
        }

        if (!$receipt->enabled) {
            return null;
        }

        return $order->payment_owner === 'PLATFORM' ? $receipt->withProblems(['payme_platform_cashbox']) : $receipt;
    }

    /**
     * Create payment transaction attempt.
     */
    protected function createTransaction(array $params, $id): JsonResponse
    {
        $trxId = $this->transactionId($params);
        if ($trxId === '') {
            return $this->rpcError($id, -32600, 'Transaction id is required');
        }

        [$order, $topupRequest] = $this->findTarget($this->accountReference($params));
        if (!$order && !$topupRequest) {
            return $this->rpcError($id, -31050, 'Order not found', 'order_id');
        }

        return DB::transaction(function () use ($params, $id, $trxId, $order, $topupRequest) {
            // All transactions of one account are serialised on its row.
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);

            $payment = Payment::where('provider', 'payme')
                ->where('provider_transaction_id', $trxId)
                ->lockForUpdate()
                ->first();

            if ($payment) {
                if (!$this->belongsToTarget($payment, $order, $topupRequest)) {
                    return $this->rpcError($id, -31050, 'Transaction belongs to another order', 'order_id');
                }

                if ($payment->status !== PaymentStatus::PENDING) {
                    return $this->rpcError($id, -31008, 'Unable to perform operation');
                }

                if ($this->isExpired($payment)) {
                    $this->cancelPayment($payment, 4);
                    return $this->rpcError($id, -31008, 'Transaction timed out');
                }

                // Repeated CreateTransaction with the same id returns the same result.
                return $this->result($id, [
                    'create_time' => $this->createTime($payment),
                    'transaction' => (string) $payment->id,
                    'state' => 1
                ]);
            }

            if ($error = $this->accountError($order, $topupRequest, $params['amount'] ?? null)) {
                return $this->rpcError($id, ...$error);
            }

            // An account may wait on only one live Payme transaction, otherwise it could be paid twice.
            $awaitingPayment = Payment::where('provider', 'payme')
                ->whereNotNull('provider_transaction_id')
                ->where('status', PaymentStatus::PENDING->value)
                ->when(
                    $order,
                    fn ($query) => $query->where('order_id', $order->id),
                    fn ($query) => $query->whereNull('order_id')->where('payload->account->order_id', 'topup_' . $topupRequest->uuid)
                )
                ->get()
                ->contains(fn (Payment $pending) => !$this->isExpired($pending));

            if ($awaitingPayment) {
                return $this->rpcError($id, -31099, 'Order is awaiting payment by another transaction', 'order_id');
            }

            // Create new pending payment
            $payment = Payment::create([
                'order_id' => $order?->id,
                'provider' => 'payme',
                'provider_transaction_id' => $trxId,
                'amount' => Money::fromTiyin($params['amount'])->toString(), // Payme sends tiyin
                'status' => PaymentStatus::PENDING,
                'payload' => $params,
            ]);

            // P3-14: a receipt Payme cannot register (no `detail` was given) is flagged on the payment for the owner
            $receipt = $order ? $this->fiscalReceipt($order) : null;
            if ($receipt && !$receipt->isIssuable()) {
                app(OnlineFiscalRecorder::class)->mark($payment, ['provider' => 'payme', 'status' => OnlineFiscalRecorder::STATUS_BLOCKED, 'problems' => $receipt->problems]);
            }

            return $this->result($id, [
                'create_time' => $this->createTime($payment),
                'transaction' => (string) $payment->id,
                'state' => 1
            ]);
        });
    }

    /**
     * Finalize payment transaction.
     */
    protected function performTransaction(array $params, $id): JsonResponse
    {
        $payment = $this->findPayment($params);

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        [$order, $topupRequest] = $this->targetOf($payment);

        [$response, $paidPayment] = DB::transaction(function () use ($params, $id, $payment, $order, $topupRequest) {
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::PAID) {
                // Repeated PerformTransaction with the same id returns the same result.
                return [$this->performResult($id, $payment), null];
            }

            if ($payment->status !== PaymentStatus::PENDING) {
                return [$this->rpcError($id, -31008, 'Invalid transaction state'), null];
            }

            if ($this->isExpired($payment)) {
                $this->cancelPayment($payment, 4);
                return [$this->rpcError($id, -31008, 'Transaction timed out'), null];
            }

            // Re-check under lock: the account may have been paid, cancelled or changed since CreateTransaction.
            if ($this->accountError($order, $topupRequest, Money::of($payment->amount)->toTiyin())) {
                return [$this->rpcError($id, -31008, 'Unable to perform operation'), null];
            }

            // Mark payment as paid
            $payment->update([
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'payload' => array_merge($payment->payload ?? [], ['perform_params' => $params]),
            ]);

            if ($topupRequest) {
                $topupRequest->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'payload' => $params,
                ]);

                app(LedgerService::class)->creditTopup($topupRequest, 'Payme');

                return [$this->performResult($id, $payment), null];
            }

            $order->update(['payment_status' => PaymentStatus::PAID->value]);

            return [$this->performResult($id, $payment), $payment];
        });

        if ($paidPayment) {
            // Dispatch decoupled event only on the real transition, after commit
            event(new PaymentPaid($paidPayment));
        }

        return $response;
    }

    /**
     * Cancel/refund payment transaction.
     */
    protected function cancelTransaction(array $params, $id): JsonResponse
    {
        $payment = $this->findPayment($params);

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        $reason = (int) ($params['reason'] ?? 0);
        [$order, $topupRequest] = $this->targetOf($payment);

        return DB::transaction(function () use ($id, $payment, $reason, $order, $topupRequest) {
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::PAID) {
                // Refused only while the money is passed on: a top-up reduced the store's debt, or a platform-collected
                // order was credited to the store's wallet and not taken back by the admin refund (the path for it).
                // A STORE-owned order's money is the store's to refund.
                if ($topupRequest || ($order && $order->payment_owner === 'PLATFORM' && $order->status !== OrderStatus::REFUNDED
                    && app(LedgerService::class)->hasSettlement($order))) {
                    return $this->rpcError($id, -31007, 'Unable to cancel transaction');
                }
            } elseif ($payment->status !== PaymentStatus::PENDING) {
                // Repeated CancelTransaction returns the same result.
                return $this->cancelResult($id, $payment);
            }

            $wasPaid = $payment->status === PaymentStatus::PAID;
            $this->cancelPayment($payment, $reason, $wasPaid ? PaymentStatus::REFUNDED : PaymentStatus::CANCELLED);

            if ($topupRequest && $topupRequest->status === 'pending') {
                $topupRequest->update(['status' => 'cancelled']);
            } elseif ($wasPaid && $order?->status === OrderStatus::DELIVERED) {
                // Refunded after delivery: the order is refunded with it, which reverses its commission.
                $payment->update(['payload' => array_merge($payment->payload ?? [], ['refunded_after_delivery' => true])]);
                $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
                app(OrderStatusService::class)->refund($order, null, "Payme to'lovni qaytardi (sabab kodi: {$reason}).", true);
                Log::warning("Payme refund after delivery for order {$order->uuid}: order refunded", [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'payment_owner' => $order->payment_owner,
                    'amount' => Money::of($payment->amount)->toString(),
                ]);
            } elseif ($wasPaid && $order?->status === OrderStatus::REFUNDED) {
                // The money an admin refund left for a manual refund went back through Payme.
                $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
            } elseif ($wasPaid && $order?->payment_status === PaymentStatus::PAID) {
                // If order was already paid, revert back to pending
                $order->update([
                    'payment_status' => PaymentStatus::PENDING->value,
                ]);
            }

            return $this->cancelResult($id, $payment);
        });
    }

    /**
     * Check transaction state.
     */
    protected function checkTransaction(array $params, $id): JsonResponse
    {
        $payment = $this->findPayment($params);

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        return $this->result($id, [
            'create_time' => $this->createTime($payment),
            'perform_time' => $payment->paid_at ? $payment->paid_at->getTimestamp() * 1000 : 0,
            'cancel_time' => $payment->cancelled_at ? $payment->cancelled_at->getTimestamp() * 1000 : 0,
            'transaction' => (string) $payment->id,
            'state' => $this->state($payment),
            'reason' => $payment->payload['cancel_reason'] ?? null
        ]);
    }

    /**
     * Transactions Payme created within [from, to] (ms), for reconciliation.
     */
    protected function getStatement(array $params, $id): JsonResponse
    {
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        if (!is_numeric($from) || !is_numeric($to)) {
            return $this->rpcError($id, -32600, 'Invalid statement period');
        }

        $transactions = Payment::with('order.store')
            ->where('provider', 'payme')
            ->whereNotNull('provider_transaction_id')
            ->whereRaw(
                "(CASE WHEN payload->>'time' ~ '^[0-9]+$' THEN CAST(payload->>'time' AS BIGINT) END) BETWEEN ? AND ?",
                [(int) $from, (int) $to]
            )
            ->get()
            // A statement request names no account, so it is the platform cashbox asking: leave out stores' own cashboxes.
            ->filter(fn (Payment $payment) => $this->storeKey($payment->order) === null)
            ->sortBy(fn (Payment $payment) => $this->createTime($payment))
            ->values()
            ->map(fn (Payment $payment) => [
                'id' => $payment->provider_transaction_id,
                'time' => $this->createTime($payment),
                'amount' => Money::of($payment->amount)->toTiyin(),
                'account' => $payment->payload['account'] ?? (object) [],
                'create_time' => $this->createTime($payment),
                'perform_time' => $payment->paid_at ? $payment->paid_at->getTimestamp() * 1000 : 0,
                'cancel_time' => $payment->cancelled_at ? $payment->cancelled_at->getTimestamp() * 1000 : 0,
                'transaction' => (string) $payment->id,
                'state' => $this->state($payment),
                'reason' => $payment->payload['cancel_reason'] ?? null,
                'receivers' => null,
            ]);

        return $this->result($id, [
            'transactions' => $transactions,
        ]);
    }

    /**
     * SetFiscalData: Payme reports the fiscal receipt it registered for a payment (`PERFORM`) or its cancellation
     * (`CANCEL`, a separate refund receipt). Stored on the payment as payload.fiscal.perform_data / cancel_data, as
     * the docs suggest, and recorded in fiscal_receipts; a repeated call with the same data changes nothing.
     * https://developer.help.paycom.uz/metody-merchant-api/setfiscaldata
     */
    protected function setFiscalData(array $params, $id): JsonResponse
    {
        $type = $params['type'] ?? null;
        $data = $params['fiscal_data'] ?? null;

        if ($this->transactionId($params) === '') {
            return $this->rpcError($id, -32602, 'Invalid parameter: id');
        }
        if (!in_array($type, ['PERFORM', 'CANCEL'], true)) {
            return $this->rpcError($id, -32602, 'Invalid parameter: type');
        }
        if (!is_array($data)) {
            return $this->rpcError($id, -32602, 'Invalid parameter: fiscal_data');
        }
        if (!isset($data['status_code']) || !is_numeric($data['status_code'])) {
            return $this->rpcError($id, -32602, 'Invalid parameter: fiscal_data.status_code');
        }
        $accepted = (int) $data['status_code'] === 0;
        foreach (['receipt_id', 'terminal_id', 'fiscal_sign', 'qr_code_url', 'date', 'message'] as $field) {
            $value = $data[$field] ?? null;
            $required = $accepted && in_array($field, ['receipt_id', 'terminal_id', 'fiscal_sign'], true);
            if (($required && ($value === null || $value === '')) || ($value !== null && !is_scalar($value))) {
                return $this->rpcError($id, -32602, "Invalid parameter: fiscal_data.{$field}");
            }
        }

        $payment = $this->findPayment($params);
        if (!$payment) {
            return $this->rpcError($id, -32001, 'Receipt not found');
        }

        DB::transaction(function () use ($payment, $type, $data, $accepted) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $fiscal = is_array($payment->payload['fiscal'] ?? null) ? $payment->payload['fiscal'] : [];
            $key = $type === 'PERFORM' ? 'perform_data' : 'cancel_data';
            $stored = is_array($fiscal[$key] ?? null) ? $fiscal[$key] : null;

            // Already accepted: a repeated (or late, different) report never replaces the registered receipt
            if ($stored !== null && (int) ($stored['status_code'] ?? -1) === 0) {
                if ((string) ($stored['receipt_id'] ?? '') !== (string) ($data['receipt_id'] ?? '')) {
                    Log::warning('Payme SetFiscalData repeated with another receipt; the first one is kept', [
                        'payment_id' => $payment->id,
                        'type' => $type,
                        'stored_receipt_id' => $stored['receipt_id'] ?? null,
                        'receipt_id' => $data['receipt_id'] ?? null,
                    ]);
                }

                return;
            }

            $fiscalData = array_intersect_key($data, array_flip(['receipt_id', 'status_code', 'message', 'terminal_id', 'fiscal_sign', 'qr_code_url', 'date']));
            $marks = ['provider' => 'payme', $key => $fiscalData];
            $order = $payment->order;

            if (!$accepted) {
                // The OFD refused the receipt: the owner sees it, Payme may report it again once registered
                Log::warning('Payme fiscal receipt was not registered by the OFD', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'type' => $type,
                    'status_code' => $data['status_code'],
                    'message' => $data['message'] ?? null,
                ]);
                $marks += ['status' => OnlineFiscalRecorder::STATUS_FAILED, 'problems' => ['payme_ofd_rejected']];
            } elseif ($order) {
                // A top-up is the platform's own sale, not a restaurant receipt: kept on the payment only
                $result = app(OnlineFiscalRecorder::class)->record(
                    $order,
                    $payment,
                    'payme',
                    $type === 'PERFORM' ? FiscalReceiptType::SALE : FiscalReceiptType::REFUND,
                    'payme:' . $payment->provider_transaction_id . ':' . strtolower($type),
                    ['id' => $payment->provider_transaction_id, 'type' => $type] + $fiscalData,
                    $type === 'CANCEL' ? ($fiscal['receipt_uuid'] ?? null) : null,
                );
                $marks += [
                    'status' => OnlineFiscalRecorder::STATUS_RECORDED,
                    'problems' => [],
                    ($type === 'PERFORM' ? 'receipt_uuid' : 'refund_receipt_uuid') => $result?->receiptUuid,
                ];
            }

            app(OnlineFiscalRecorder::class)->mark($payment, $marks);
        });

        return $this->result($id, ['success' => true]);
    }

    protected function state(Payment $payment): int
    {
        if ($payment->status === PaymentStatus::PAID) {
            return 2;
        }

        if ($payment->status === PaymentStatus::PENDING) {
            return 1;
        }

        return $payment->paid_at !== null ? -2 : -1;
    }

    /**
     * Payme error for an account that cannot be paid with this amount (in tiyin), or null when it can.
     */
    protected function accountError(?Order $order, ?StoreTopupRequest $topupRequest, $amount): ?array
    {
        if ($topupRequest) {
            if ($topupRequest->status !== 'pending') {
                return [-31051, $topupRequest->status === 'paid' ? 'Topup already paid' : 'Topup is not payable', 'order_id'];
            }
            $expectedAmount = $topupRequest->amount;
        } elseif ($order) {
            // An order placed for another payment method (e.g. cash) is not a Payme account
            if ($order->payment_method !== 'payme') {
                return [-31050, 'Order is not payable via Payme', 'order_id'];
            }
            if ($order->payment_status === PaymentStatus::PAID) {
                return [-31051, 'Order already paid', 'order_id'];
            }
            if (in_array($order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true)) {
                return [-31051, 'Order is not payable', 'order_id'];
            }
            $expectedAmount = $order->total_price;
        } else {
            return [-31050, 'Order not found', 'order_id'];
        }

        // Payme sends tiyin (UZS * 100)
        if (!is_numeric($amount) || !Money::fromTiyin($amount)->equals($expectedAmount)) {
            return [-31001, 'Incorrect amount'];
        }

        return null;
    }

    /**
     * @return array{0: ?Order, 1: ?StoreTopupRequest}
     */
    protected function findTarget(string $reference): array
    {
        if (str_starts_with($reference, 'topup_')) {
            $topupUuid = substr($reference, 6);

            return [null, Str::isUuid($topupUuid) ? StoreTopupRequest::where('uuid', $topupUuid)->first() : null];
        }

        if ($reference === '') {
            return [null, null];
        }

        $order = Str::isUuid($reference)
            ? Order::where('uuid', $reference)->first()
            : Order::where('short_code', $reference)->first();

        return [$order, null];
    }

    /**
     * @return array{0: ?Order, 1: ?StoreTopupRequest}
     */
    protected function targetOf(Payment $payment): array
    {
        if ($payment->order_id) {
            return [Order::find($payment->order_id), null];
        }

        $reference = $payment->payload['account']['order_id'] ?? '';

        return $this->findTarget(is_string($reference) ? $reference : '');
    }

    /**
     * @return array{0: ?Order, 1: ?StoreTopupRequest}
     */
    protected function lockTarget(?Order $order, ?StoreTopupRequest $topupRequest): array
    {
        return [
            $order ? Order::whereKey($order->id)->lockForUpdate()->first() : null,
            $topupRequest ? StoreTopupRequest::whereKey($topupRequest->id)->lockForUpdate()->first() : null,
        ];
    }

    protected function belongsToTarget(Payment $payment, ?Order $order, ?StoreTopupRequest $topupRequest): bool
    {
        if ($order) {
            return (int) $payment->order_id === (int) $order->id;
        }

        return $payment->order_id === null
            && $topupRequest !== null
            && ($payment->payload['account']['order_id'] ?? null) === 'topup_' . $topupRequest->uuid;
    }

    protected function findPayment(array $params): ?Payment
    {
        $trxId = $this->transactionId($params);
        if ($trxId === '') {
            return null;
        }

        return Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $trxId)
            ->first();
    }

    protected function transactionId(array $params): string
    {
        $trxId = $params['id'] ?? '';

        return is_scalar($trxId) ? (string) $trxId : '';
    }

    protected function accountReference(array $params): string
    {
        $reference = $params['account']['order_id'] ?? '';

        return is_scalar($reference) ? (string) $reference : '';
    }

    protected function isExpired(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::PENDING
            && $payment->created_at->lt(now()->subMilliseconds(self::TRANSACTION_TIMEOUT_MS));
    }

    protected function cancelPayment(Payment $payment, int $reason, PaymentStatus $status = PaymentStatus::CANCELLED): void
    {
        $payment->update([
            'status' => $status,
            'cancelled_at' => now(),
            'payload' => array_merge($payment->payload ?? [], ['cancel_reason' => $reason]),
        ]);
    }

    /**
     * Payme's own `time` from CreateTransaction, so every response reports the same create_time.
     */
    protected function createTime(Payment $payment): int
    {
        $time = $payment->payload['time'] ?? null;

        return is_numeric($time) ? (int) $time : $payment->created_at->getTimestamp() * 1000;
    }

    protected function performResult($id, Payment $payment): JsonResponse
    {
        return $this->result($id, [
            'perform_time' => $payment->paid_at->getTimestamp() * 1000,
            'transaction' => (string) $payment->id,
            'state' => 2
        ]);
    }

    protected function cancelResult($id, Payment $payment): JsonResponse
    {
        return $this->result($id, [
            'cancel_time' => $payment->cancelled_at ? $payment->cancelled_at->getTimestamp() * 1000 : 0,
            'transaction' => (string) $payment->id,
            'state' => $this->state($payment)
        ]);
    }

    protected function result($id, array $result): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ]);
    }

    /**
     * Return formatted RPC error payload.
     */
    protected function rpcError($id, int $code, string $message, ?string $data = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message
        ];

        // Account errors name the offending account field
        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error
        ]);
    }
}
