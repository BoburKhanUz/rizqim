<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Where an online payment's fiscal receipt stands (P3-14), kept on the payment as `payload.fiscal`:
 * {provider, status, problems, ...provider data}. Status: `blocked` (fiscal mode is on but the receipt cannot be sent:
 * missing MXIK, package code, store TIN, credentials — the owner must fix it), `pending` (sent, waiting for the
 * provider), `failed` (the provider or the OFD refused it, or retries ran out), `recorded` (in fiscal_receipts).
 * GET /owner/payments shows status and problems; the raw payload never leaves the server.
 */
class OnlineFiscalRecorder
{
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RECORDED = 'recorded';

    public const STATUSES = [self::STATUS_BLOCKED, self::STATUS_PENDING, self::STATUS_FAILED, self::STATUS_RECORDED];

    public function __construct(protected OnlineFiscalReceiptBuilder $receipts) {}

    /**
     * Merges $fiscal into the payment's payload.fiscal.
     *
     * @param  array<string, mixed>  $fiscal
     */
    public function mark(Payment $payment, array $fiscal): void
    {
        $payload = $payment->payload ?? [];
        $payload['fiscal'] = array_merge(is_array($payload['fiscal'] ?? null) ? $payload['fiscal'] : [], $fiscal);

        $payment->forceFill(['payload' => $payload])->save();
    }

    /**
     * Logs a receipt that cannot be sent; with a payment, flags it for the owner too.
     */
    public function blocked(Order $order, string $provider, OnlineFiscalReceipt $receipt, ?Payment $payment = null): void
    {
        Log::warning('Online fiscal receipt not sent', [
            'provider' => $provider,
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'payment_id' => $payment?->id,
            'problems' => $receipt->problems,
            'details' => $receipt->details,
        ]);

        if ($payment) {
            $this->mark($payment, [
                'provider' => $provider,
                'status' => self::STATUS_BLOCKED,
                'problems' => $receipt->problems,
            ]);
        }
    }

    /**
     * Records a receipt the provider registered (FiscalIssuer::recordExternal, P3-11), idempotent on $key. Null when
     * the fiscal core is not installed.
     *
     * @param  array<string, mixed>  $providerData
     */
    public function record(Order $order, Payment $payment, string $provider, FiscalReceiptType $type, string $key, array $providerData, ?string $originalReceiptUuid = null): ?FiscalIssueResult
    {
        if (!app()->bound(FiscalIssuer::class)) {
            Log::warning('Online fiscal receipt not recorded: no FiscalIssuer is bound', ['provider' => $provider, 'payment_id' => $payment->id]);

            return null;
        }

        $amount = Money::of($payment->amount);
        $document = new FiscalDocument(
            type: $type,
            storeId: (int) $order->store_id,
            branchId: $order->branch_id !== null ? (int) $order->branch_id : null,
            deviceId: null,
            lines: $this->receipts->recordedLines($order),
            // Paid online by card through the provider; the provider's transaction id goes on the receipt
            payments: [new FiscalPayment(FiscalPaymentForm::CARD, $amount, transactionId: $payment->provider_transaction_id)],
            total: $amount,
            idempotencyKey: $key,
            originalReceiptUuid: $originalReceiptUuid,
            subjectType: 'order',
            subjectId: (int) $order->id,
        );

        return app(FiscalIssuer::class)->recordExternal($document, $provider, $providerData);
    }
}
