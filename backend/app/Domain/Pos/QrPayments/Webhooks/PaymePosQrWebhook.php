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
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P6-41: Payme Merchant API for a QR shown at a POS till (`account.order_id` = `pos_qr_<uuid>`, or a transaction id
 * such a request holds; PaymeWebhookController hands these over before its order logic). A POS QR is always
 * STORE-owned: HTTP Basic `Paycom:<the store's own key>` (payment_settings.payme.secret_key), never the platform's.
 *
 *  - CheckPerformTransaction: `{allow: true}` WITHOUT `detail`, so Payme registers no fiscal receipt (Rizqim issues
 *    the sale receipt; POS_SPEC §4 "no double fiscal"). Not found -31050; wrong amount (tiyin) -31001; paid, expired,
 *    cancelled or the bill changed -31051; another transaction already on it -31099.
 *  - CreateTransaction: holds the request (the expiry waits) for this transaction id; the same id again: the same
 *    answer; a request no longer payable: -31008.
 *  - PerformTransaction: the bill takes the `qr` payment (QrPaymentService::confirm), once; refused: -31008 (Payme then
 *    cancels the transaction on its timeout: the money is not taken).
 *  - CancelTransaction: a pending one is cancelled (state -1); a performed one only while Rizqim's refund asked for it
 *    (QrRefunds, Payme receipts.cancel; state -2), otherwise -31007 — the bill's money is taken back by a refund at the
 *    till, never behind its back.
 *  - CheckTransaction: the state; SetFiscalData: answered, never recorded (Payme registers no receipt for these).
 */
final class PaymePosQrWebhook
{
    public function __construct(private readonly QrPaymentService $service) {}

    /**
     * Whether this request is about a POS QR (its account, or a transaction id one of them holds).
     *
     * @param  array<string, mixed>  $params
     */
    public static function claims(array $params): bool
    {
        $account = $params['account']['order_id'] ?? null;
        if (is_string($account) && PosQrPayment::isAccount($account)) {
            return true;
        }
        $id = $params['id'] ?? null;

        return is_scalar($id) && (string) $id !== '' && self::byTransaction((string) $id) !== null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function handle(Request $request, string $method, array $params, mixed $id): JsonResponse
    {
        $qr = $this->target($params);
        if (!$this->authorized($request, $qr)) {
            return $this->error($id, -32504, 'Unauthorized merchant key');
        }

        try {
            return match ($method) {
                'CheckPerformTransaction' => $this->checkPerform($qr, $params, $id),
                'CreateTransaction' => $this->create($qr, $params, $id),
                'PerformTransaction' => $this->perform($qr, $id),
                'CancelTransaction' => $this->cancel($qr, $params, $id),
                'CheckTransaction' => $qr !== null && $qr->provider_transaction_id !== null ? $this->result($id, $this->state($qr)) : $this->error($id, -31003, 'Transaction not found'),
                'SetFiscalData' => $this->setFiscalData($qr, $id),
                default => $this->error($id, -32601, 'Method not found'),
            };
        } catch (\Throwable $e) {
            Log::error('Payme POS QR webhook exception: ' . $e->getMessage(), ['qr' => $qr?->uuid]);

            return $this->error($id, -31008, 'Internal server error');
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
    private function create(?PosQrPayment $qr, array $params, mixed $id): JsonResponse
    {
        $transaction = $this->transactionId($params);
        if ($transaction === '') {
            return $this->error($id, -32600, 'Transaction id is required');
        }
        if ($qr === null) {
            return $this->error($id, -31050, 'Order not found', 'order_id');
        }

        return DB::transaction(function () use ($qr, $params, $id, $transaction) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
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
                // Asked by Rizqim's refund (receipts.cancel): recorded without locking the row the refund will update
                $payload = $qr->provider_payload ?? [];
                $payload['payme'] = $payme + ['cancel_time' => now()->getTimestampMs(), 'cancel_reason' => $reason];
                PosQrPayment::whereKey($qr->id)->update(['provider_payload' => json_encode($payload)]);
                $qr->provider_payload = $payload;
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
        if (!is_numeric($amount) || !Money::fromTiyin($amount)->equals($qr->amount)) {
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
     * @param  array<string, mixed>  $params
     */
    private function target(array $params): ?PosQrPayment
    {
        $transaction = $this->transactionId($params);
        if ($transaction !== '' && ($qr = self::byTransaction($transaction)) !== null) {
            return $qr;
        }
        $account = $params['account']['order_id'] ?? null;

        return is_string($account) ? PosQrPayment::forAccount($account) : null;
    }

    private static function byTransaction(string $transaction): ?PosQrPayment
    {
        return PosQrPayment::where('provider', QrProvider::PAYME->value)->where('provider_transaction_id', $transaction)->first();
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

        return $expected !== null && hash_equals($expected, trim($parts[1]));
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
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error]);
    }
}
