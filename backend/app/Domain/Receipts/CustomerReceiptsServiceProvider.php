<?php

namespace App\Domain\Receipts;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Jobs\SendCustomerReceiptNotice;
use App\Models\FiscalReceipt;
use App\Models\OrderBill;
use App\Models\PosRefund;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Wave P (receipts): a sale or refund receipt of an order that becomes registered (written `issued` by FiscalService:
 * recorded from Payme / Click / Paylov, or a till's receipt confirmed now or after a retry) queues
 * SendCustomerReceiptNotice after commit (a failed push is reported, never thrown to the caller): the customer's
 * Telegram message with the link, and orders.fiscalized_by. The job decides everything else (whose order, which
 * link, whether it was sent already); this hook only filters.
 */
class CustomerReceiptsServiceProvider extends ServiceProvider
{
    private const SUBJECTS = ['order', OrderBill::FISCAL_SUBJECT, PosRefund::FISCAL_SUBJECT];

    public function boot(): void
    {
        FiscalReceipt::saved(function (FiscalReceipt $receipt) {
            if ($receipt->status !== FiscalReceiptStatus::ISSUED || $receipt->non_fiscal
                || !in_array($receipt->type, [FiscalReceiptType::SALE, FiscalReceiptType::REFUND], true)
                || !in_array($receipt->subject_type, self::SUBJECTS, true)
                || !($receipt->wasRecentlyCreated || $receipt->wasChanged('status'))) {
                return;
            }

            // This hook owns the after-commit step: inside a transaction a ShouldQueueAfterCommit job is pushed by the
            // outermost commit, outside any catch here, so a failed push (Redis down) would escape DB::transaction()
            // after the receipt was committed and fail the caller (Payme SetFiscalData answering a system error).
            // Pushed from our own after-commit callback instead (at once when no transaction is open; dropped on a
            // rollback), so the failure is caught. Nothing was claimed then (the job sets customer_notified_at
            // itself), so the receipt is not marked notified; the notice is lost, the Mini App's order history
            // still shows the receipt.
            $receiptId = (int) $receipt->id;
            $receipt->getConnection()->afterCommit(function () use ($receiptId) {
                try {
                    SendCustomerReceiptNotice::dispatch($receiptId)->beforeCommit();
                } catch (Throwable $e) {
                    // The receipt is what matters; the customer still finds it in the Mini App's order history
                    report($e);
                }
            });
        });
    }
}
