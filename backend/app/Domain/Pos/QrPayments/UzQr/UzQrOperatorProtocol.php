<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Models\PosQrPayment;
use App\Support\Money;

/**
 * Wave P: EVERY request and answer shape of the UzQR operator's API, in this one class, so the operator's spec (still
 * to come from the owner, research report "UzQR integratsiyasi" 7A) replaces it here alone; UzQrOperatorGateway only
 * moves these over HTTP. tests/Feature/PosQr/UzQrOperatorFixtures builds the answers in the same shape.
 *
 * What the sources show (read 22.11.2026):
 *  - SetRetail10's UzQR plugin (crystals.atlassian.net/wiki/spaces/SR10SUPPORT/pages/6492127238, the vendor's own
 *    docs): the till sends the operator requests to create a dynamic QR, ask the payment status (every 3–10 s), cancel
 *    an unpaid invoice, create a refund (the whole receipt or some lines) and ask its status, and after fiscalisation
 *    send the fiscal receipt's link. Settings: the connection address, the INN (9-digit STIR) and API_key; timeouts
 *    3 s connect, 20 s answer. Answer fields it names: `pay_id` (its RRN, ref.number), `invoice_id` (the operation's
 *    id at UzQR, order.id), `external_id` (the till's own id, operation.id), `card_type`, `card_no`. A test contour
 *    exists ("may answer QR not found").
 *  - The operator's Personal Account manual v1.1 (kdb.uz copy): one dynamic QR and one API key per till, the key is
 *    shown once; a refund amount "cannot exceed the amount of the original payment" (partial refunds) and takes "from
 *    several seconds to several hours"; payments are searched by payment number and invoice number; error codes are in
 *    an "UzQR Error Reference" we do not have.
 *
 * GUESSED (neither source shows them): the paths, the HTTP verbs (POST + JSON everywhere), how the key is sent
 * (AUTH_HEADER), that `inn` goes in the body, the amount unit (tiyin: amount()), the QR lifetime field, the
 * envelope (`success` / `data` / `error{code,message}`), the status words, a separate `rrn` field, the refund fields
 * and the receipt link's field name. Each is marked "guess" below.
 */
final class UzQrOperatorProtocol
{
    // Guess: paths under the connection address the operator gives (SetRetail10: "Адрес подключения")
    public const PATH_CREATE = '/invoice/create';

    public const PATH_STATUS = '/invoice/status';

    public const PATH_CANCEL = '/invoice/cancel';

    public const PATH_REFUND = '/refund/create';

    public const PATH_REFUND_STATUS = '/refund/status';

    public const PATH_RECEIPT = '/invoice/fiscal-receipt';

    // Guess: the till's API_key as a header of its own
    public const AUTH_HEADER = 'X-Api-Key';

    // Guess: the operator's status words. Anything else is `pending` (never taken as paid)
    private const PAID = ['paid', 'success', 'succeeded', 'completed', 'confirmed', 'done'];

    private const CANCELLED = ['cancelled', 'canceled', 'expired', 'rejected', 'declined', 'failed', 'deleted'];

    private const REFUND_DONE = ['success', 'succeeded', 'completed', 'done', 'refunded'];

    private const REFUND_FAILED = ['failed', 'rejected', 'declined', 'cancelled', 'canceled'];

    // Guess: an explicit "no such refund" (the reversal never reached the operator: it may be sent again)
    private const REFUND_NOT_FOUND = ['not_found'];

    /**
     * @return array<string, string>
     */
    public static function headers(string $apiKey): array
    {
        return [self::AUTH_HEADER => $apiKey];
    }

    /**
     * Guess: amounts as integer tiyin (as Payme's API and the minor units of an EMV amount). If the operator wants
     * so'm, this and paidAmount() are the two places to change.
     */
    public static function amount(Money $amount): int
    {
        return $amount->toTiyin();
    }

    /**
     * Create a dynamic QR: our request's uuid as `external_id` (SetRetail10: operation.id ← external_id, the till's id),
     * the exact amount, how long it may be paid (guess: `lifetime` in seconds, from our expires_at).
     *
     * @return array<string, mixed>
     */
    public static function create(Money $amount, PosQrPayment $qr, string $tin): array
    {
        return [
            'inn' => $tin,
            'external_id' => (string) $qr->uuid,
            'amount' => self::amount($amount),
            'lifetime' => max(60, (int) ceil(now()->diffInSeconds($qr->expires_at, true))),
        ];
    }

    /**
     * The created QR: `invoice_id` (SetRetail10) and the QR string the till shows (guess: `qr`, also read as
     * `qr_code` / `payload`); null when the answer has neither.
     *
     * @param  array<string, mixed>  $answer
     */
    public static function created(array $answer): ?UzQrDynamicQr
    {
        $data = self::data($answer);
        $id = self::text($data, ['invoice_id']);
        $payload = self::text($data, ['qr', 'qr_code', 'payload']);

        return $id !== null && $payload !== null ? new UzQrDynamicQr($id, $payload) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(PosQrPayment $qr, string $tin): array
    {
        return ['inn' => $tin, 'invoice_id' => (string) $qr->gateway_reference];
    }

    /**
     * The payment's state as our statuses: `paid` (with pay_id, the RRN, the card), `cancelled`, `pending`, or
     * `mismatch` when the operator reports a payment of another amount than ours.
     *
     * Guess: a separate `rrn`; without one the RRN is `pay_id` (SetRetail10 fills ref.number, "RRN", from pay_id).
     * `card_no` is kept as its last four digits only (it may be a masked PAN; we never keep more).
     *
     * @param  array<string, mixed>  $answer
     * @return array{status: string, transaction_id: string|null, details: array<string, string>}
     */
    public static function paymentState(array $answer, PosQrPayment $qr): array
    {
        $data = self::data($answer);
        $word = strtolower((string) (self::text($data, ['status', 'state']) ?? ''));
        $payId = self::text($data, ['pay_id']);
        $cardNo = self::text($data, ['card_no']);
        $last4 = $cardNo !== null && preg_match('/(\d{4})\D*$/', $cardNo, $match) === 1 ? $match[1] : null;
        $details = array_filter([
            'pay_id' => $payId,
            'rrn' => self::text($data, ['rrn']) ?? $payId,
            'invoice_id' => self::text($data, ['invoice_id']) ?? $qr->gateway_reference,
            'card_type' => ($type = self::text($data, ['card_type'])) !== null ? mb_substr($type, 0, 32) : null,
            'card_last4' => $last4,
        ], fn ($value) => $value !== null);

        if (in_array($word, self::PAID, true)) {
            $paid = self::paidAmount($data);
            $status = $paid === null || $paid->equals($qr->amount) ? 'paid' : 'mismatch';
            if ($paid !== null) {
                $details['amount'] = $paid->toString();
            }

            return ['status' => $status, 'transaction_id' => $payId ?? $qr->gateway_reference, 'details' => $details];
        }

        return ['status' => in_array($word, self::CANCELLED, true) ? 'cancelled' : 'pending', 'transaction_id' => null, 'details' => $details];
    }

    /**
     * @return array<string, mixed>
     */
    public static function cancel(PosQrPayment $qr, string $tin): array
    {
        return ['inn' => $tin, 'invoice_id' => (string) $qr->gateway_reference];
    }

    /**
     * Guess: a refund names the payment (`invoice_id`, `pay_id`), our attempt's uuid as its `external_id` (a retry of
     * the same attempt sends the same id, so an operator that de-duplicates never refunds twice) and the amount.
     *
     * @return array<string, mixed>
     */
    public static function refund(PosQrPayment $qr, Money $amount, string $refundUuid, string $tin): array
    {
        return array_filter([
            'inn' => $tin,
            'invoice_id' => (string) $qr->gateway_reference,
            'pay_id' => self::payId($qr),
            'external_id' => $refundUuid,
            'amount' => self::amount($amount),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function refundStatus(PosQrPayment $qr, string $refundUuid, string $tin): array
    {
        return ['inn' => $tin, 'invoice_id' => (string) $qr->gateway_reference, 'external_id' => $refundUuid];
    }

    /**
     * The refund's state: `done` (with the operator's refund id), `failed` (it says no, or explicitly has no such
     * refund) or `pending` (it is still working: "from several seconds to several hours"; any other word too).
     *
     * @param  array<string, mixed>  $answer
     * @return array{state: 'done'|'failed'|'pending', reference: string|null, message: string|null}
     */
    public static function refundState(array $answer): array
    {
        $data = self::data($answer);
        $word = strtolower((string) (self::text($data, ['status', 'state']) ?? ''));
        $state = match (true) {
            in_array($word, self::REFUND_DONE, true) => 'done',
            in_array($word, self::REFUND_FAILED, true), in_array($word, self::REFUND_NOT_FOUND, true) => 'failed',
            default => 'pending',
        };

        return ['state' => $state, 'reference' => self::text($data, ['refund_id', 'id']), 'message' => self::text($data, ['message', 'reason'])];
    }

    /**
     * Guess: the fiscal receipt's link for the payment (field `fiscal_url`).
     *
     * @return array<string, mixed>
     */
    public static function receipt(PosQrPayment $qr, string $url, string $tin): array
    {
        return array_filter([
            'inn' => $tin,
            'invoice_id' => (string) $qr->gateway_reference,
            'pay_id' => self::payId($qr),
            'fiscal_url' => $url,
        ], fn ($value) => $value !== null);
    }

    /**
     * Whether a 2xx answer is the operator saying no (guess: `success: false` or an `error`), and its code and message.
     *
     * @param  array<string, mixed>  $answer
     * @return array{code: string, message: string}|null
     */
    public static function error(array $answer): ?array
    {
        $error = $answer['error'] ?? null;
        if (($answer['success'] ?? true) !== false && ($error === null || $error === false || $error === [] || $error === 0)) {
            return null;
        }
        $error = is_array($error) ? $error : ['message' => is_scalar($error) ? (string) $error : null];

        return [
            'code' => mb_substr((string) ($error['code'] ?? $answer['code'] ?? 'error'), 0, 64),
            'message' => mb_substr((string) ($error['message'] ?? $answer['message'] ?? ''), 0, 200),
        ];
    }

    /**
     * The payment's pay_id as the confirmation recorded it.
     */
    public static function payId(PosQrPayment $qr): ?string
    {
        $confirm = (array) (($qr->provider_payload ?? [])['confirm'] ?? []);

        return isset($confirm['pay_id']) && is_string($confirm['pay_id']) ? $confirm['pay_id'] : null;
    }

    /**
     * Guess: the answer's body inside `data` (or `result`), else the answer itself.
     *
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private static function data(array $answer): array
    {
        foreach (['data', 'result'] as $key) {
            if (is_array($answer[$key] ?? null)) {
                return $answer[$key];
            }
        }

        return $answer;
    }

    private static function paidAmount(array $data): ?Money
    {
        $value = $data['amount'] ?? null;
        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            return null;
        }

        return Money::fromTiyin($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private static function text(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if ((is_string($value) || is_int($value)) && (string) $value !== '') {
                return mb_substr((string) $value, 0, 2000);
            }
        }

        return null;
    }
}
