<?php

namespace App\Jobs;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use App\Services\Payment\Fiscal\PaylovFiscalItems;
use App\Services\Payment\Paylov\PaylovAccount;
use App\Services\Payment\Paylov\PaylovApi;
use App\Services\Payment\Paylov\PaylovPayments;
use App\Services\Payment\Paylov\PaylovResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave P: after a Paylov payment is confirmed (never on the perform's word alone), registers its fiscal receipt with the
 * order's lines and the restaurant as komitent: POST fiscalization/register {transactionId, items}
 * (developer.paylov.uz/subscribe/ofd/register), then records it in fiscal_receipts as the Payme/Click receipts are
 * (OnlineFiscalRecorder, payload.fiscal). A transaction that already has a receipt (`ofd_check_already_generated`) is
 * read back with fiscalization/status (subscribe/ofd/get-receipt). Passing failures are retried with backoff.
 */
class RegisterPaylovFiscalReceipt implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;

    public int $timeout = 60;

    public function __construct(public int $paymentId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600, 7200, 14400];
    }

    public function handle(OnlineFiscalReceiptBuilder $receipts, OnlineFiscalRecorder $recorder, PaylovApi $api): void
    {
        $payment = Payment::with('order.store')->find($this->paymentId);
        $order = $payment?->order;
        if (!$payment || !$order || $payment->provider !== 'paylov' || $payment->status !== PaymentStatus::PAID
            || (PaylovPayments::state($payment)['refused'] ?? null) !== null) {
            return;
        }

        $fiscal = is_array($payment->payload['fiscal'] ?? null) ? $payment->payload['fiscal'] : [];
        if (($fiscal['status'] ?? null) === OnlineFiscalRecorder::STATUS_RECORDED) {
            return;
        }

        // Paylov has no `shipping`: the delivery fee is a line with its own MXIK (FiscalLineBuilder charge `delivery`)
        $receipt = $receipts->build($order, true);
        if (!$receipt->enabled) {
            return;
        }

        $account = PaylovAccount::forOrder($order);
        $items = PaylovFiscalItems::from($receipt);
        $receipt = $receipt->withProblems([...$items['problems'], ...($account->hasApiCredentials() ? [] : ['paylov_credentials_missing'])]);
        if (!$receipt->isIssuable()) {
            $recorder->blocked($order, 'paylov', $receipt, $payment);

            return;
        }

        $transactionId = (string) $payment->provider_transaction_id;
        $result = $api->registerReceipt($account, $transactionId, $items['items']);
        $ofd = $result->ok && is_array($result->result['ofd'] ?? null) ? $result->result['ofd'] : null;

        if ($ofd === null && $result->errorCode === 'ofd_check_already_generated') {
            // Registered before (our earlier attempt whose answer was lost): read it back
            $status = $api->receiptStatus($account, $transactionId, false);
            $ofd = $status->ok && ($status->result['success'] ?? false) ? $status->result : null;
            $result = $ofd === null ? $status : $result;
        }

        if ($ofd === null || !isset($ofd['receiptId'])) {
            $this->retryOrGiveUp($payment, $recorder, $result);

            return;
        }

        $data = self::providerData($transactionId, $ofd);
        $recorded = $recorder->record($order, $payment, 'paylov', FiscalReceiptType::SALE, "paylov:{$transactionId}:sale", $data);

        $recorder->mark($payment, [
            'provider' => 'paylov',
            'status' => OnlineFiscalRecorder::STATUS_RECORDED,
            'problems' => [],
            'receipt_id' => $data['receipt_id'],
            'qr_code_url' => $data['qr_code_url'],
            'receipt_uuid' => $recorded?->receiptUuid,
        ]);

        // Review 1: refunded while Paylov was registering the sale (the cancel found no receipt to refund yet). mark()
        // read the row under its lock, so either the refund came first and is seen here, or it comes after and
        // PaylovPayments::applyCancel sees this receipt: the refund receipt is asked for exactly once
        if ($payment->status === PaymentStatus::REFUNDED) {
            RefundPaylovFiscalReceipt::dispatch((int) $payment->id);
        }
    }

    /**
     * What fiscal_receipts keeps of Paylov's {receiptUrl, terminalId, receiptId, fiscalSign}, in the spelling
     * FiscalService::externalFields reads.
     *
     * @param  array<string, mixed>  $ofd
     * @return array{transaction_id: string, receipt_id: int|null, qr_code_url: string|null, terminal_id: string|null, fiscal_sign: string|null}
     */
    public static function providerData(string $transactionId, array $ofd): array
    {
        $text = fn (string $key) => is_scalar($ofd[$key] ?? null) && (string) $ofd[$key] !== '' ? (string) $ofd[$key] : null;

        return [
            'transaction_id' => $transactionId,
            'receipt_id' => is_numeric($ofd['receiptId'] ?? null) ? (int) $ofd['receiptId'] : null,
            'qr_code_url' => $text('receiptUrl'),
            'terminal_id' => $text('terminalId'),
            'fiscal_sign' => $text('fiscalSign'),
        ];
    }

    public function failed(?Throwable $e): void
    {
        $payment = Payment::find($this->paymentId);
        if ($payment) {
            $this->giveUp($payment, app(OnlineFiscalRecorder::class), 'paylov_register_failed', (string) $e?->getMessage());
        }
    }

    private function retryOrGiveUp(Payment $payment, OnlineFiscalRecorder $recorder, PaylovResult $result): void
    {
        $problem = 'paylov_' . ($result->errorCode ?? 'register_failed');

        if ($result->transient && $this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $recorder->mark($payment, ['provider' => 'paylov', 'status' => OnlineFiscalRecorder::STATUS_PENDING, 'problems' => [$problem]]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 14400);

            return;
        }

        $this->giveUp($payment, $recorder, $problem, "HTTP {$result->httpStatus}");
    }

    private function giveUp(Payment $payment, OnlineFiscalRecorder $recorder, string $problem, string $error): void
    {
        Log::warning('Paylov fiscal receipt failed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'attempts' => $this->attempts(),
            'problem' => $problem,
            'error' => mb_substr($error, 0, 300),
        ]);

        $recorder->mark($payment, ['provider' => 'paylov', 'status' => OnlineFiscalRecorder::STATUS_FAILED, 'problems' => [$problem]]);
    }
}
