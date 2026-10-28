<?php

namespace App\Services\Finance\Billing;

use App\Enums\DunningStage;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\StoreSubscription;
use App\Models\SubscriptionDunningEvent;
use App\Models\SubscriptionInvoice;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * P1-23 / P4-21: a current subscription's status from its store's invoices (docs/BILLING.md):
 * - locked: a v2 plan's invoice still unpaid more than config('billing.dunning.lock_after_days') days after its due
 *   date (from the start of the next day, Asia/Tashkent); a P1-23 plan is never locked, only past_due;
 * - past_due: an unpaid invoice past its due date;
 * - trialing: during the trial; otherwise active.
 *
 * One rule for the stored status (the daily run, a payment, a void) and for Entitlements, which works it out live, so
 * paying unlocks at once even before any job runs.
 */
class SubscriptionStatusResolver
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function timezone(): string
    {
        return (string) config('billing.timezone', 'Asia/Tashkent');
    }

    public function statusFor(StoreSubscription $subscription, CarbonInterface $now): SubscriptionStatus
    {
        if ($subscription->status === SubscriptionStatus::CANCELLED) {
            return SubscriptionStatus::CANCELLED;
        }

        $oldest = $this->oldestOverdue($subscription->store_id, $now);
        $isV2 = $subscription->plan()->value('version') >= 2;

        return match (true) {
            $oldest !== null && $isV2 && $this->lockAt($oldest)->lessThanOrEqualTo($now) => SubscriptionStatus::LOCKED,
            $oldest !== null => SubscriptionStatus::PAST_DUE,
            $subscription->trial_ends_at !== null && $subscription->trial_ends_at->greaterThan($now) => SubscriptionStatus::TRIALING,
            default => SubscriptionStatus::ACTIVE,
        };
    }

    /**
     * The store's unpaid invoice whose due date passed longest ago (null when none is late).
     */
    public function oldestOverdue(int $storeId, CarbonInterface $now): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::where('store_id', $storeId)
            ->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->where('due_date', '<', $this->today($now))
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();
    }

    /**
     * When an unpaid invoice locks its subscription: the start (Asia/Tashkent) of the day after due date + the lock
     * days. Due 11.11: reminders on 12, 14 and 18.11, locked from 19.11 00:00.
     */
    public function lockAt(SubscriptionInvoice $invoice): Carbon
    {
        return Carbon::parse($invoice->due_date->toDateString(), $this->timezone())
            ->addDays((int) config('billing.dunning.lock_after_days', 7) + 1)
            ->setTimezone((string) config('app.timezone'));
    }

    /**
     * Days since the due date (0 on it, 1 the day after), Asia/Tashkent calendar days.
     */
    public function daysOverdue(SubscriptionInvoice $invoice, CarbonInterface $now): int
    {
        $due = Carbon::parse($invoice->due_date->toDateString(), $this->timezone());
        $today = Carbon::parse($this->today($now), $this->timezone());

        return (int) $due->diffInDays($today, false);
    }

    public function today(CarbonInterface $now): string
    {
        return Carbon::instance($now)->setTimezone($this->timezone())->toDateString();
    }

    /**
     * Bring the store's current subscription's stored status up to date. Leaving `locked` (paid, voided) is the
     * unlock: recorded on the invoice that locked it and in the audit log.
     */
    public function refresh(int $storeId, CarbonInterface $now): ?StoreSubscription
    {
        $subscription = StoreSubscription::query()->current()->where('store_id', $storeId)->latest('id')->first();
        if ($subscription === null) {
            return null;
        }

        $status = $this->statusFor($subscription, $now);
        if ($status === $subscription->status) {
            return $subscription;
        }

        $was = $subscription->status;
        $update = ['status' => $status];
        if ($status === SubscriptionStatus::LOCKED && $subscription->locked_at === null) {
            $update['locked_at'] = $now;
        }
        if ($was === SubscriptionStatus::LOCKED && $status !== SubscriptionStatus::LOCKED) {
            $update['locked_at'] = null;
        }
        $subscription->update($update);

        if ($was === SubscriptionStatus::LOCKED && $status !== SubscriptionStatus::LOCKED) {
            $this->recordUnlock($subscription, $now);
        }
        if ($status === SubscriptionStatus::LOCKED) {
            $this->audit->record('subscription.locked', $subscription, ['status' => $was->value], ['status' => 'locked'], [], ['store_id' => $subscription->store_id]);
        }

        return $subscription;
    }

    protected function recordUnlock(StoreSubscription $subscription, CarbonInterface $now): void
    {
        // The invoice that locked it is the last one with a lock event and no unlock yet
        $invoiceId = SubscriptionDunningEvent::where('store_id', $subscription->store_id)
            ->where('stage', DunningStage::LOCKED->value)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('subscription_dunning_events as u')
                ->whereColumn('u.subscription_invoice_id', 'subscription_dunning_events.subscription_invoice_id')
                ->where('u.stage', DunningStage::UNLOCKED->value))
            ->orderByDesc('id')
            ->value('subscription_invoice_id');

        if ($invoiceId !== null) {
            SubscriptionDunningEvent::firstOrCreate(
                ['subscription_invoice_id' => $invoiceId, 'stage' => DunningStage::UNLOCKED->value],
                ['store_id' => $subscription->store_id, 'notified' => false, 'recipients' => 0, 'occurred_at' => $now],
            );
        }

        $this->audit->record('subscription.unlocked', $subscription, ['status' => 'locked'], ['status' => $subscription->status->value], [], ['store_id' => $subscription->store_id]);
    }
}
