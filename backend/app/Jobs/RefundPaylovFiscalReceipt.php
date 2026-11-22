<?php

namespace App\Jobs;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use App\Services\Payment\Paylov\PaylovAccount;
use App\Services\Payment\Paylov\PaylovApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;

/**
 * Wave P: the refund receipt of a Paylov payment refunded in full. developer.paylov.uz/subscribe/ofd/refund-receipt,
 * "Notes": cancelling a card payment (payment/cancel) cancels its fiscalisation too; "If fiscalization for a refund is
 * not available via the /fiscalization/status/ API, send a request to this /fiscalization/refund/ endpoint". So the
 * refund receipt is read with fiscalization/status?refunded=true first, and only asked for with fiscalization/refund
 * {receiptId} when Paylov has none; it is recorded against the sale receipt (payload.fiscal.refund_receipt_uuid).
 */
class RefundPaylovFiscalReceipt implements ShouldQueueAfterCommit
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

    public function handle(OnlineFiscalRecorder $recorder, PaylovApi $api): void
    {
        $payment = Payment::with('order')->find($this->paymentId);
        $order = $payment?->order;
        $fiscal = is_array($payment?->payload['fiscal'] ?? null) ? $payment->payload['fiscal'] : [];
        if (!$payment || !$order || $payment->status !== PaymentStatus::REFUNDED
            || ($fiscal['status'] ?? null) !== OnlineFiscalRecorder::STATUS_RECORDED || !empty($fiscal['refund_receipt_id'])) {
            return;
        }

        $account = PaylovAccount::forOrder($order);
        $transactionId = (string) $payment->provider_transaction_id;

        $status = $api->receiptStatus($account, $transactionId, true);
        $refund = $status->ok && ($status->result['success'] ?? false) && ($status->result['isRefund'] ?? false) ? $status->result : null;
        $result = $status;

        if ($refund === null && is_numeric($fiscal['receipt_id'] ?? null)) {
            $result = $api->refundReceipt($account, (int) $fiscal['receipt_id']);
            $refund = $result->ok && is_array($result->result['ofd']['refund'] ?? null) ? $result->result['ofd']['refund'] : null;
        }

        if ($refund === null || !isset($refund['receiptId'])) {
            $problem = 'paylov_refund_' . ($result->errorCode ?? 'receipt_missing');
            if ($result->transient && $this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 14400);

                return;
            }
            Log::warning('Paylov refund receipt failed', ['payment_id' => $payment->id, 'order_id' => $payment->order_id, 'problem' => $problem]);
            $recorder->mark($payment, ['refund_status' => OnlineFiscalRecorder::STATUS_FAILED, 'refund_problems' => [$problem]]);

            return;
        }

        $data = RegisterPaylovFiscalReceipt::providerData($transactionId, $refund);
        $recorded = $recorder->record($order, $payment, 'paylov', FiscalReceiptType::REFUND, "paylov:{$transactionId}:refund", $data, $fiscal['receipt_uuid'] ?? null);

        $recorder->mark($payment, [
            'refund_status' => OnlineFiscalRecorder::STATUS_RECORDED,
            'refund_problems' => [],
            'refund_receipt_id' => $data['receipt_id'],
            'refund_qr_code_url' => $data['qr_code_url'],
            'refund_receipt_uuid' => $recorded?->receiptUuid,
        ]);
    }
}
