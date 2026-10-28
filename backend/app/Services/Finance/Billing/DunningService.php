<?php

namespace App\Services\Finance\Billing;

use App\Enums\DunningStage;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\SubscriptionDunningEvent;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-21: the unpaid-invoice routine (docs/BILLING.md, "Eslatmalar va cheklov"; owner decision 28.10.2026), run daily
 * by SendBillingRemindersJob:
 * - reminders on days 1, 3 and 7 after an invoice's due date (config('billing.dunning.reminder_days')): a Telegram
 *   message to the store's admin chat and its owners (queued, as every Telegram message), and the partner web notice
 *   (Entitlements' `notice`, worked out live);
 * - from the day after due date + 7 the v2 subscription is locked (SubscriptionStatusResolver): the store hears it
 *   once; settings and features outside the plan close; the till keeps taking payments and printing receipts.
 * Each step is recorded once per invoice (subscription_dunning_events): running again sends nothing twice; a run that
 * missed days sends only the latest step due and records the skipped ones without a message. Paying unlocks at once
 * (SubscriptionDebt → SubscriptionStatusResolver), whatever this run does.
 */
class DunningService
{
    public function __construct(
        protected SubscriptionStatusResolver $statuses,
        protected TelegramMessenger $telegram,
    ) {}

    /**
     * @return array{reminders: int, locked: int}
     */
    public function run(?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now())->setTimezone((string) config('app.timezone'));
        $sent = ['reminders' => 0, 'locked' => 0];

        $invoices = SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->where('due_date', '<', $this->statuses->today($now))
            ->whereIn('store_id', Store::query()->select('id'))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $result = DB::transaction(fn () => $this->step($invoice, $now));
                $sent['reminders'] += $result === 'reminder' ? 1 : 0;
                $sent['locked'] += $result === 'locked' ? 1 : 0;
            } catch (Throwable $e) {
                // One store's failure never stops the others; the next run tries again
                Log::error("Dunning for invoice {$invoice->number} failed", ['error' => $e->getMessage()]);
                report($e);
            }
        }

        return $sent;
    }

    /**
     * The steps due for one invoice: records them, sends the latest. Returns what was sent.
     */
    protected function step(SubscriptionInvoice $invoice, Carbon $now): ?string
    {
        $invoice = SubscriptionInvoice::whereKey($invoice->id)->lockForUpdate()->first();
        if ($invoice === null || $invoice->status !== SubscriptionInvoiceStatus::ISSUED) {
            return null;
        }

        $days = $this->statuses->daysOverdue($invoice, $now);
        $lockAt = $this->statuses->lockAt($invoice);
        $locks = $this->planVersion($invoice) >= 2 && $lockAt->lessThanOrEqualTo($now);

        $due = [];
        foreach ((array) config('billing.dunning.reminder_days', [1, 3, 7]) as $day) {
            $stage = DunningStage::reminderFor((int) $day);
            if ($stage !== null && $days >= (int) $day) {
                $due[] = $stage;
            }
        }
        if ($locks) {
            $due[] = DunningStage::LOCKED;
        }

        $recorded = SubscriptionDunningEvent::where('subscription_invoice_id', $invoice->id)->pluck('stage')
            ->map(fn ($stage) => $stage instanceof DunningStage ? $stage->value : (string) $stage)->all();
        $pending = array_values(array_filter($due, fn (DunningStage $stage) => !in_array($stage->value, $recorded, true)));
        if ($pending === []) {
            return null;
        }

        $last = $pending[count($pending) - 1];
        foreach ($pending as $stage) {
            $notify = $stage === $last;
            $recipients = $notify ? $this->notify($invoice, $stage, $days, $lockAt) : 0;
            SubscriptionDunningEvent::create([
                'store_id' => $invoice->store_id,
                'subscription_invoice_id' => $invoice->id,
                'stage' => $stage,
                'notified' => $notify,
                'recipients' => $recipients,
                'occurred_at' => $now,
            ]);
        }

        if ($locks) {
            $this->statuses->refresh($invoice->store_id, $now);
        }

        return $last === DunningStage::LOCKED ? 'locked' : 'reminder';
    }

    /**
     * The store's admin chat and its owners on Telegram (once each); returns how many were messaged.
     */
    protected function notify(SubscriptionInvoice $invoice, DunningStage $stage, int $days, Carbon $lockAt): int
    {
        if (!$this->telegram->isConfigured()) {
            return 0;
        }

        $store = Store::find($invoice->store_id);
        $chats = [];
        if ($store?->telegram_admin_chat_id) {
            $chats[] = (string) $store->telegram_admin_chat_id;
        }
        foreach (User::where('store_id', $invoice->store_id)->where('role', UserRole::OWNER->value)->orderBy('id')->get() as $owner) {
            if ($owner->hasTelegram()) {
                $chats[] = (string) $owner->telegram_id;
            }
        }
        $chats = array_values(array_unique(array_filter($chats)));

        $text = $this->message($invoice, $stage, $days, $lockAt);
        foreach ($chats as $chat) {
            $this->telegram->sendMessage($chat, $text);
        }

        return count($chats);
    }

    protected function message(SubscriptionInvoice $invoice, DunningStage $stage, int $days, Carbon $lockAt): string
    {
        $amount = number_format($invoice->outstanding()->roundTo(0)->toFloat(), 0, '.', ' ');
        $due = $invoice->due_date->format('d.m.Y');
        $lockDate = $lockAt->copy()->setTimezone($this->statuses->timezone())->format('d.m.Y');
        $locks = $this->planVersion($invoice) >= 2;

        if ($stage === DunningStage::LOCKED) {
            return "RIZQIM: {$invoice->number} hisob-faktura ({$amount} so'm) to'lanmagani sababli yangi sozlamalar va tarifdagidan ortiq imkoniyatlar yopildi. "
                . "To'lov qilinishi bilan darhol ochiladi. Kassa to'lov qabul qilish va chek chiqarishda davom etadi.";
        }

        return "RIZQIM: {$invoice->number} hisob-faktura ({$amount} so'm) to'lov muddati {$due} edi, {$days} kun o'tdi. "
            . "Balansni Click yoki Payme orqali to'ldiring yoki hisob raqamiga o'tkazing."
            . ($locks ? " {$lockDate} dan yangi sozlamalar va tarifdagidan ortiq imkoniyatlar yopiladi; kassa to'lov qabul qilishda davom etadi." : '');
    }

    /**
     * A v2 plan's invoice locks; a P1-23 plan's only gets reminders.
     */
    protected function planVersion(SubscriptionInvoice $invoice): int
    {
        return (int) (\App\Models\TariffPlan::whereKey($invoice->tariff_plan_id)->value('version') ?? 1);
    }
}
