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
 * SendCustomerReceiptNotice after commit: the customer's Telegram message with the link, and orders.fiscalized_by.
 * The job decides everything else (whose order, which link, whether it was sent already); this hook only filters.
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

            try {
                SendCustomerReceiptNotice::dispatch((int) $receipt->id);
            } catch (Throwable $e) {
                // The receipt is what matters; the customer still finds it in the Mini App's order history
                report($e);
            }
        });
    }
}
