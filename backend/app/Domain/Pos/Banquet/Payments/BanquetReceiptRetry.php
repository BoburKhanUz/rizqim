<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Models\BanquetPaymentEntry;
use App\Models\Store;
use Throwable;

/**
 * Review C5 (P6 wave 5), run hourly by pos:banquet-retry-receipts: the receipts of banquet money entries of the last
 * RETRY_DAYS days that could not be asked for (`error`) or that the provider refused (`failed`) are sent again under
 * the same receipt key (FiscalIssuer::reissue: the same receipt, never a second one) — the ADVANCE receipt of a
 * payment, the REFUND receipt of a refunded part (cancellation or a settlement's excess) and the SALE receipt of a
 * retained part. A `pending` one is the fiscal queue's (fiscal:retry-pending). A bank transfer has no receipt. A
 * refund whose advance receipt is still missing waits for it (oldest entries go first, so the advance goes before).
 */
final class BanquetReceiptRetry
{
    public const RETRY_DAYS = 3;

    /**
     * Returns how many receipts were sent again.
     */
    public function run(int $limit = 100): int
    {
        $entries = BanquetPaymentEntry::whereIn('fiscal_status', ['error', 'failed'])
            ->whereIn('type', [BanquetEntryType::PAYMENT->value, BanquetEntryType::REFUND->value, BanquetEntryType::RETAIN->value])
            ->where('tender', '!=', BanquetPaymentService::BANK_TRANSFER)
            ->where('created_at', '>=', now()->subDays(self::RETRY_DAYS))
            ->orderBy('id')->limit($limit)->get();
        $sent = 0;
        foreach ($entries as $entry) {
            try {
                if ($entry->type === BanquetEntryType::PAYMENT) {
                    app(BanquetPaymentService::class)->retryReceipt($entry);
                } else {
                    app(BanquetCancellationService::class)->receipt(Store::findOrFail($entry->store_id), $entry, true);
                }
                $sent++;
            } catch (Throwable $e) {
                // One entry never holds up the others (a fiscal setup problem stays visible as `error`)
                report($e);
            }
        }

        return $sent;
    }
}
