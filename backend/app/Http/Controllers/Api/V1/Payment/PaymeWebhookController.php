<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\OrderMerchantAccount;
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
use App\Services\Payment\PaymeCashbox;
use App\Services\Payment\PaymeRpcMessage;
use App\Services\Payment\StoreRefundReview;
use App\Domain\Pos\QrPayments\Webhooks\PaymePosQrWebhook;
use App\Models\PosQrPayment;
use Brick\Math\BigDecimal;
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
     * Security review F5/F6: the cashbox the request's key speaks for (cashboxOf): null for the platform's, the ids of the
     * stores whose own key it is, false for no known key. A transaction id is looked up only within that cashbox's
     * merchant accounts (Payment::merchant_scope), so a transaction of another cashbox is, to this caller, simply
     * unknown. Reset per request: the route keeps its controller instance.
     *
     * @var list<int>|false|null
     */
    private array|false|null $cashbox = false;

    /**
     * The stores whose own Payme key the request's key is, also when it is the platform's (a store that saved the
     * platform's key as its own still has its transactions found). Third review M1: also the store of an order whose
     * own key authorised CheckPerformTransaction / CreateTransaction (authorizeAccount), whatever its digest says.
     *
     * @var list<int>
     */
    private array $keyStores = [];

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
        // "Общие ошибки": a body that is not JSON is -32700 (Laravel would read it as an empty request)
        $raw = (string) $request->getContent();
        if (trim($raw) !== '' && !is_array(json_decode($raw, true))) {
            return $this->rpcError(null, -32700, 'Parse error');
        }

        $payload = $request->all();
        Log::info("Payme Webhook Received", $payload);

        $method = $payload['method'] ?? '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $id = $payload['id'] ?? null;

        try {
            // Inside a try: a database error looking the key up is a -32400, never an HTTP 500
            $this->cashbox = $this->cashboxOf($request, $this->transactionId($params));

            // P6-41: a QR shown at a POS till (`pos_qr_<uuid>`, or a transaction id one of this cashbox's QRs holds and
            // none of its payments does) confirms a bill, never an order: the store's own key, no `detail` and no
            // SetFiscalData record (Rizqim issues the receipt). Third review L2: the platform's cashbox has no QRs, so
            // a call signed with the platform key is never routed by transaction id to the QRs of a store that saved
            // the platform's key as its own (qrStores())
            $qrRoute = PaymePosQrWebhook::claims($params, $this->qrStores());
        } catch (\Throwable $e) {
            Log::error('Payme Webhook Exception: ' . $e->getMessage());

            return $this->rpcError($id, -32400, 'System error');
        }
        if ($qrRoute) {
            return app(PaymePosQrWebhook::class)->handle($request, (string) $method, $params, $id, $this->qrStores());
        }

        // 1. HTTP Basic `Paycom:<key>`. Security review F3: a method about an account (CheckPerformTransaction,
        // CreateTransaction) is signed by that account's key, whatever transaction id it names; one about a transaction
        // id (PerformTransaction, CancelTransaction, CheckTransaction, SetFiscalData) and GetStatement by a known key,
        // the id then looked up within that key's cashbox only
        $authorized = in_array($method, ['CheckPerformTransaction', 'CreateTransaction'], true)
            ? $this->authorizeAccount($request, $params)
            : $this->cashbox !== false;
        if (!$authorized) {
            return $this->rpcError($id, -32504, 'Insufficient privileges');
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
                    // GetStatement names no account: the key says which cashbox asks (the platform's, or a restaurant's own)
                    return $this->getStatement($params, $id, $this->cashbox === false ? null : $this->cashbox);
                case 'SetFiscalData':
                    return $this->setFiscalData($params, $id);
                default:
                    // "Общие ошибки": the requested method's name goes in `data`
                    return $this->rpcError($id, -32601, 'Method not found', is_scalar($method) ? (string) $method : null);
            }
        } catch (\Throwable $e) {
            // -32400: a system failure (database, undefined behaviour) inside a method's transaction, which rolled back:
            // nothing was recorded, so Payme may retry. What runs after a commit (PerformTransaction's PaymentPaid) never
            // gets here: a performed transaction is always answered as performed.
            Log::error("Payme Webhook Exception: " . $e->getMessage());

            return $this->rpcError($id, -32400, 'System error');
        }
    }

    /**
     * CheckPerformTransaction and CreateTransaction: signed with the key of the account they name (the platform's for
     * a top-up or an account that does not exist, so an unknown account is answered "not found" only to the platform).
     */
    protected function authorizeAccount(Request $request, array $params): bool
    {
        $presented = $this->presentedKey($request);
        if ($presented === null) {
            return false;
        }

        $order = $this->findTarget($this->accountReference($params))[0];
        $expectedKey = $this->secretKey($order);
        if ($expectedKey === null) {
            Log::error('Payme webhook rejected: no merchant key is configured for this payment (PAYME_SECRET_KEY or the store\'s own key).');

            return false;
        }

        if (!hash_equals($expectedKey, $presented)) {
            return false;
        }

        // Third review M1: the order's own key signed this, so its store's account is the request's whatever the
        // store's digest says (a stale one is repaired): the replay of a CreateTransaction always finds its payment
        if (OrderMerchantAccount::isStore($order)) {
            $storeId = (int) $order->store_id;
            if ($order->store?->getAttribute('payme_key_digest') !== PaymeCashbox::digest($presented)) {
                PaymeCashbox::remember($storeId, $presented);
            }
            if (!in_array($storeId, $this->keyStores, true)) {
                $this->keyStores[] = $storeId;
                sort($this->keyStores);
            }
            if ($this->cashbox !== null) {
                $this->cashbox = $this->keyStores;
            }
        }

        return true;
    }

    /**
     * The key of HTTP Basic `Paycom:<key>`, or null.
     */
    protected function presentedKey(Request $request): ?string
    {
        $header = (string) $request->header('Authorization');
        if (!str_starts_with($header, 'Basic ')) {
            return null;
        }

        $credentials = base64_decode(substr($header, 6), true);
        if ($credentials === false) {
            return null;
        }

        // Payme sends basic auth as 'Paycom:secret_key'
        $parts = explode(':', $credentials, 2);
        if (count($parts) < 2 || $parts[0] !== 'Paycom' || trim($parts[1]) === '') {
            return null;
        }

        return trim($parts[1]);
    }

    /**
     * Which cashbox the request's key speaks for: null for the platform's key, the ids of the stores whose own Payme key
     * it is (one cashbox may serve several stores of a chain, each saving the same key), or false for no known key. The
     * platform key wins. Security review F6: the stores are found through the indexed digest of their key
     * (PaymeCashbox), never by decrypting every store's settings.
     *
     * Third review M1: with the request's transaction id, the stores that hold it are checked against their decrypted key
     * too when their digest is stale (PaymeCashbox::storesWithKey, bounded).
     *
     * @return list<int>|false|null
     */
    protected function cashboxOf(Request $request, string $transactionId = ''): array|false|null
    {
        $this->keyStores = [];
        $presented = $this->presentedKey($request);
        if ($presented === null) {
            return false;
        }

        $this->keyStores = PaymeCashbox::storesWithKey($presented, $transactionId);
        $platformKey = OrderMerchantAccount::secretKey('payme', null);
        if ($platformKey !== null && hash_equals($platformKey, $presented)) {
            return null;
        }

        return $this->keyStores !== [] ? $this->keyStores : false;
    }

    /**
     * The stores whose POS QRs a call may be about by its transaction id: the key's stores, none for the platform's
     * key (third review L2: the platform's cashbox has no QRs).
     *
     * @return list<int>
     */
    protected function qrStores(): array
    {
        return $this->cashbox === null ? [] : $this->keyStores;
    }

    /**
     * The merchant accounts (Payment::merchant_scope) of the request's cashbox.
     *
     * @return list<string>
     */
    protected function scopes(): array
    {
        return [
            ...($this->cashbox === null ? [Payment::SCOPE_PLATFORM] : []),
            ...array_map(fn (int $storeId) => Payment::storeScope($storeId), $this->keyStores),
        ];
    }

    /**
     * A store's own key may only confirm money paid into the store's own merchant account;
     * platform-collected orders and top-ups must be signed with the platform key. Review C#2: chosen by the order's
     * `payment_owner` (fixed at checkout) for every method, CancelTransaction included, never by the store's current
     * payment account type.
     */
    protected function secretKey(?Order $order): ?string
    {
        return OrderMerchantAccount::secretKey('payme', $order);
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
            // Security review F2: one transaction id of a cashbox is one payment or one POS QR, never both; every
            // CreateTransaction of this id (order, top-up or QR) is serialised here, so the first one to commit owns it
            // and the other is answered "belongs to another order"
            PaymeCashbox::lockTransaction($trxId);
            // All transactions of one account are serialised on its row.
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);

            // Security review F4/F5: the id within this cashbox's merchant accounts only. An id another cashbox holds
            // is unknown here and answered as such (a payment of this account is created under it), never "belongs to
            // another order", which told a store which Payme ids exist elsewhere
            $payment = Payment::where('provider', 'payme')
                ->whereIn('merchant_scope', $this->scopes())
                ->where('provider_transaction_id', $trxId)
                ->orderBy('id')
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

            // Security review F2: a POS QR of this cashbox holds the id: its transaction, not this account's
            if (PaymePosQrWebhook::heldByQr($trxId, $this->keyStores)) {
                return $this->rpcError($id, -31050, 'Transaction belongs to another order', 'order_id');
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
                // M4: the order's store, or the store topping up its balance
                'store_id' => $order->store_id ?? $topupRequest?->store_id,
                'provider' => 'payme',
                'merchant_scope' => Payment::merchantScopeOf($order),
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

        [$response, $paidPayment, $replayed] = DB::transaction(function () use ($params, $id, $payment, $order, $topupRequest) {
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::PAID) {
                // Repeated PerformTransaction with the same id returns the same result (and re-fires PaymentPaid below).
                return [$this->performResult($id, $payment), null, $payment];
            }

            if ($payment->status !== PaymentStatus::PENDING) {
                return [$this->rpcError($id, -31008, 'Invalid transaction state'), null, null];
            }

            if ($this->isExpired($payment)) {
                $this->cancelPayment($payment, 4);
                return [$this->rpcError($id, -31008, 'Transaction timed out'), null, null];
            }

            // Re-check under lock: the account may have been paid, cancelled or changed since CreateTransaction.
            if ($this->accountError($order, $topupRequest, Money::of($payment->amount)->toTiyin())) {
                return [$this->rpcError($id, -31008, 'Unable to perform operation'), null, null];
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

                return [$this->performResult($id, $payment), null, null];
            }

            $order->update(['payment_status' => PaymentStatus::PAID->value]);

            return [$this->performResult($id, $payment), $payment, null];
        });

        if ($paidPayment) {
            // Go-live review M1: the payment is committed PAID, so the answer is the success whatever a listener does.
            // A replay of the performed transaction fires the event again (the listener books everything once), which
            // is how a failed settlement or auto-accept catches up.
            $this->firePaid($paidPayment);
        } elseif ($replayed) {
            $this->firePaid($replayed, true);
        }

        return $response;
    }

    /**
     * PaymentPaid after the commit; a listener failure is logged (with the ids, never a key) and never changes the
     * answer to Payme. A replay re-fires it only for an order still paid (never revives a refunded or reverted one).
     */
    protected function firePaid(Payment $payment, bool $replay = false): void
    {
        if (!$payment->order_id) {
            return;
        }

        try {
            if ($replay && Order::whereKey($payment->order_id)->first(['id', 'payment_status'])?->payment_status !== PaymentStatus::PAID) {
                return;
            }

            event(new PaymentPaid($payment));
        } catch (\Throwable $e) {
            Log::error('Payme PaymentPaid listener failed after the payment was committed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'replay' => $replay,
            ]);
        }
    }

    /**
     * Cancel/refund payment transaction. Wave P: a performed transaction of a delivered STORE-owned order (the store
     * knows the key that signed this) is cancelled and shown refunded, but RIZQIM's commission is kept until a manager
     * decides it (StoreRefundReview); answered state -2 like any cancel, so Payme never retries it or sees it broken.
     */
    protected function cancelTransaction(array $params, $id): JsonResponse
    {
        $payment = $this->findPayment($params);

        if (!$payment) {
            return $this->rpcError($id, -31003, 'Transaction not found');
        }

        // Wave P review F4: Payme sends an integer reason (1-5, 10); anything else is a malformed request, refused
        // before anything changes (-32600), never coerced into a reason code
        $reason = $params['reason'] ?? null;
        if (!is_int($reason) || $reason < 1 || $reason > 10) {
            return $this->rpcError($id, -32600, 'Invalid parameter: reason');
        }
        [$order, $topupRequest] = $this->targetOf($payment);

        return DB::transaction(function () use ($id, $payment, $reason, $order, $topupRequest) {
            [$order, $topupRequest] = $this->lockTarget($order, $topupRequest);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::PAID) {
                // Refused only while the money is passed on: a top-up reduced the store's debt, or a platform-collected
                // order was credited to the store's wallet and not taken back by the admin refund (the path for it).
                // A STORE-owned order's money is the store's to refund (wave P: of a delivered one, without RIZQIM's
                // commission until a manager decides it, StoreRefundReview below).
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
            } elseif ($wasPaid && StoreRefundReview::applies($order)) {
                // Wave P: the store's own cashbox key signed this, and the store knows it: a forged cancel and a real
                // cabinet refund look the same, and Payme's Merchant API has no read-back of the transaction. Answered
                // as cancelled (state -2, Payme's side stays consistent), shown as refunded, the commission kept until a
                // manager decides it (StoreRefundReview)
                app(StoreRefundReview::class)->hold($order, $payment, 'payme', $reason);
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
            } elseif ($wasPaid && $order && in_array($order->status, [OrderStatus::REFUNDED, OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
                // The money an admin refund, or the cancellation of a paid order, left for a manual refund went back
                // through Payme: the order is refunded, never unpaid again (as Paylov's applyCancel)
                $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
            } elseif ($wasPaid && $order?->payment_status === PaymentStatus::PAID) {
                // If order was already paid, revert back to pending
                $order->update([
                    'payment_status' => PaymentStatus::PENDING->value,
                ]);
            }

            // Security review F1: money given back while the order is still in progress is no part of what delivery
            // books the commission on (RefundRequestService::deliveryShareOf)
            if ($wasPaid && $order && !in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::REFUNDED, OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
                $payment->update(['payload' => array_merge($payment->payload ?? [], ['refunded_before_delivery' => true])]);
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
    /**
     * @param  list<int>|null  $cashbox  the stores of a restaurant's own cashbox, null for the platform's
     */
    protected function getStatement(array $params, $id, ?array $cashbox = null): JsonResponse
    {
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        if (!is_numeric($from) || !is_numeric($to)) {
            return $this->rpcError($id, -32600, 'Invalid statement period');
        }

        // The platform cashbox: the platform-collected orders and top-ups; a store's: its STORE-owned orders (the
        // merchant account each payment was made in, Payment::merchant_scope)
        $transactions = Payment::query()
            ->where('provider', 'payme')
            ->whereIn('merchant_scope', $cashbox === null ? [Payment::SCOPE_PLATFORM] : array_map(fn (int $storeId) => Payment::storeScope($storeId), $cashbox))
            ->whereNotNull('provider_transaction_id')
            ->whereRaw(
                "(CASE WHEN payload->>'time' ~ '^[0-9]+$' THEN CAST(payload->>'time' AS BIGINT) END) BETWEEN ? AND ?",
                [(int) $from, (int) $to]
            )
            ->get()
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

        // A store's own cashbox also takes the till's QR payments (P6-41)
        if ($cashbox !== null) {
            $qrWebhook = app(PaymePosQrWebhook::class);
            $transactions = $transactions->concat(PosQrPayment::query()
                ->whereIn('store_id', $cashbox)
                ->where('provider', \App\Domain\Pos\QrPayments\QrProvider::PAYME->value)
                ->where('driver', 'payme')
                ->whereNotNull('provider_transaction_id')
                ->whereRaw(
                    "(CASE WHEN provider_payload->'payme'->>'time' ~ '^[0-9]+$' THEN CAST(provider_payload->'payme'->>'time' AS BIGINT) END) BETWEEN ? AND ?",
                    [(int) $from, (int) $to]
                )
                ->get()
                ->map(fn (PosQrPayment $qr) => $qrWebhook->statementRow($qr)));
        }

        // "по возрастанию даты создания"
        return $this->result($id, [
            'transactions' => $transactions->sortBy('time')->values(),
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

        $recorded = DB::transaction(function () use ($payment, $type, $data, $accepted) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Payme registers a sale receipt after PerformTransaction and a refund receipt after cancelling a performed
            // transaction. A store's own cashbox key is known to the store, so a report out of that order (a sale of a
            // transaction never performed, a refund of one still paid) is no receipt of ours: answered like an
            // unknown one, nothing recorded
            if (!$this->hasReceipt($payment, $type)) {
                return false;
            }
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

                return true;
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
                    $this->fiscalKey($payment, $order, $type),
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

            return true;
        });

        if (!$recorded) {
            return $this->rpcError($id, -32001, 'Receipt not found');
        }

        return $this->result($id, ['success' => true]);
    }

    /**
     * The idempotency key of the receipt Payme registered for this payment. Security review F5: one id may be a payment
     * of the platform's cashbox and of the store's own, both of this store, so the platform's key names its account
     * (`payme:platform:<id>:<type>`). A receipt recorded before that change has the old key (`payme:<id>:<type>`): when
     * this order's receipt is on record under it, that key is kept, so a replay finds it instead of recording the sale
     * again (the old key of another order — the store's own payment with the same id — is never taken).
     */
    protected function fiscalKey(Payment $payment, Order $order, string $type): string
    {
        $legacy = 'payme:' . $payment->provider_transaction_id . ':' . strtolower($type);
        if ($payment->merchant_scope !== Payment::SCOPE_PLATFORM) {
            return $legacy;
        }

        $recordedUnderLegacy = \App\Models\FiscalReceipt::query()
            ->where('store_id', $order->store_id)
            ->where('idempotency_key', $legacy)
            ->where('subject_type', 'order')
            ->where('subject_id', $order->id)
            ->exists();

        return $recordedUnderLegacy ? $legacy : 'payme:platform:' . $payment->provider_transaction_id . ':' . strtolower($type);
    }

    /**
     * Whether Payme can have registered a receipt of this type for the payment: a sale (PERFORM) once the transaction
     * was performed (paid, or refunded after it), a refund (CANCEL) once a performed transaction was cancelled.
     */
    protected function hasReceipt(Payment $payment, string $type): bool
    {
        if ($payment->paid_at === null) {
            return false;
        }

        return $type === 'PERFORM'
            ? in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
            : $payment->status === PaymentStatus::REFUNDED;
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
            if (in_array($order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true)
                || StoreRefundReview::moneyWentBack($order)) {
                return [-31051, 'Order is not payable', 'order_id'];
            }
            $expectedAmount = $order->total_price;
        } else {
            return [-31050, 'Order not found', 'order_id'];
        }

        // Payme sends whole tiyin (UZS * 100): a fraction would be rounded into a match
        if (!is_numeric($amount) || BigDecimal::of((string) $amount)->hasNonZeroFractionalPart() || !Money::fromTiyin($amount)->equals($expectedAmount)) {
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

    /**
     * The payment of this transaction id within the request's cashbox (security review F5: a transaction of another
     * cashbox is unknown to this caller).
     */
    protected function findPayment(array $params): ?Payment
    {
        $trxId = $this->transactionId($params);
        if ($trxId === '') {
            return null;
        }

        return Payment::where('provider', 'payme')
            ->whereIn('merchant_scope', $this->scopes())
            ->where('provider_transaction_id', $trxId)
            ->orderBy('id')
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
        // The payer sees `message` in their language; SetFiscalData's parameter errors (-32602, -32001) stay plain text
        $error = [
            'code' => $code,
            'message' => in_array($code, [-32602, -32001], true) ? $message : PaymeRpcMessage::of($message),
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
