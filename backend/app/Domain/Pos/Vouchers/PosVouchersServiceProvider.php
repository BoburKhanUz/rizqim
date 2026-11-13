<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Contracts\VoucherLedger;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\Voucher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P6-43: gift certificates. VoucherLedger (what billing consumes for PosTender::VOUCHER) is VoucherLedgerService; a
 * certificate's advance receipt the provider confirms (or fails) later is recorded on it (fiscal.receipt_updated).
 */
class PosVouchersServiceProvider extends ServiceProvider
{
    public const FISCAL_SUBJECT = 'voucher';

    public function register(): void
    {
        $this->app->bind(VoucherLedger::class, VoucherLedgerService::class);
    }

    public function boot(): void
    {
        Event::listen(FiscalReceiptUpdatedBroadcast::class, function (FiscalReceiptUpdatedBroadcast $event) {
            if ($event->subjectType !== self::FISCAL_SUBJECT || $event->subjectId === null) {
                return;
            }
            try {
                Voucher::whereKey($event->subjectId)->update(['fiscal_receipt_uuid' => $event->uuid, 'fiscal_status' => $event->status]);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
