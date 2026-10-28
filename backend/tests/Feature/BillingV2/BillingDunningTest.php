<?php

namespace Tests\Feature\BillingV2;

use App\Enums\DunningStage;
use App\Enums\SubscriptionStatus;
use App\Jobs\SendBillingRemindersJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionDunningEvent;
use App\Models\User;
use App\Services\Finance\Billing\DunningService;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-21 (owner decision 28.10.2026): reminders 1, 3 and 7 days after the due date (Telegram to the owner and the
 * store's chat, and the partner web notice), the lock from the 8th day (settings and features outside the plan; the
 * till never stops), and paying unlocks at once (docs/BILLING.md, "Eslatmalar va cheklov").
 */
class BillingDunningTest extends TestCase
{
    use BillingV2Fixtures;
    use RefreshDatabase;

    private Store $store;

    private StoreSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutTrials();
        config(['services.telegram.bot_token' => '1:token']);
        Queue::fake([SendTelegramRequest::class]);

        $this->store = $this->v2Store('Qarzdor', ['telegram_admin_chat_id' => '-100777']);
        User::create(['name' => 'Ega', 'phone' => '+998901234567', 'password' => bcrypt('x'), 'store_id' => $this->store->id, 'role' => 'owner', 'telegram_id' => '5550001']);
        $this->subscription = $this->billing()->assignPlan($this->store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));   // October's invoice, due 11 November
    }

    public function test_reminders_on_days_one_three_and_seven_once_each_then_the_lock(): void
    {
        $this->dunning('2026-11-11 10:05');
        Queue::assertNothingPushed();

        $this->dunning('2026-11-12 10:05');
        $this->dunning('2026-11-12 18:00');   // the same day again: nothing twice
        $this->assertStages([DunningStage::REMINDER_1]);
        Queue::assertPushed(SendTelegramRequest::class, 2);   // the store's chat and the owner
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100777'
            && str_contains($job->params['text'], '1 kun o\'tdi') && str_contains($job->params['text'], '19.11.2026'));
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5550001');

        $entitlements = $this->entitlements('2026-11-12 18:00');
        $this->assertSame('past_due', $entitlements['status']);
        $this->assertSame('reminder', $entitlements['notice']['level']);
        $this->assertSame($this->at('2026-11-19 00:00')->toIso8601String(), $entitlements['lock_at']);
        $this->assertFalse($entitlements['settings_locked']);

        $this->dunning('2026-11-13 10:05');
        $this->assertStages([DunningStage::REMINDER_1]);
        $this->dunning('2026-11-14 10:05');
        $this->dunning('2026-11-18 10:05');
        $this->assertStages([DunningStage::REMINDER_1, DunningStage::REMINDER_3, DunningStage::REMINDER_7]);
        Queue::assertPushed(SendTelegramRequest::class, 6);

        // Day 7 is not locked yet
        $this->billing()->run($this->at('2026-11-18 23:59'));
        $this->assertSame(SubscriptionStatus::PAST_DUE, $this->subscription->fresh()->status);

        // From the 8th day: the 00:15 run locks it, the reminder run tells the owner once
        $this->billing()->run($this->at('2026-11-19 00:15'));
        $this->assertSame(SubscriptionStatus::LOCKED, $this->subscription->fresh()->status);
        $this->assertNotNull($this->subscription->fresh()->locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.locked', 'store_id' => $this->store->id]);

        $this->dunning('2026-11-19 10:05');
        $this->dunning('2026-11-20 10:05');
        $this->assertStages([DunningStage::REMINDER_1, DunningStage::REMINDER_3, DunningStage::REMINDER_7, DunningStage::LOCKED]);
        Queue::assertPushed(SendTelegramRequest::class, 8);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => str_contains($job->params['text'], 'yopildi')
            && str_contains($job->params['text'], "Kassa to'lov qabul qilish va chek chiqarishda davom etadi"));

        $entitlements = $this->entitlements('2026-11-20 10:05');
        $this->assertSame('locked', $entitlements['status']);
        $this->assertTrue($entitlements['settings_locked']);
        $this->assertSame($this->at('2026-11-19 00:00')->toIso8601String(), $entitlements['locked_since']);
        $this->assertSame('locked', $entitlements['notice']['level']);
    }

    public function test_paying_unlocks_at_once_without_waiting_for_a_run(): void
    {
        $this->billing()->run($this->at('2026-11-20 00:15'));
        $this->dunning('2026-11-20 10:05');
        $this->assertSame(SubscriptionStatus::LOCKED, $this->subscription->fresh()->status);

        $this->travelTo($this->at('2026-11-20 12:00'));
        app(LedgerService::class)->reduceDebt($this->store, Money::of('149000.00'), 'Click top-up');

        $subscription = $this->subscription->fresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.unlocked', 'store_id' => $this->store->id]);
        $this->assertTrue(SubscriptionDunningEvent::where('stage', DunningStage::UNLOCKED->value)->exists());

        $entitlements = $this->entitlements('2026-11-20 12:00');
        $this->assertSame('active', $entitlements['status']);
        $this->assertNull($entitlements['notice']);
        $this->assertFalse($entitlements['settings_locked']);
    }

    public function test_marking_the_invoice_paid_by_hand_unlocks_too(): void
    {
        $this->billing()->run($this->at('2026-11-20 00:15'));
        $this->assertSame(SubscriptionStatus::LOCKED, $this->subscription->fresh()->status);

        $this->billing()->markPaid(\App\Models\SubscriptionInvoice::sole(), null, $this->at('2026-11-20 09:00'));

        $this->assertSame(SubscriptionStatus::ACTIVE, $this->subscription->fresh()->status);
        $this->assertSame('active', $this->entitlements('2026-11-20 09:00')['status']);
        // The ledger has not received the money: it is still the subscription part of the debt, outside the credit limit
        $this->assertSame(['149000.00', '149000.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->subscription_debt]);
        $this->assertFalse($this->store->fresh()->exceedsCreditLimit());
    }

    public function test_a_run_that_missed_days_sends_only_the_latest_reminder(): void
    {
        $this->dunning('2026-11-16 10:05');

        $events = SubscriptionDunningEvent::orderBy('id')->get();
        $this->assertSame(['reminder_1', 'reminder_3'], $events->map(fn ($event) => $event->stage->value)->all());
        $this->assertSame([false, true], $events->pluck('notified')->all());
        Queue::assertPushed(SendTelegramRequest::class, 2);
    }

    public function test_a_p1_23_plan_gets_reminders_but_is_never_locked(): void
    {
        $legacy = $this->billingStore('Eski', ['telegram_admin_chat_id' => '-100888']);
        $subscription = $this->billing()->assignPlan($legacy, $this->plan(['monthly_base_fee' => 100000]), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $this->billing()->run($this->at('2026-12-20 00:15'));
        $this->dunning('2026-12-20 10:05');

        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
        // October's and November's invoices (due 11.11 and 11.12) both got their reminders; no lock
        $stages = SubscriptionDunningEvent::where('store_id', $legacy->id)->orderBy('id')->get()->map(fn ($event) => $event->stage->value)->all();
        $this->assertSame(['reminder_1', 'reminder_3', 'reminder_7', 'reminder_1', 'reminder_3', 'reminder_7'], $stages);
        $this->assertFalse(app(Entitlements::class)->toArray($legacy, $this->at('2026-12-20 10:05'))['settings_locked']);
    }

    public function test_the_reminder_job_is_scheduled_daily_in_tashkent_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->description, 'billing-dunning') || str_contains((string) $event->description, SendBillingRemindersJob::class));

        $this->assertNotNull($event);
        $this->assertSame('5 10 * * *', $event->expression);
        $this->assertSame('Asia/Tashkent', $event->timezone);
    }

    private function dunning(string $at): void
    {
        app(DunningService::class)->run($this->at($at));
    }

    /**
     * @param  list<DunningStage>  $stages
     */
    private function assertStages(array $stages): void
    {
        $this->assertSame(
            array_map(fn (DunningStage $stage) => $stage->value, $stages),
            SubscriptionDunningEvent::where('store_id', $this->store->id)->orderBy('id')->get()->map(fn ($event) => $event->stage->value)->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlements(string $at): array
    {
        $entitlements = app(Entitlements::class);
        $entitlements->forget();

        return $entitlements->toArray($this->store, $this->at($at));
    }
}
