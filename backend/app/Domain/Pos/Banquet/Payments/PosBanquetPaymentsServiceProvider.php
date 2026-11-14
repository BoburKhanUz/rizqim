<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\Payments\Console\SendBanquetPaymentReminders;
use App\Domain\Pos\Banquet\BanquetEventChanged;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P6-53: the payment schedule of banquet events. When an event is created or its total changes (BanquetEventChanged, after the
 * commit) its schedule is planned or re-planned under the event's lock (BanquetSchedule::sync: unpaid rows adjust, paid rows
 * never change); the daily Telegram reminders (pos:banquet-payment-reminders, 09:05 Tashkent); an advance, refund or
 * cancellation receipt the provider confirms (or fails) later is recorded on its entry (fiscal.receipt_updated).
 */
class PosBanquetPaymentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendBanquetPaymentReminders::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pos:banquet-payment-reminders')->dailyAt('09:05')->timezone('Asia/Tashkent')->withoutOverlapping(60);
        });

        // V1's BanquetEventChanged (after the commit): a new event or a changed total re-plans the unpaid rows
        Event::listen(BanquetEventChanged::class, function (BanquetEventChanged $changed) {
            if (!$changed->totalChanged && $changed->action !== 'created') {
                return;
            }
            DB::transaction(function () use ($changed) {
                $event = BanquetEvent::whereKey($changed->event->id)->lockForUpdate()->first();
                if ($event !== null) {
                    app(BanquetSchedule::class)->sync($event);
                }
            });
        });

        Event::listen(FiscalReceiptUpdatedBroadcast::class, function (FiscalReceiptUpdatedBroadcast $event) {
            if ($event->subjectType !== BanquetPaymentService::FISCAL_SUBJECT || $event->subjectId === null) {
                return;
            }
            try {
                $entry = BanquetPaymentEntry::find($event->subjectId);
                if ($entry === null) {
                    return;
                }
                $entry->forceFill(['fiscal_receipt_uuid' => $event->uuid, 'fiscal_status' => $event->status])->save();
                if ($entry->type === BanquetEntryType::PAYMENT && $entry->banquet_payment_id !== null) {
                    BanquetPayment::whereKey($entry->banquet_payment_id)->update(['fiscal_receipt_uuid' => $event->uuid, 'fiscal_status' => $event->status]);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
