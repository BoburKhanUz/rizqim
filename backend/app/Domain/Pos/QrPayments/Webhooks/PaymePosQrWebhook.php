<?php

namespace App\Domain\Pos\QrPayments\Webhooks;

use App\Domain\Pos\QrPayments\Providers\StoreMerchant;
use App\Domain\Pos\QrPayments\QrPaymentService;
use App\Domain\Pos\QrPayments\QrPaymentStatus;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrRefunds;
use App\Models\PaymentProvider;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Services\Payment\PaymeCashbox;
use App\Services\Payment\PaymeRpcMessage;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: Payme Merchant API for a QR shown at a POS till (`account.order_id` = `pos_qr_<uuid>`, or a transaction id
 * such a request holds within the cashbox whose key signed the call and no payment of that cashbox holds, see claims();
 * PaymeWebhookController hands these over before its order logic). A POS QR is always
 * STORE-owned: HTTP Basic `Paycom:<the store's own key>` (payment_settings.payme.secret_key), never the platform's.
 *
 *  - CheckPerformTransaction: `{allow: true}` WITHOUT `detail`, so Payme registers no fiscal receipt (Rizqim issues
 *    the sale receipt; POS_SPEC §4 "no double fiscal"). Not found -31050; wrong amount (tiyin) -31001; paid, expired,
 *    cancelled or the bill changed -31051; another transaction already on it -31099.
 *  - CreateTransaction: holds the request (the expiry waits for Payme's own pending window, 12 h,
 *    qr_payments.payme.hold_minutes; review P6W4 C9) for this transaction id; the same id again: the same answer; a
 *    request no longer payable: -31008. A bill change (or the cashier's cancel) during the hold still cancels it on
 *    our side: PerformTransaction is then refused (-31008) and CancelTransaction answers the cancelled state (-1).
 *  - PerformTransaction: the bill takes the `qr` payment (QrPaymentService::confirm), once; refused: -31008 (Payme then
 *    cancels the transaction on its timeout: the money is not taken).
 *  - CancelTransaction: a pending one is cancelled (state -1); a performed one only while Rizqim's refund asked for it
 *    (QrRefunds: an open refund attempt, Payme receipts.cancel; state -2), otherwise -31007 — the bill's money is taken back by a refund at the
 *    till, never behind its back.
 *  - CheckTransaction: the state; SetFiscalData: answered, never recorded (Payme registers no receipt for these).
 */
final class PaymePosQrWebhook
{
    public function __construct(private readonly QrPaymentService $service) {}

    /**
     * Whether this request is about a POS QR: its account is a `pos_qr_<uuid>`, or (no account: PerformTransaction,
     * CancelTransaction, CheckTransaction, SetFiscalData) its transaction id is held by a QR of the cashbox whose key
     * signed it and by none of that cashbox's payments. Security review F2: a QR never captures the id of a payment —
     * the platform's cashbox has no QRs, another cashbox's QRs are not looked at, and within one cashbox an id is a
     * payment's or a QR's, never both (create(), PaymeWebhookController::createTransaction); for an id recorded as both
     * before that rule, the payment wins.
     *
     * @param  array<string, mixed>  $params
     * @param  list<int>  $storeIds  the stores of the key's cashbox (PaymeCashbox); [] for the platform's or no key
     */
    public static function claims(array $params, array $storeIds): bool
    {
        $account = $params['account']['order_id'] ?? null;
        if (is_string($account) && PosQrPayment::isAccount($account)) {
            return true;
        }
        if (is_scalar($account) && (string) $account !== '') {
            return false; // an order's or a top-up's account
        }
        $id = $params['id'] ?? null;
        if (!is_scalar($id) || (string) $id === '' || $storeIds === [] || self::byTransaction((string) $id, $storeIds) === null) {
            return false;
        }

        return !self::paymentHolds((string) $id, $storeIds);
    }

    /**
     * Whether a POS QR of these stores holds this Payme transaction id.
     *
     * @param  list<int>  $storeIds
     */
    public static function heldByQr(string $transaction, array $storeIds, ?int $except = null): bool
    {
        return $storeIds !== [] && PosQrPayment::where('provider', QrProvider::PAYME->value)->whereIn('store_id', $storeIds)
            ->where('provider_transaction_id', $transaction)->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists();
    }

    /**
     * Whether a payment (an order's) of these stores' own cashbox holds this Payme transaction id.
     *
     * @param  list<int>  $storeIds
     */
    private static function paymentHolds(string $transaction, array $storeIds): bool
    {
        return \App\Models\Payment::where('provider', 'payme')
            ->whereIn('merchant_scope', array_map(fn (int $storeId) => \App\Models\Payment::storeScope($storeId), $storeIds))
            ->where('provider_transaction_id', $transaction)->exists();
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<int>  $storeIds  the stores of the key's cashbox
     */
    public function handle(Request $request, string $method, array $params, mixed $id, array $storeIds = []): JsonResponse
    {
        $qr = $this->target($params, $method, $storeIds);
        if (!$this->authorized($request, $qr)) {
            return $this->error($id, -32504, 'Insufficient privileges');
        }

        try {
            return match ($method) {
                'CheckPerformTransaction' => $this->checkPerform($qr, $params, $id),
                'CreateTransaction' => $this->create($qr, $params, $id, $storeIds),
                'PerformTransaction' => $this->perform($qr, $id),
                'CancelTransaction' => $this->cancel($qr, $params, $id),
                'CheckTransaction' => $qr !== null && $qr->provider_transaction_id !== null ? $this->result($id, $this->state($qr)) : $this->error($id, -31003, 'Transaction not found'),
                'SetFiscalData' => $this->setFiscalData($qr, $id),
                default => $this->error($id, -32601, 'Method not found', $method),
            };
        } catch (\Throwable $e) {
            Log::error('Payme POS QR webhook exception: ' . $e->getMessage(), ['qr' => $qr?->uuid]);

            return $this->error($id, -32400, 'System error');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function checkPerform(?PosQrPayment $qr, array $params, mixed $id): JsonResponse
    {
        if ($error = $this->accountError($qr, $params['amount'] ?? null, null)) {
            return $this->error($id, ...$error);
        }

        return $this->result($id, ['allow' => true]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function create(?PosQrPayment $qr, array $params, mixed $id, array $storeIds): JsonResponse
    {
        $transaction = $this->transactionId($params);
        if ($transaction === '') {
            return $this->error($id, -32600, 'Transaction id is required');
        }
        if ($qr === null) {
            return $this->error($id, -31050, 'Order not found', 'order_id');
        }

        return DB::transaction(function () use ($qr, $params, $id, $transaction, $storeIds) {
            // Security review F2: the same lock as an order's CreateTransaction of this id; the first to commit owns it
            PaymeCashbox::lockTransaction($transaction);
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            // A transaction id of this cashbox that a payment or another QR holds is theirs, never this QR's
            $cashbox = array_values(array_unique([...$storeIds, (int) $qr->store_id]));
            if (self::paymentHolds($transaction, $cashbox) || self::heldByQr($transaction, $cashbox, (int) $qr->id)) {
                return $this->error($id, -31050, 'Transaction belongs to another order', 'order_id');
            }
            if ($qr->provider_transaction_id === $transaction) {
                if ($qr->status !== QrPaymentStatus::PENDING || !$this->service->payable($qr)) {
                    return $this->error($id, -31008, 'Unable to perform operation');
                }

                return $this->result($id, ['create_time' => $this->createTime($qr), 'transaction' => (string) $qr->id, 'state' => 1]);
            }
            if ($error = $this->accountError($qr, $params['amount'] ?? null, $transaction)) {
                return $this->error($id, ...$error);
            }

            $this->service->hold($qr, $transaction, ['payme' => ['time' => is_numeric($params['time'] ?? null) ? (int) $params['time'] : now()->getTimestampMs()]]);

            return $this->result($id, ['create_time' => $this->createTime($qr), 'transaction' => (string) $qr->id, 'state' => 1]);
        });
    }

    private function perform(?PosQrPayment $qr, mixed $id): JsonResponse
    {
        if ($qr === null || $qr->provider_transaction_id === null) {
            return $this->error($id, -31003, 'Transaction not found');
        }
        if ($qr->status === QrPaymentStatus::PAID) {
            return $this->result($id, $this->performed($qr));
        }

        $outcome = $this->service->confirm($qr, (string) $qr->provider_transaction_id, ['payme' => 'perform']);
        $qr->refresh();

        return in_array($outcome, ['paid', 'already_confirmed'], true)
            ? $this->result($id, $this->performed($qr))
            : $this->error($id, -31008, 'Unable to perform operation');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function cancel(?PosQrPayment $qr, array $params, mixed $id): JsonResponse
    {
        if ($qr === null || $qr->provider_transaction_id === null) {
            return $this->error($id, -31003, 'Transaction not found');
        }
        $reason = is_numeric($params['reason'] ?? null) ? (int) $params['reason'] : null;

        if ($qr->status === QrPaymentStatus::PENDING) {
            $this->service->cancelBy($qr, 'payme_cancel' . ($reason !== null ? ":{$reason}" : ''));
            $qr->refresh();
        }

        if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
            $payme = (array) (($qr->provider_payload ?? [])['payme'] ?? []);
            if (!isset($payme['cancel_time'])) {
                if (!QrRefunds::requested($qr)) {
                    return $this->error($id, -31007, 'Unable to cancel transaction');
                }
                // Asked by Rizqim's refund (receipts.cancel, its attempt committed before the call: review P6W4 C2).
                // Once recorded, the attempt ends `refunded` even if receipts.cancel then answers an error or nothing
                $payload = $qr->provider_payload ?? [];
                $payload['payme'] = $payme + ['cancel_time' => now()->getTimestampMs(), 'cancel_reason' => $reason];
                PosQrPayment::whereKey($qr->id)->update(['provider_payload' => json_encode($payload)]);
                $qr->provider_payload = $payload;
                QrRefunds::cancelledByProvider($qr);
            }
        }

        $state = $this->state($qr);

        return $this->result($id, ['cancel_time' => $state['cancel_time'], 'transaction' => (string) $qr->id, 'state' => $state['state']]);
    }

    private function setFiscalData(?PosQrPayment $qr, mixed $id): JsonResponse
    {
        if ($qr === null) {
            return $this->error($id, -32001, 'Receipt not found');
        }
        // Rizqim issued the sale receipt of the bill; a receipt Payme reports for it would be a second one
        Log::warning('Payme SetFiscalData for a POS QR payment ignored (Rizqim issues its receipt)', ['qr' => $qr->uuid]);

        return $this->result($id, ['success' => true]);
    }

    /**
     * @return array{0: int, 1: string, 2?: string}|null
     */
    private function accountError(?PosQrPayment $qr, mixed $amount, ?string $transaction): ?array
    {
        if ($qr === null || $qr->provider !== QrProvider::PAYME) {
            return [-31050, 'Order not found', 'order_id'];
        }
        // Payme sends whole tiyin (UZS * 100): a fraction would be rounded into a match (as for orders)
        if (!is_numeric($amount) || BigDecimal::of((string) $amount)->hasNonZeroFractionalPart() || !Money::fromTiyin($amount)->equals($qr->amount)) {
            return [-31001, 'Incorrect amount'];
        }
        if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
            return [-31051, 'Order already paid', 'order_id'];
        }
        if (!$this->service->payable($qr)) {
            return [-31051, 'Order is not payable', 'order_id'];
        }
        if ($qr->provider_transaction_id !== null && $qr->provider_transaction_id !== $transaction) {
            return [-31099, 'Order is awaiting payment by another transaction', 'order_id'];
        }

        return null;
    }

    /**
     * @return array{create_time: int, perform_time: int, cancel_time: int, transaction: string, state: int, reason: int|null}
     */
    private function state(PosQrPayment $qr): array
    {
        $payme = (array) (($qr->provider_payload ?? [])['payme'] ?? []);
        $cancelTime = isset($payme['cancel_time']) ? (int) $payme['cancel_time'] : ($qr->cancelled_at?->getTimestampMs() ?? 0);
        $state = match (true) {
            $qr->status === QrPaymentStatus::PENDING => 1,
            ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) && !isset($payme['cancel_time']) => 2,
            $qr->paid_at !== null => -2,
            default => -1,
        };
        $reason = isset($payme['cancel_reason']) ? (int) $payme['cancel_reason'] : (str_starts_with((string) $qr->cancel_reason, 'payme_cancel:') ? (int) substr((string) $qr->cancel_reason, 13) : ($state === -1 ? 3 : null));

        return [
            'create_time' => $this->createTime($qr),
            'perform_time' => $qr->paid_at?->getTimestampMs() ?? 0,
            'cancel_time' => $state < 0 ? $cancelTime : 0,
            'transaction' => (string) $qr->id,
            'state' => $state,
            'reason' => $state < 0 ? $reason : null,
        ];
    }

    /**
     * One GetStatement transaction of the store's own cashbox (PaymeWebhookController::getStatement).
     *
     * @return array<string, mixed>
     */
    public function statementRow(PosQrPayment $qr): array
    {
        $state = $this->state($qr);

        return [
            'id' => (string) $qr->provider_transaction_id,
            'time' => $state['create_time'],
            'amount' => Money::of($qr->amount)->toTiyin(),
            'account' => ['order_id' => $qr->account()],
            ...$state,
            'receivers' => null,
        ];
    }

    /**
     * @return array{perform_time: int, transaction: string, state: int}
     */
    private function performed(PosQrPayment $qr): array
    {
        return ['perform_time' => $qr->paid_at?->getTimestampMs() ?? 0, 'transaction' => (string) $qr->id, 'state' => 2];
    }

    private function createTime(PosQrPayment $qr): int
    {
        $time = (($qr->provider_payload ?? [])['payme'] ?? [])['time'] ?? null;

        return is_numeric($time) ? (int) $time : ($qr->held_at ?? $qr->created_at ?? now())->getTimestampMs();
    }

    /**
     * The QR a request is about: CheckPerformTransaction and CreateTransaction name it by their account; the methods
     * about a transaction by its id, among the QRs of the key's cashbox only.
     *
     * @param  array<string, mixed>  $params
     * @param  list<int>  $storeIds
     */
    private function target(array $params, string $method, array $storeIds): ?PosQrPayment
    {
        if (in_array($method, ['CheckPerformTransaction', 'CreateTransaction'], true)) {
            $account = $params['account']['order_id'] ?? null;

            return is_string($account) ? PosQrPayment::forAccount($account) : null;
        }
        $transaction = $this->transactionId($params);

        return $transaction !== '' ? self::byTransaction($transaction, $storeIds) : null;
    }

    /**
     * @param  list<int>  $storeIds
     */
    private static function byTransaction(string $transaction, array $storeIds): ?PosQrPayment
    {
        return $storeIds === [] ? null : PosQrPayment::where('provider', QrProvider::PAYME->value)->whereIn('store_id', $storeIds)
            ->where('provider_transaction_id', $transaction)->orderBy('id')->first();
    }

    /**
     * The store's own Payme key for a known request (a sandbox one has none); an unknown `pos_qr_` account is
     * answered "not found" only to the platform's key, as an unknown order is.
     */
    private function authorized(Request $request, ?PosQrPayment $qr): bool
    {
        $header = (string) $request->header('Authorization');
        $credentials = str_starts_with($header, 'Basic ') ? base64_decode(substr($header, 6), true) : false;
        $parts = is_string($credentials) ? explode(':', $credentials, 2) : [];
        if (count($parts) < 2 || $parts[0] !== 'Paycom') {
            return false;
        }

        if ($qr === null) {
            $expected = PaymentProvider::platformCredential('payme', 'secret_key');
        } else {
            $store = Store::find($qr->store_id);
            $expected = $store !== null && $qr->driver === 'payme' ? StoreMerchant::credential($store, 'payme', 'secret_key') : null;
        }

        if ($expected === null || !hash_equals($expected, trim($parts[1]))) {
            return false;
        }
        // Fourth review: a store whose own key is the platform's has no QR cashbox of its own. A call signed with the
        // platform key is the platform cashbox's and never routed to a QR by transaction id (PaymeWebhookController::
        // qrStores()), so a QR held here could never be performed or cancelled: every QR call is refused up front
        $platform = $qr !== null ? PaymentProvider::platformCredential('payme', 'secret_key') : null;
        if ($platform !== null && hash_equals($platform, $expected)) {
            return false;
        }
        // Third review M1: the store's own key signed this; a stale digest of it is repaired (PaymeCashbox), so the
        // calls about the QR's transaction id find the store through the index again
        if ($qr !== null && isset($store) && $store->getAttribute('payme_key_digest') !== PaymeCashbox::digest($expected)) {
            PaymeCashbox::remember((int) $store->id, $expected, $store->getRawOriginal('payment_settings'));
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function transactionId(array $params): string
    {
        $id = $params['id'] ?? '';

        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message, ?string $data = null): JsonResponse
    {
        // The payer sees `message` in their language (Payme "Формат ответа": a localized object); SetFiscalData's
        // -32001 stays plain text, as PaymeWebhookController's
        $error = ['code' => $code, 'message' => $code === -32001 ? $message : PaymeRpcMessage::of($message)];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error]);
    }
}
