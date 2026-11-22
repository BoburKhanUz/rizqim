<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ConfirmPaylovPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Paylov\PaylovAccount;
use App\Services\Payment\Paylov\PaylovPayments;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Group('Webhooks')]
class PaylovWebhookController extends Controller
{
    /**
     * Status codes of developer.paylov.uz/merchants/statuses: "0 – Success, 3 – Maintenance is in process, 5 – Invalid
     * amount, 303 – Order not found, +1 – Custom error message" (statusText carries our snake_case reason). The page
     * gives the codes as strings in its examples ("status": "0", "+1"), so they are sent as strings.
     */
    private const OK = ['0', 'OK'];
    private const MAINTENANCE = ['3', 'Maintenance is in process'];
    private const INVALID_AMOUNT = ['5', 'Invalid amount'];
    private const ORDER_NOT_FOUND = ['303', 'Order not found'];
    private const CUSTOM = '+1';

    /** The JSON-RPC answer (merchants/transaction-check, "Response example") */
    private const ANSWER = 'array{jsonrpc: string, id: int|string|null, result: array{status: \'0\'|\'3\'|\'5\'|\'303\'|\'+1\', statusText: string}}';

    /** merchants/creating-payment-link: currency_id "860 - UZS"; the only currency RIZQIM charges in */
    private const UZS = 860;

    public function __construct(private readonly PaylovPayments $payments) {}

    /**
     * Paylov: Checkout callbacks and the cancel webhook (JSON-RPC 2.0)
     *
     * `transaction.check` and `transaction.perform` (developer.paylov.uz/merchants/paylov-payment-gateway) and the
     * `transaction.cancel` notification (subscribe/payment/cancel-webhook), with HTTP Basic Auth of the callback login and
     * password of the account the order is paid into (the platform's, or the store's own). Answers
     * `{jsonrpc, id, result: {status, statusText}}`; a request without the right credentials is HTTP 401. A perform is
     * settled only after Paylov's getTransactions says `payed` (until then the payment is `unconfirmed`); a cancel is any
     * 2xx, and a failure to record it a 500 so that Paylov retries it.
     */
    #[ApiDocResponse(200, type: self::ANSWER)]
    #[ApiDocResponse(401, description: "Callback login yoki paroli noto'g'ri yoki sozlanmagan", type: self::ANSWER)]
    #[ApiDocResponse(500, description: "transaction.cancel yozilmadi: Paylov qayta yuboradi", type: self::ANSWER)]
    public function handle(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        $payload = json_decode($raw, true);
        if (trim($raw) !== '' && !is_array($payload)) {
            return $this->answer(null, self::CUSTOM, 'parse_error');
        }
        $payload = is_array($payload) ? $payload : [];

        $method = is_string($payload['method'] ?? null) ? $payload['method'] : '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $id = $payload['id'] ?? null;

        // Ids only: the callbacks carry no card data, and the Authorization header is never logged
        Log::info('Paylov Webhook Received', [
            'method' => $method,
            'id' => is_scalar($id) ? $id : null,
            'transaction_id' => is_scalar($params['transaction_id'] ?? null) ? $params['transaction_id'] : null,
            'order_id' => is_scalar($params['account']['order_id'] ?? null) ? $params['account']['order_id'] : null,
        ]);

        try {
            $payment = $this->findPayment($params);
            $order = $payment ? $payment->order : $this->findOrder($params);

            if (!$this->authorized($request, $order)) {
                return $this->answer($id, self::CUSTOM, 'unauthorized', 401);
            }

            return match ($method) {
                'transaction.check' => $this->check($id, $params, $order),
                'transaction.perform' => $this->perform($id, $params, $order, $payment),
                'transaction.cancel' => $this->cancel($id, $params, $payment),
                default => $this->answer($id, self::CUSTOM, 'method_not_found'),
            };
        } catch (\Throwable $e) {
            // The transaction rolled back: nothing was recorded. A check or perform is answered "maintenance" (Paylov
            // does not take the money for it); a cancel gets a 500, which Paylov retries (up to 3 times, 60 s apart)
            Log::error('Paylov Webhook Exception: ' . $e->getMessage(), ['method' => $method]);

            return $method === 'transaction.cancel'
                ? $this->answer($id, ...self::MAINTENANCE, http: 500)
                : $this->answer($id, ...self::MAINTENANCE);
        }
    }

    /**
     * transaction.check (merchants/transaction-check): Paylov asks whether the order can be paid this amount.
     */
    private function check(mixed $id, array $params, ?Order $order): JsonResponse
    {
        if ($refusal = $this->orderRefusal($params, $order)) {
            return $this->answer($id, ...$refusal);
        }

        if ($order->payment_status === PaymentStatus::PAID) {
            return $this->answer($id, self::CUSTOM, 'order_already_paid');
        }
        if (!PaylovPayments::isPayable($order)) {
            return $this->answer($id, self::CUSTOM, 'order_not_payable');
        }

        return $this->answer($id, ...self::OK);
    }

    /**
     * transaction.perform (merchants/transaction-perform): "called by Paylov after the user confirms the payment".
     * Recorded `pending` / `unconfirmed` under the order lock and confirmed with getTransactions after the answer
     * (ConfirmPaylovPayment). The page does not say whether a non-zero answer stops Paylov taking the money, so a perform
     * we refuse (the order is paid by another transaction, or cancelled meanwhile) is still recorded: if Paylov reports it
     * `payed`, it is given back in full (PaylovPayments). A repeated perform of the same transaction gets the same answer.
     */
    private function perform(mixed $id, array $params, ?Order $order, ?Payment $existing): JsonResponse
    {
        $transactionId = $this->transactionId($params);
        if ($transactionId === null) {
            return $this->answer($id, self::CUSTOM, 'transaction_id_required');
        }
        if (!Str::isUuid($transactionId)) {
            // perform's transaction_id is "UUID4": getTransactions refuses anything else
            return $this->answer($id, self::CUSTOM, 'invalid_transaction_id');
        }

        $accountOrder = $this->findOrder($params);
        if ($existing && ($accountOrder === null || (int) $existing->order_id !== (int) $accountOrder->id)) {
            return $this->answer($id, self::CUSTOM, 'transaction_belongs_to_another_order');
        }

        if ($refusal = $this->orderRefusal($params, $accountOrder)) {
            return $this->answer($id, ...$refusal);
        }

        [$answer, $confirm, $replayPaid] = DB::transaction(function () use ($id, $params, $accountOrder, $transactionId) {
            // Every write about this order's money is serialised on the order row (the other webhooks, the unpaid timeout)
            $order = Order::whereKey($accountOrder->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('provider', 'paylov')->where('provider_transaction_id', $transactionId)->lockForUpdate()->first();

            if ($payment) {
                if ((int) $payment->order_id !== (int) $order->id) {
                    return [$this->answer($id, self::CUSTOM, 'transaction_belongs_to_another_order'), null, null];
                }

                return $this->replay($id, $payment);
            }

            $refused = match (true) {
                $order->payment_status === PaymentStatus::PAID => 'order_already_paid',
                !PaylovPayments::isPayable($order) => 'order_not_payable',
                default => null,
            };

            $payment = Payment::create([
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'provider' => 'paylov',
                'provider_transaction_id' => $transactionId,
                'amount' => Money::fromTiyin((int) PaylovPayments::wholeTiyin($params['amount_tiyin'] ?? null))->toString(),
                'status' => PaymentStatus::PENDING,
                'payload' => [
                    'paylov' => array_filter([
                        'state' => PaylovPayments::STATE_UNCONFIRMED,
                        'performed_at' => now()->toIso8601String(),
                        'refused' => $refused,
                    ], fn ($value) => $value !== null),
                    'perform_params' => $params,
                ],
            ]);

            return [$refused ? $this->answer($id, self::CUSTOM, $refused) : $this->answer($id, ...self::OK), $payment, null];
        });

        if ($confirm !== null) {
            ConfirmPaylovPayment::dispatch((int) $confirm->id);
        }
        if ($replayPaid !== null) {
            // Go-live review M1 pattern: Paylov's replay of a performed transaction fires PaymentPaid again (the listener
            // books everything once), which is how a failed settlement or auto-accept catches up
            $this->payments->firePaid($replayPaid, true);
        }

        return $answer;
    }

    /**
     * The answer to a perform Paylov sends again: what the first one got, and a confirmation still pending asked again.
     *
     * @return array{0: JsonResponse, 1: Payment|null, 2: Payment|null}
     */
    private function replay(mixed $id, Payment $payment): array
    {
        $state = PaylovPayments::state($payment);
        if (($state['refused'] ?? null) !== null) {
            return [$this->answer($id, self::CUSTOM, (string) $state['refused']), null, null];
        }

        return match (true) {
            $payment->status === PaymentStatus::PAID => [$this->answer($id, ...self::OK), null, $payment],
            PaylovPayments::isUnconfirmed($payment) => [$this->answer($id, ...self::OK), $payment, null],
            ($state['state'] ?? null) === PaylovPayments::STATE_MISMATCH => [$this->answer($id, ...self::OK), null, null],
            default => [$this->answer($id, self::CUSTOM, 'transaction_cancelled'), null, null],
        };
    }

    /**
     * transaction.cancel (subscribe/payment/cancel-webhook): "Return any HTTP 2xx status code. The response body is not
     * checked." Sent when the transaction is cancelled from Paylov's business panel or by its staff (and, it is our
     * reading, after our own payment/cancel), retried up to 3 times: recorded once by transaction_id.
     */
    private function cancel(mixed $id, array $params, ?Payment $payment): JsonResponse
    {
        if (!$payment) {
            Log::warning('Paylov cancel webhook for a transaction RIZQIM never recorded: nothing to do', [
                'transaction_id' => $this->transactionId($params),
            ]);

            return $this->answer($id, ...self::OK);
        }

        $tiyin = PaylovPayments::wholeTiyin($params['amount_in_tiyin'] ?? null);
        if ($tiyin !== null && $tiyin !== Money::of($payment->amount)->toTiyin()) {
            // A cancel is always the whole transaction (cancel-payment has no amount): recorded, but worth a look
            Log::warning('Paylov cancel webhook names another amount than the payment', ['payment_id' => $payment->id, 'amount_in_tiyin' => $tiyin]);
        }

        $cancelTime = is_numeric($params['cancel_time'] ?? null) ? (int) $params['cancel_time'] : null;
        $this->payments->applyCancel($payment, 'webhook', $cancelTime);

        return $this->answer($id, ...self::OK);
    }

    /**
     * Why the order cannot be paid this amount, as a [status, statusText] pair, or null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function orderRefusal(array $params, ?Order $order): ?array
    {
        if (!$order || $order->payment_method !== 'paylov') {
            return self::ORDER_NOT_FOUND;
        }

        $currency = $params['currency'] ?? self::UZS;
        if ((string) $currency !== (string) self::UZS) {
            return [self::CUSTOM, 'unsupported_currency'];
        }

        // amount_tiyin only, as an exact whole number: `amount` is "in full currency" and would drop the tiyin
        $tiyin = PaylovPayments::wholeTiyin($params['amount_tiyin'] ?? null);
        if ($tiyin === null || $tiyin <= 0 || $tiyin !== Money::of($order->total_price)->toTiyin()) {
            return self::INVALID_AMOUNT;
        }

        return null;
    }

    /**
     * Basic Auth of the callback login and password of the account the order is paid into (OrderMerchantAccount; the
     * platform's when no order is found), both compared in constant time. Unset credentials fail closed.
     */
    private function authorized(Request $request, ?Order $order): bool
    {
        $account = PaylovAccount::forOrder($order);
        $login = $account->get('callback_login');
        $password = $account->get('callback_password');
        if ($login === null || $password === null) {
            Log::error("Paylov webhook rejected: no callback login/password is configured for this payment (PAYLOV_CALLBACK_LOGIN / PAYLOV_CALLBACK_PASSWORD or the store's own).", [
                'order_id' => $order?->id,
                'store_account' => $account->isStore,
            ]);

            return false;
        }

        $header = (string) $request->header('Authorization');
        if (!str_starts_with($header, 'Basic ')) {
            return false;
        }
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return false;
        }
        [$presentedLogin, $presentedPassword] = explode(':', $decoded, 2);

        // Both compared, whatever the first gives (no early return on the login)
        $loginMatches = hash_equals($login, $presentedLogin);
        $passwordMatches = hash_equals($password, $presentedPassword);

        return $loginMatches && $passwordMatches;
    }

    private function findPayment(array $params): ?Payment
    {
        $transactionId = $this->transactionId($params);

        return $transactionId === null ? null : Payment::with('order')->where('provider', 'paylov')->where('provider_transaction_id', $transactionId)->first();
    }

    /**
     * The order of `account.order_id`: the uuid the checkout link carries (PaymentProviderSwitch::paylovUrl).
     */
    private function findOrder(array $params): ?Order
    {
        $reference = $params['account']['order_id'] ?? null;
        if (!is_scalar($reference) || !Str::isUuid((string) $reference)) {
            return null;
        }

        return Order::where('uuid', (string) $reference)->first();
    }

    private function transactionId(array $params): ?string
    {
        $value = $params['transaction_id'] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? strtolower(trim((string) $value)) : null;
    }

    private function answer(mixed $id, string $status, string $text, int $http = 200): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => is_scalar($id) ? $id : null,
            'result' => ['status' => $status, 'statusText' => $text],
        ], $http);
    }
}
