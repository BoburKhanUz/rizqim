<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\DeviceType;
use App\Events\Broadcast\PlatformAlertBroadcast;
use App\Jobs\EvaluateAlertsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Models\Store;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\Checks\AlertCheck;
use App\Services\Admin\Monitoring\Checks\FiscalFailuresCheck;
use App\Services\Admin\Monitoring\OpenAlertsAttentionSource;
use App\Support\HealthReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P4-15: the alert engine. Each built-in rule kind opens an alert when its problem exists, dedupes it per rule and
 * subject, resolves it when the problem is gone, tells the ops Telegram group (rate limited) and `platform.ops`, and the
 * open alerts feed "Bugun → Diqqat talab qiladi".
 */
class AdminMonitoringEvaluatorTest extends TestCase
{
    use RefreshDatabase;
    use MonitoringFixtures;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
    }

    private function run_(): array
    {
        return app(AlertEvaluator::class)->run();
    }

    private function alerts(?AlertKind $kind = null)
    {
        return Alert::query()->when($kind, fn ($q) => $q->where('kind', $kind->value))->orderBy('id')->get();
    }

    /**
     * The store's opening hours as the columns hold them (the model also mirrors them into payment_settings).
     */
    private function hours(Store $store, string $open, string $close): void
    {
        DB::table('stores')->where('id', $store->id)->update(['open_time' => $open, 'close_time' => $close, 'timezone' => 'Asia/Tashkent']);
    }

    private function telegramOn(string $chat = '-100777'): void
    {
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => $chat]);
        Bus::fake();
    }

    private function sentTexts(): array
    {
        return Bus::dispatched(SendTelegramRequest::class)->map(fn ($job) => $job->params['text'])->values()->all();
    }

    // --- open, dedupe, resolve --------------------------------------------------------------------------------------

    public function test_failed_jobs_spike_opens_dedupes_and_resolves_when_the_failures_age_out(): void
    {
        $this->onlyRule(AlertKind::FAILED_JOBS_SPIKE, ['threshold' => 3, 'window_minutes' => 15]);
        Event::fake([PlatformAlertBroadcast::class]);

        $this->failedJob();
        $this->failedJob();
        $this->assertSame(['opened' => 0, 'updated' => 0, 'resolved' => 0], $this->run_());

        $this->failedJob();
        $this->assertSame(['opened' => 1, 'updated' => 0, 'resolved' => 0], $this->run_());

        $alert = $this->alerts()->sole();
        $this->assertSame('failed_jobs_spike', $alert->kind);
        $this->assertSame('warning', $alert->severity);
        $this->assertSame('open', $alert->state);
        $this->assertSame('global', $alert->subject_key);
        $this->assertSame("Oxirgi 15 daqiqada 3 ta vazifa yiqildi", $alert->title);
        $this->assertSame(3, $alert->details['count']);
        $this->assertSame([['job' => 'SendTelegramRequest', 'count' => 3]], $alert->details['top_jobs']);
        $this->assertSame(1, $alert->count);

        // The next minute: the same alert is updated, not duplicated
        $this->travel(1)->minutes();
        $this->failedJob();
        $this->assertSame(['opened' => 0, 'updated' => 1, 'resolved' => 0], $this->run_());
        $alert->refresh();
        $this->assertSame(1, Alert::count());
        $this->assertSame(2, $alert->count);
        $this->assertSame(4, $alert->details['count']);
        $this->assertTrue($alert->last_seen_at->gt($alert->first_seen_at));

        // Once the failures are older than the window the alert resolves itself
        $this->travel(20)->minutes();
        $this->assertSame(['opened' => 0, 'updated' => 0, 'resolved' => 1], $this->run_());
        $alert->refresh();
        $this->assertSame('resolved', $alert->state);
        $this->assertSame('auto', $alert->resolution);
        $this->assertNull($alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);

        Event::assertDispatched(PlatformAlertBroadcast::class, fn ($e) => $e->broadcastAs() === 'ops.alert.opened');
        Event::assertDispatchedTimes(PlatformAlertBroadcast::class, 2);
    }

    public function test_a_problem_that_comes_back_is_a_new_alert_and_an_acknowledged_one_stays_acknowledged(): void
    {
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        $this->run_(); // no heartbeat: stale
        $first = $this->alerts()->sole();
        $first->forceFill(['state' => 'acknowledged', 'acknowledged_at' => now()])->save();

        $this->run_();
        $first->refresh();
        $this->assertSame('acknowledged', $first->state);
        $this->assertSame(2, $first->count);

        HealthReport::beat();
        $this->run_();
        $this->assertSame('resolved', $first->refresh()->state);

        Cache::forget(HealthReport::HEARTBEAT_KEY);
        $this->run_();
        $alerts = $this->alerts();
        $this->assertCount(2, $alerts);
        $this->assertSame(['resolved', 'open'], $alerts->pluck('state')->all());
    }

    public function test_the_database_refuses_two_unresolved_alerts_for_one_rule_and_subject(): void
    {
        $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'default']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'default']);
    }

    public function test_queue_backlog_opens_one_alert_per_queue_by_size_or_by_age(): void
    {
        config(['queue.default' => 'database']);
        $this->onlyRule(AlertKind::QUEUE_BACKLOG, ['threshold' => 5, 'threshold_secondary' => 10]);

        $job = fn (string $queue, int $availableAt) => ['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $availableAt, 'created_at' => $availableAt];
        // default: 6 fresh jobs (too many); reports: 1 job waiting 11 minutes (too old); broadcasts: 2 fresh jobs (fine)
        DB::table('jobs')->insert(array_map(fn () => $job('default', time()), range(1, 6)));
        DB::table('jobs')->insert([$job('reports', time() - 11 * 60)]);
        DB::table('jobs')->insert([$job('broadcasts', time()), $job('broadcasts', time())]);

        $this->assertSame(2, $this->run_()['opened']);

        $alerts = $this->alerts()->keyBy('subject_key');
        $this->assertSame(['default', 'reports'], $alerts->keys()->all());
        $this->assertStringContainsString('6 ta vazifa', $alerts['default']->title);
        $this->assertSame(6, $alerts['default']->details['size']);
        $this->assertStringContainsString('11 daqiqadan beri kutmoqda', $alerts['reports']->title);
        $this->assertGreaterThanOrEqual(660, $alerts['reports']->details['oldest_age_seconds']);

        DB::table('jobs')->where('queue', 'default')->delete();
        $this->assertSame(1, $this->run_()['resolved']);
        $this->assertSame('resolved', $alerts['default']->refresh()->state);
        $this->assertSame('open', $alerts['reports']->refresh()->state);
    }

    public function test_pos_device_offline_counts_only_the_stores_business_hours(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        // Store hours 09:00-23:00 Asia/Tashkent
        $store = $this->makeStore('Choyxona');
        $this->hours($store, '09:00:00', '23:00:00');

        // 10:00 Tashkent = 05:00 UTC. Last heard 30 minutes ago: an alert.
        $this->travelTo(Carbon::parse('2026-11-03 05:00:00', 'UTC'));
        $stale = $this->terminal($store, ['name' => 'Kassa 1', 'last_seen_at' => now()->subMinutes(30)]);
        $this->terminal($store, ['name' => 'Kassa 2', 'last_seen_at' => now()->subMinutes(5)]);
        $this->terminal($store, ['name' => 'Eski', 'last_seen_at' => now()->subDays(8)]);
        $this->terminal($store, ['name' => 'Bekor', 'last_seen_at' => now()->subHour(), 'revoked_at' => now()]);
        $this->terminal($store, ['name' => 'Telefon', 'type' => DeviceType::COURIER->value, 'last_seen_at' => now()->subHour()]);
        $kds = $this->terminal($store, ['name' => 'Oshxona', 'type' => DeviceType::KDS->value, 'last_seen_at' => now()->subHours(2)]);
        $closedStore = $this->makeStore('Yopiq');
        $closedStore->forceFill(['status' => 'suspended'])->save();
        $this->terminal($closedStore, ['last_seen_at' => now()->subHours(2)]);

        $this->assertSame(2, $this->run_()['opened']);
        $alerts = $this->alerts()->keyBy('subject_key');
        $this->assertEqualsCanonicalizing([$stale->uuid, $kds->uuid], $alerts->keys()->all());
        $this->assertSame("Choyxona: Kassa 1 30 daqiqadan beri aloqada emas", $alerts[$stale->uuid]->title);
        $this->assertSame($store->id, $alerts[$stale->uuid]->store_id);
        $this->assertSame('Kassa 1', $alerts[$stale->uuid]->details['device_name']);
        $this->assertStringStartsWith('Choyxona: Oshxona', $alerts[$kds->uuid]->title);

        // The store closes at 23:00 (18:00 UTC): nothing counts after that, and the alerts go
        $this->travelTo(Carbon::parse('2026-11-03 19:00:00', 'UTC'));
        $this->assertSame(2, $this->run_()['resolved']);
    }

    public function test_a_till_that_is_off_overnight_is_not_offline_right_after_opening(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Erta ochiladigan');
        $this->hours($store, '09:00:00', '23:00:00');
        $till = $this->terminal($store, ['last_seen_at' => Carbon::parse('2026-11-02 18:00:00', 'UTC')]); // 23:00 yesterday

        // 09:10 Tashkent: opened 10 minutes ago, below the 15 minute threshold
        $this->travelTo(Carbon::parse('2026-11-03 04:10:00', 'UTC'));
        $this->assertSame(0, $this->run_()['opened']);

        // 09:20: 20 minutes of business hours without a sign
        $this->travelTo(Carbon::parse('2026-11-03 04:20:00', 'UTC'));
        $this->assertSame(1, $this->run_()['opened']);
        $this->assertSame(20, $this->alerts()->sole()->details['offline_minutes']);

        // It reports: resolved
        $till->forceFill(['last_seen_at' => now()])->save();
        $this->assertSame(1, $this->run_()['resolved']);
    }

    public function test_overnight_hours_and_all_day_stores(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $bar = $this->makeStore('Tungi bar');
        $this->hours($bar, '18:00:00', '02:00:00');
        $always = $this->makeStore('Doim ochiq');
        $this->hours($always, '00:00:00', '00:00:00');

        // 01:00 Tashkent (20:00 UTC): the bar is open since 18:00 yesterday
        $this->travelTo(Carbon::parse('2026-11-03 20:00:00', 'UTC'));
        $this->terminal($bar, ['last_seen_at' => now()->subHours(3)]);
        $this->terminal($always, ['last_seen_at' => now()->subHour()]);
        $this->assertSame(2, $this->run_()['opened']);

        // 04:00 Tashkent (23:00 UTC): the bar is closed, the other store is still open
        $this->travelTo(Carbon::parse('2026-11-03 23:00:00', 'UTC'));
        $this->assertSame(1, $this->run_()['resolved']);
        $this->assertSame(['Doim ochiq'], $this->alerts()->where('state', 'open')->map(fn ($a) => $a->details['store'])->values()->all());
    }

    public function test_fiscal_failures_count_failed_receipts_in_the_window_and_name_the_stores(): void
    {
        $this->setUpLifecycleStore();
        $this->onlyRule(AlertKind::FISCAL_FAILURES, ['threshold' => 2, 'window_minutes' => 60]);

        $this->failedReceipt($this->store);
        $this->failedReceipt($this->store, ['status' => 'issued']);
        $this->failedReceipt($this->store, ['status' => 'pending']);
        $old = $this->failedReceipt($this->store);
        $old->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();
        $this->assertSame(0, $this->run_()['opened']);

        $this->failedReceipt($this->store);
        $this->assertSame(1, $this->run_()['opened']);
        $alert = $this->alerts()->sole();
        $this->assertSame('critical', $alert->severity);
        $this->assertSame(2, $alert->details['count']);
        $this->assertSame([['store_id' => $this->store->id, 'name' => 'Lifecycle Burger', 'failures' => 2]], $alert->details['stores']);
    }

    public function test_settlement_failures_follow_the_orders_waiting_for_their_booking(): void
    {
        $this->setUpLifecycleStore();
        $this->onlyRule(AlertKind::SETTLEMENT_FAILURES, ['threshold' => 1]);
        $this->assertSame(0, $this->run_()['opened']);

        $order = $this->makeOrder(\App\Enums\OrderStatus::DELIVERED);
        $order->forceFill(['settlement_failed_at' => now()->subMinutes(20), 'settlement_error' => 'ledger down'])->save();

        $this->assertSame(1, $this->run_()['opened']);
        $alert = $this->alerts()->sole();
        $this->assertSame('critical', $alert->severity);
        $this->assertSame("1 ta buyurtma puli hisobga yozilmadi", $alert->title);
        $this->assertSame([$order->uuid], $alert->details['orders']);
        $this->assertStringNotContainsString('ledger down', json_encode($alert->details));

        $order->forceFill(['settlement_failed_at' => null])->save(); // SettlementRecoveryService booked it
        $this->assertSame(1, $this->run_()['resolved']);
    }

    public function test_backup_stale_waits_for_a_marker_or_the_rules_age_and_resolves_on_a_new_dump(): void
    {
        $rule = $this->onlyRule(AlertKind::BACKUP_STALE, ['threshold' => 26]);

        // A fresh install: no marker yet, but the rule is new too, so no alert yet
        $this->assertSame(0, $this->run_()['opened']);

        // The rule is older than the threshold and still nothing recorded
        $rule->forceFill(['created_at' => now()->subHours(30)])->save();
        $this->assertSame(1, $this->run_()['opened']);
        $this->assertSame('Zaxira nusxa hech qachon qayd etilmagan', $this->alerts()->sole()->title);

        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHours(40)]);
        $this->run_();
        $this->assertSame('Oxirgi zaxira nusxa 40 soat oldin olingan', $this->alerts()->sole()->title);

        MonitoringMarker::query()->find('backup')->forceFill(['recorded_at' => now()->subHour()])->save();
        $this->assertSame(1, $this->run_()['resolved']);
    }

    public function test_scheduler_stale_uses_the_heartbeat_age_and_the_rules_seconds(): void
    {
        $this->onlyRule(AlertKind::SCHEDULER_STALE, ['threshold' => 120]);

        Cache::put(HealthReport::HEARTBEAT_KEY, time() - 100, 600);
        $this->assertSame(0, $this->run_()['opened']);

        Cache::put(HealthReport::HEARTBEAT_KEY, time() - 400, 600);
        $this->assertSame(1, $this->run_()['opened']);
        $alert = $this->alerts()->sole();
        $this->assertSame('critical', $alert->severity);
        $this->assertSame('Scheduler 6 daqiqadan beri yurak urishini yozmayapti', $alert->title);
    }

    // --- rule switches and failing checks ---------------------------------------------------------------------------

    public function test_switching_a_rule_off_resolves_its_alerts_quietly(): void
    {
        $this->telegramOn();
        $rule = $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->run_();
        $this->assertSame('open', $this->alerts()->sole()->state);
        Bus::assertDispatchedTimes(SendTelegramRequest::class, 1);

        $rule->forceFill(['enabled' => false])->save();
        $this->assertSame(1, $this->run_()['resolved']);

        $alert = $this->alerts()->sole();
        $this->assertSame('resolved', $alert->state);
        $this->assertSame('rule_disabled', $alert->resolution);
        $this->assertSame("Qoida o'chirildi", $alert->resolve_note);
        Bus::assertDispatchedTimes(SendTelegramRequest::class, 1);

        // Off stays off
        $this->assertSame(['opened' => 0, 'updated' => 0, 'resolved' => 0], $this->run_());
    }

    public function test_a_check_that_fails_or_cannot_read_its_source_leaves_alerts_alone(): void
    {
        $this->onlyRule(AlertKind::FISCAL_FAILURES);
        $existing = $this->makeAlert(AlertKind::FISCAL_FAILURES, ['subject_key' => 'global']);
        Log::spy();

        $this->app->bind(FiscalFailuresCheck::class, fn () => new class implements AlertCheck {
            public function findings(AlertRule $rule): ?array
            {
                throw new \RuntimeException('boom');
            }
        });
        $this->assertSame(['opened' => 0, 'updated' => 0, 'resolved' => 0], $this->run_());
        $this->assertSame('open', $existing->refresh()->state);
        Log::shouldHaveReceived('error')->once();

        $this->app->bind(FiscalFailuresCheck::class, fn () => new class implements AlertCheck {
            public function findings(AlertRule $rule): ?array
            {
                return null;
            }
        });
        $this->assertSame(['opened' => 0, 'updated' => 0, 'resolved' => 0], $this->run_());
        $this->assertSame('open', $existing->refresh()->state);

        // One broken rule does not stop the others
        $this->rule(AlertKind::SCHEDULER_STALE, ['enabled' => true]);
        $this->app->bind(FiscalFailuresCheck::class, fn () => new class implements AlertCheck {
            public function findings(AlertRule $rule): ?array
            {
                throw new \RuntimeException('boom');
            }
        });
        $this->assertSame(1, $this->run_()['opened']);
    }

    public function test_the_job_runs_the_evaluator(): void
    {
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        EvaluateAlertsJob::dispatchSync();

        $this->assertSame('scheduler_stale', $this->alerts()->sole()->kind);
    }

    public function test_the_evaluator_is_scheduled_every_minute_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'monitoring-evaluate-alerts');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // --- realtime ---------------------------------------------------------------------------------------------------

    public function test_alert_changes_are_broadcast_on_platform_ops_without_personal_data(): void
    {
        Event::fake([PlatformAlertBroadcast::class]);
        $this->setUpLifecycleStore();
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        $this->run_();
        $alert = $this->alerts()->sole();
        app(AlertEvaluator::class)->acknowledge($alert, $this->makeStaff(\App\Enums\UserRole::OWNER), 'Qarayapman');
        HealthReport::beat();
        $this->run_();

        $names = [];
        Event::assertDispatched(PlatformAlertBroadcast::class, function ($event) use (&$names, $alert) {
            $names[] = $event->broadcastAs();
            $this->assertSame('platform.ops', $event->broadcastOn()[0]->name === 'private-platform.ops' ? 'platform.ops' : $event->broadcastOn()[0]->name);
            $this->assertSame(['id', 'kind', 'severity', 'state'], array_keys($event->broadcastWith()));
            $this->assertSame($alert->id, $event->broadcastWith()['id']);

            return true;
        });
        $this->assertSame(['ops.alert.opened', 'ops.alert.acknowledged', 'ops.alert.resolved'], $names);
    }

    // --- Telegram ---------------------------------------------------------------------------------------------------

    public function test_the_ops_group_hears_when_an_alert_opens_and_when_it_resolves(): void
    {
        $this->telegramOn('-100777');
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        $this->run_();
        $this->assertCount(1, $this->sentTexts());
        Bus::assertDispatched(SendTelegramRequest::class, fn ($job) => $job->method === 'sendMessage' && $job->params['chat_id'] === '-100777');
        $text = $this->sentTexts()[0];
        $this->assertStringStartsWith('[KRITIK] Scheduler yurak urishi topilmadi', $text);
        $this->assertStringContainsString("Scheduler to'xtagan", $text);
        $this->assertStringContainsString('/monitoring?tab=alerts', $text);
        $this->assertNotNull($this->alerts()->sole()->telegram_opened_at);

        // Still there next minute: not announced again
        $this->travel(1)->minutes();
        $this->run_();
        $this->assertCount(1, $this->sentTexts());

        HealthReport::beat();
        $this->travel(1)->minutes();
        $this->run_();
        $this->assertCount(2, $this->sentTexts());
        $this->assertStringStartsWith('[HAL QILINDI] Scheduler yurak urishi topilmadi', $this->sentTexts()[1]);
        $this->assertStringContainsString("Muammo o'z-o'zidan tugadi", $this->sentTexts()[1]);
        $this->assertNotNull($this->alerts()->sole()->telegram_resolved_at);
    }

    public function test_the_ops_chat_falls_back_to_the_superadmin_chat_and_without_either_nothing_is_sent(): void
    {
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '', 'services.telegram.superadmin_chat_id' => '-100555']);
        Bus::fake();
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        $this->run_();
        Bus::assertDispatched(SendTelegramRequest::class, fn ($job) => $job->params['chat_id'] === '-100555');

        Bus::fake();
        config(['services.telegram.superadmin_chat_id' => '']);
        HealthReport::beat();
        $this->run_(); // resolves
        Cache::forget(HealthReport::HEARTBEAT_KEY);
        $this->run_(); // opens again
        Bus::assertNotDispatched(SendTelegramRequest::class);
        $this->assertCount(2, $this->alerts());
    }

    public function test_a_rule_with_telegram_off_stays_silent_and_so_does_its_resolve(): void
    {
        $this->telegramOn();
        $this->onlyRule(AlertKind::SCHEDULER_STALE, ['telegram' => false]);

        $this->run_();
        HealthReport::beat();
        $this->run_();

        Bus::assertNotDispatched(SendTelegramRequest::class);
        $this->assertSame('resolved', $this->alerts()->sole()->state);
    }

    public function test_a_flapping_problem_is_announced_once_per_cooldown_and_never_resolved_unannounced(): void
    {
        $this->telegramOn();
        config(['monitoring.telegram.cooldown_minutes' => 15]);
        $this->onlyRule(AlertKind::SCHEDULER_STALE);

        // Opens (announced), resolves (announced)
        $this->run_();
        HealthReport::beat();
        $this->run_();
        $this->assertCount(2, $this->sentTexts());

        // Comes back 3 minutes later: inside the cooldown, so neither the open nor its resolve is sent
        $this->travel(3)->minutes();
        Cache::forget(HealthReport::HEARTBEAT_KEY);
        $this->run_();
        HealthReport::beat();
        $this->run_();
        $this->assertCount(2, $this->sentTexts());
        $this->assertNull($this->alerts()->get(1)->telegram_opened_at);
        $this->assertNull($this->alerts()->get(1)->telegram_resolved_at);

        // After the cooldown it is announced again
        $this->travel(20)->minutes();
        Cache::forget(HealthReport::HEARTBEAT_KEY);
        $this->run_();
        $this->assertCount(3, $this->sentTexts());
    }

    public function test_different_subjects_are_not_held_back_by_each_others_cooldown(): void
    {
        $this->telegramOn();
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Ikki kassa');
        $this->travelTo(Carbon::parse('2026-11-03 05:00:00', 'UTC'));
        $this->hours($store, '00:00:00', '00:00:00');
        $this->terminal($store, ['name' => 'A', 'last_seen_at' => now()->subHour()]);
        $this->terminal($store, ['name' => 'B', 'last_seen_at' => now()->subHour()]);

        $this->run_();

        $this->assertCount(2, $this->sentTexts());
    }

    public function test_at_most_the_hourly_limit_goes_out_and_one_notice_says_the_rest_were_held_back(): void
    {
        $this->telegramOn();
        config(['monitoring.telegram.hourly_limit' => 3]);
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Ko\'p kassa');
        $this->hours($store, '00:00:00', '00:00:00');
        $this->travelTo(Carbon::parse('2026-11-03 05:00:00', 'UTC'));
        foreach (range(1, 6) as $i) {
            $this->terminal($store, ['name' => "Kassa {$i}", 'last_seen_at' => now()->subHour()]);
        }

        $this->run_();

        $texts = $this->sentTexts();
        $this->assertCount(4, $texts); // 3 alerts + the notice
        $this->assertCount(3, array_filter($texts, fn ($text) => str_starts_with($text, '[DIQQAT]')));
        $this->assertCount(1, array_filter($texts, fn ($text) => str_starts_with($text, 'Ogohlantirishlar ko\'p')));
        $this->assertSame(6, Alert::count());
        $this->assertSame(3, Alert::whereNotNull('telegram_opened_at')->count());

        // The notice is sent once per hour, not for every held-back alert
        $this->travel(2)->minutes();
        $this->run_();
        $this->assertCount(4, $this->sentTexts());

        // An hour later the limit is open again
        $this->travel(61)->minutes();
        $store->refresh();
        $this->terminal($store, ['name' => 'Yangi', 'last_seen_at' => now()->subHour()]);
        Alert::query()->update(['state' => 'resolved', 'resolved_at' => now()]);
        $this->run_();
        $this->assertGreaterThan(4, count($this->sentTexts()));
    }

    public function test_telegram_messages_carry_no_personal_data(): void
    {
        $this->telegramOn();
        $this->setUpLifecycleStore();
        $this->onlyRule(AlertKind::SETTLEMENT_FAILURES);
        $order = $this->makeOrder(\App\Enums\OrderStatus::DELIVERED);
        $order->forceFill(['settlement_failed_at' => now(), 'settlement_error' => 'Kartochka 8600 1234 5678 9012 ' . self::SECRET_PHONE])->save();

        $this->run_();

        $text = $this->sentTexts()[0];
        $this->assertStringNotContainsString('8600', $text);
        $this->assertStringNotContainsString('901234567', $text);
        $this->assertStringNotContainsString($this->customer->phone, $text);
    }

    // --- attention source -------------------------------------------------------------------------------------------

    public function test_open_alerts_are_an_attention_source_for_monitoring_viewers(): void
    {
        $sources = collect(app()->tagged(AttentionSource::TAG));
        $source = $sources->first(fn ($s) => $s instanceof OpenAlertsAttentionSource);
        $this->assertNotNull($source, 'the monitoring source is not registered');
        $this->assertSame(AdminPermission::MONITORING_VIEW, $source->permission());

        $this->assertSame(['count' => 0, 'items' => []], $source->collect(10));

        $warning = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'default', 'title' => 'Navbat to\'ldi']);
        $critical = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global', 'title' => 'Scheduler to\'xtadi']);
        $this->makeAlert(AlertKind::BACKUP_STALE, ['subject_key' => 'global', 'state' => 'acknowledged']);
        $this->makeAlert(AlertKind::FISCAL_FAILURES, ['subject_key' => 'global', 'state' => 'resolved', 'resolved_at' => now()]);

        $result = $source->collect(10);
        $this->assertSame(2, $result['count']);
        $this->assertSame(["Scheduler to'xtadi", "Navbat to'ldi"], array_map(fn ($item) => $item->title, $result['items']));

        $item = $result['items'][0];
        $this->assertSame('alert', $item->kind);
        $this->assertSame('critical', $item->severity);
        $this->assertSame("Scheduler to'xtagan", $item->subtitle);
        $this->assertSame("/monitoring?tab=alerts&alert={$critical->id}", $item->href);
        $this->assertSame($critical->first_seen_at->toIso8601String(), $item->since);

        $limited = $source->collect(1);
        $this->assertSame(2, $limited['count']);
        $this->assertCount(1, $limited['items']);
        $this->assertSame($warning->id > 0, true);
    }

    // --- housekeeping -----------------------------------------------------------------------------------------------

    public function test_resolved_alerts_are_pruned_after_ninety_days_and_open_ones_never(): void
    {
        $keepOpen = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'a', 'first_seen_at' => now()->subDays(200)]);
        $keepRecent = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'b', 'state' => 'resolved', 'resolved_at' => now()->subDays(10)]);
        $drop = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'c', 'state' => 'resolved', 'resolved_at' => now()->subDays(91)]);

        $this->artisan('model:prune', ['--model' => [Alert::class]])->assertSuccessful();

        $this->assertDatabaseHas('alerts', ['id' => $keepOpen->id]);
        $this->assertDatabaseHas('alerts', ['id' => $keepRecent->id]);
        $this->assertDatabaseMissing('alerts', ['id' => $drop->id]);
    }

    public function test_the_evaluator_creates_missing_rules_from_the_defaults(): void
    {
        AlertRule::query()->delete();

        $this->run_();

        $this->assertSame(count(AlertKind::cases()), AlertRule::count());
        $rule = AlertRule::query()->where('kind', 'queue_backlog')->sole();
        $this->assertSame([500, 10, null, true, true], [$rule->threshold, $rule->threshold_secondary, $rule->window_minutes, $rule->enabled, $rule->telegram]);
    }
}
