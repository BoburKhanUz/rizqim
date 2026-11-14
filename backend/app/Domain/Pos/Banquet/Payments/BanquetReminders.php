<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\Branch;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P6-53: the daily Telegram reminders of banquet payments (pos:banquet-payment-reminders): the store's admin chat and
 * owners (PosOwnerAlerts) hear of every unpaid row of a live event (hold, confirmed, in progress) N days before its due
 * date (the store's reminder_days, default 3 and 1) and, once overdue, on the first day after it and then every 7 days
 * (overdue_reminders). One reminder per row and business day at most (banquet_payment_reminders: running the command
 * twice sends nothing new). The branch's business day decides "today".
 */
final class BanquetReminders
{
    public const OVERDUE_REPEAT_DAYS = 7;

    public function __construct(
        private readonly BanquetPaymentSettings $settings,
        private readonly PosOwnerAlerts $alerts,
    ) {}

    /**
     * Returns how many reminders were sent.
     */
    public function run(): int
    {
        $sent = 0;
        $todays = [];
        $settings = [];
        $live = [BanquetEventStatus::HOLD->value, BanquetEventStatus::CONFIRMED->value, BanquetEventStatus::IN_PROGRESS->value];
        BanquetPayment::query()
            ->where('status', BanquetPaymentStatus::DUE->value)
            ->whereNotNull('due_date')
            ->whereHas('event', fn ($query) => $query->whereIn('status', $live))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$sent, &$todays, &$settings) {
                foreach ($rows as $row) {
                    $today = $todays[$row->branch_id] ??= BusinessDay::of(Branch::findOrFail($row->branch_id));
                    $rules = $settings[$row->store_id] ??= $this->settings->of((int) $row->store_id);
                    $days = (int) Carbon::parse($today)->diffInDays(Carbon::parse($row->due_date->toDateString()), false);
                    $kind = null;
                    if ($days >= 0 && in_array($days, $rules['reminder_days'], true)) {
                        $kind = 'before';
                    } elseif ($days < 0 && $rules['overdue_reminders'] && (-$days === 1 || -$days % self::OVERDUE_REPEAT_DAYS === 0)) {
                        $kind = 'overdue';
                    }
                    if ($kind === null) {
                        continue;
                    }
                    $inserted = DB::table('banquet_payment_reminders')->insertOrIgnore([
                        'banquet_payment_id' => $row->id,
                        'kind' => $kind,
                        'sent_on' => $today,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($inserted === 0) {
                        continue;
                    }
                    $event = BanquetEvent::find($row->banquet_event_id);
                    if ($event === null) {
                        continue;
                    }
                    $this->alerts->send((int) $row->store_id, $this->text($event, $row, $kind, $days));
                    $sent++;
                }
            });

        return $sent;
    }

    private function text(BanquetEvent $event, BanquetPayment $row, string $kind, int $days): string
    {
        $when = $kind === 'overdue'
            ? '⏰ Muddati ' . (-$days) . " kun o'tdi"
            : ($days === 0 ? "📅 Bugun to'lanishi kerak" : "📅 {$days} kundan so'ng to'lanadi");
        $title = $event->title ?: $event->event_type->label();

        return "{$when}: {$row->kind->label()} " . PosOwnerAlerts::sum(Money::of($row->amount))
            . "\nTadbir: {$title}, " . $event->event_date->format('d.m.Y') . ", {$event->customer_name}"
            . "\nTo'lov sanasi: " . $row->due_date?->format('d.m.Y');
    }
}
