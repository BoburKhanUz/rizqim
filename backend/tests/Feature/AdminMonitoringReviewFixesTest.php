<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Jobs\SendTelegramRequest;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Device;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\Checks\PosDeviceOfflineCheck;
use App\Services\Admin\Monitoring\FailedJobs;
use App\Services\Admin\Today\Attention\OfflineTerminalsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave 1 review fixes for Monitoring (P4-15): round-the-clock stores at local midnight, truncated terminal lists,
 * one "open now" rule shared with Bugun, ack / resolve races, concurrent retries, failed job details by role.
 */
class AdminMonitoringReviewFixesTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
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

    private function telegramOn(): void
    {
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777']);
        Bus::fake();
    }

    private function sentTexts(): array
    {
        return Bus::dispatched(SendTelegramRequest::class)->map(fn ($job) => $job->params['text'])->values()->all();
    }

    private function openBranch(\App\Models\Store $store, ?string $open, ?string $close, string $name = 'Filial'): Branch
    {
        $branch = $this->makeBranch($store, $name);
        $branch->forceFill(['open_time' => $open, 'close_time' => $close, 'is_active' => true])->save();

        return $branch;
    }

    // --- 1. round-the-clock stores at local midnight --------------------------------------------------------------

    public function test_a_round_the_clock_store_keeps_its_alert_across_local_midnight(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Doim ochiq'); // 00:00 - 00:00
        // 23:50 Tashkent (18:50 UTC): silent for 30 minutes
        $this->travelTo(Carbon::parse('2026-11-03 18:50:00', 'UTC'));
        $this->terminal($store, ['last_seen_at' => now()->subMinutes(30)]);
        $this->assertSame(1, $this->run_()['opened']);

        // 00:01 Tashkent: the local day changed, the till is still silent (41 minutes), the alert must stay
        $this->travelTo(Carbon::parse('2026-11-03 19:01:00', 'UTC'));
        $result = $this->run_();
        $this->assertSame(['opened' => 0, 'updated' => 1, 'resolved' => 0], $result);
        $alert = Alert::query()->sole();
        $this->assertSame('open', $alert->state);
        $this->assertSame(41, $alert->details['offline_minutes']);
    }

    // --- 2. truncated candidate lists -----------------------------------------------------------------------------

    public function test_a_150_terminal_outage_keeps_every_existing_alert(): void
    {
        $rule = $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Katta tarmoq');
        $this->travelTo(Carbon::parse('2026-11-03 06:00:00', 'UTC'));

        $devices = [];
        foreach (range(1, 150) as $i) {
            $devices[] = $this->terminal($store, ['name' => "Kassa {$i}", 'last_seen_at' => now()->subMinutes(30 + $i)]);
            $this->makeAlert(AlertKind::POS_DEVICE_OFFLINE, ['subject_key' => end($devices)->uuid, 'store_id' => $store->id]);
        }

        $result = $this->run_();

        $this->assertSame(0, $result['resolved'], 'alerts of terminals that are still silent must stay open');
        $this->assertSame(150, Alert::query()->where('alert_rule_id', $rule->id)->unresolved()->count());
        $this->assertSame(150, $result['updated']);

        // One comes back: only that one resolves
        $devices[120]->forceFill(['last_seen_at' => now()])->save();
        $this->assertSame(1, $this->run_()['resolved']);
        $this->assertSame(149, Alert::query()->where('alert_rule_id', $rule->id)->unresolved()->count());
    }

    public function test_many_new_outages_open_a_capped_number_of_alerts_plus_one_aggregate(): void
    {
        $rule = $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Katta tarmoq');
        $this->travelTo(Carbon::parse('2026-11-03 06:00:00', 'UTC'));
        foreach (range(1, 150) as $i) {
            $this->terminal($store, ['name' => "Kassa {$i}", 'last_seen_at' => now()->subMinutes(30 + $i)]);
        }

        $this->assertSame(PosDeviceOfflineCheck::MAX_FINDINGS + 1, $this->run_()['opened']);
        $aggregate = Alert::query()->where('alert_rule_id', $rule->id)->where('subject_key', 'many')->sole();
        $this->assertSame(150, $aggregate->details['offline_total']);
        $this->assertSame(50, $aggregate->details['withheld']);
        $this->assertNull($aggregate->store_id);

        // A second run changes nothing and resolves nothing
        $this->assertSame(['opened' => 0, 'updated' => 101, 'resolved' => 0], $this->run_());

        // The outage ends: every alert (the aggregate too) resolves
        DB::table('devices')->update(['last_seen_at' => now()]);
        $this->assertSame(101, $this->run_()['resolved']);
    }

    public function test_closed_stores_do_not_crowd_the_open_ones_out_of_the_candidates(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $this->travelTo(Carbon::parse('2026-11-03 06:00:00', 'UTC')); // 11:00 Tashkent
        $closed = $this->makeStore('Kechki');
        DB::table('stores')->where('id', $closed->id)->update(['open_time' => '18:00:00', 'close_time' => '23:00:00']);
        $open = $this->makeStore('Ochiq');

        // 350 terminals that have been silent longer (older last_seen_at) but whose store is closed right now
        $rows = [];
        foreach (range(1, 350) as $i) {
            $rows[] = ['uuid' => (string) Str::uuid(), 'store_id' => $closed->id, 'type' => 'pos', 'name' => "Kech {$i}", 'last_seen_at' => now()->subHours(5), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('devices')->insert($rows);
        $real = $this->terminal($open, ['name' => 'Haqiqiy', 'last_seen_at' => now()->subMinutes(40)]);

        $this->assertSame(1, $this->run_()['opened']);
        $this->assertSame($real->uuid, Alert::query()->sole()->subject_key);
    }

    // --- 3. one "open now" rule for the alert and Bugun -----------------------------------------------------------

    public function test_the_alert_rule_and_bugun_agree_on_who_is_open_now(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $this->travelTo(Carbon::parse('2026-11-03 06:00:00', 'UTC')); // 11:00 Tashkent
        $store = $this->makeStore('Tarmoq');
        DB::table('stores')->where('id', $store->id)->update(['open_time' => '09:00:00', 'close_time' => '23:00:00']);
        $silent = now()->subMinutes(40);

        $cases = [
            'store hours, no branch' => $this->terminal($store, ['last_seen_at' => $silent]),
            'branch open now' => $this->terminal($store, ['last_seen_at' => $silent, 'branch_id' => $this->openBranch($store, '10:00:00', '20:00:00', 'A')->id]),
            // The branch's own hours win over the store's wide ones
            'branch closed now' => $this->terminal($store, ['last_seen_at' => $silent, 'branch_id' => $this->openBranch($store, '14:00:00', '20:00:00', 'B')->id]),
            'branch round the clock' => $this->terminal($store, ['last_seen_at' => $silent, 'branch_id' => $this->openBranch($store, '00:00:00', '00:00:00', 'C')->id]),
            // A branch without hours falls back to the store's
            'branch without hours' => $this->terminal($store, ['last_seen_at' => $silent, 'branch_id' => $this->openBranch($store, null, null, 'D')->id]),
        ];
        $inactive = $this->openBranch($store, null, null, 'E');
        $inactive->forceFill(['is_active' => false])->save();
        $cases['inactive branch'] = $this->terminal($store, ['last_seen_at' => $silent, 'branch_id' => $inactive->id]);
        $closedForToday = $this->makeStore('Bugun yopiq');
        $closedForToday->forceFill(['is_open' => false])->save();
        $cases['store switched closed'] = $this->terminal($closedForToday, ['last_seen_at' => $silent]);
        $suspended = $this->makeStore('Bloklangan');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $cases['store suspended'] = $this->terminal($suspended, ['last_seen_at' => $silent]);

        $expectedOpen = ['store hours, no branch', 'branch open now', 'branch round the clock', 'branch without hours'];

        $this->run_();
        $alerted = Alert::query()->pluck('subject_key')->all();
        $bugun = array_map(fn ($item) => Str::after($item->href, '/pos?device='), app(OfflineTerminalsSource::class)->collect(50)['items']);

        $uuids = fn (array $names) => array_values(array_map(fn (string $name) => $cases[$name]->uuid, $names));
        $this->assertEqualsCanonicalizing($uuids($expectedOpen), $alerted, 'alert rule');
        $this->assertEqualsCanonicalizing($uuids($expectedOpen), $bugun, 'Bugun');
    }

    public function test_a_store_switched_closed_resolves_its_alert(): void
    {
        $this->onlyRule(AlertKind::POS_DEVICE_OFFLINE, ['threshold' => 15]);
        $store = $this->makeStore('Yopiladigan');
        $this->terminal($store, ['last_seen_at' => now()->subMinutes(40)]);
        $this->assertSame(1, $this->run_()['opened']);

        $store->forceFill(['is_open' => false])->save();
        $this->assertSame(1, $this->run_()['resolved']);
    }

    // --- 4. ack / resolve races -----------------------------------------------------------------------------------

    private function staleOpenAlert(): array
    {
        $alert = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global', 'telegram_opened_at' => now()]);
        $stale = Alert::query()->find($alert->id); // what a request or an evaluator run holds in memory

        return [$alert, $stale];
    }

    public function test_acknowledging_an_alert_the_evaluator_just_resolved_is_refused_and_resurrects_nothing(): void
    {
        $this->telegramOn();
        $member = $this->makeMember(PlatformRole::OPERATOR);
        [$alert, $stale] = $this->staleOpenAlert();

        // The evaluator resolves it (auto); the problem comes back at once and opens a new alert for the subject
        $alert->forceFill(['state' => 'resolved', 'resolved_at' => now(), 'resolution' => 'auto'])->save();
        $fresh = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global']);

        $this->assertFalse(app(AlertEvaluator::class)->acknowledge($stale, $member, 'Ko\'rdim'));

        $this->assertSame('resolved', $alert->refresh()->state);
        $this->assertNull($alert->acknowledged_by);
        $this->assertSame('open', $fresh->refresh()->state);
        $this->assertSame(1, Alert::query()->unresolved()->count());
    }

    public function test_the_acknowledge_endpoint_answers_409_when_the_alert_is_resolved_under_it(): void
    {
        $alert = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global']);
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));

        $fired = false;
        Alert::retrieved(function (Alert $loaded) use (&$fired) {
            if (!$fired) {
                $fired = true;
                // The evaluator resolves the alert and the problem reopens as a new one, right after the controller read it
                DB::table('alerts')->where('id', $loaded->id)->update(['state' => 'resolved', 'resolved_at' => now(), 'resolution' => 'auto']);
                $this->makeAlertRaw($loaded);
            }
        });

        try {
            $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token, ['note' => 'Ketdim'])
                ->assertStatus(409)->assertJsonPath('code', 'alert_not_open');
        } finally {
            Alert::flushEventListeners();
        }

        $this->assertSame('resolved', DB::table('alerts')->where('id', $alert->id)->value('state'));
        $this->assertSame(0, AuditLog::where('action', 'admin.monitoring.alert_acknowledged')->count());
        $this->assertSame(1, Alert::query()->unresolved()->count());
    }

    private function makeAlertRaw(Alert $like): void
    {
        DB::table('alerts')->insert([
            'alert_rule_id' => $like->alert_rule_id, 'kind' => $like->kind, 'subject_key' => $like->subject_key, 'severity' => $like->severity,
            'state' => 'open', 'title' => 'Yangi', 'first_seen_at' => now(), 'last_seen_at' => now(), 'count' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_manual_resolve_and_an_auto_resolve_race_to_one_resolved_message(): void
    {
        $this->telegramOn();
        $member = $this->makeMember(PlatformRole::OPERATOR);
        [$alert, $stale] = $this->staleOpenAlert();
        $evaluator = app(AlertEvaluator::class);

        $this->assertTrue($evaluator->resolveByMember($alert, $member, 'Hal qildim'));
        // The evaluator still holds its copy from before and resolves it again
        $this->assertFalse($evaluator->resolveByMember($stale, $member, 'Yana'));

        $alert->refresh();
        $this->assertSame('manual', $alert->resolution);
        $this->assertSame('Hal qildim', $alert->resolve_note);
        $this->assertSame(1, count(array_filter($this->sentTexts(), fn ($text) => str_contains($text, '[HAL QILINDI]'))));
    }

    public function test_the_evaluator_does_not_touch_an_alert_a_member_resolved_meanwhile(): void
    {
        $this->telegramOn();
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $member = $this->makeMember(PlatformRole::OPERATOR);
        $this->run_();
        $alert = Alert::query()->sole();
        $alert->forceFill(['telegram_opened_at' => now()])->save();

        // A member resolves it right after the evaluator listed the unresolved alerts (before its update)
        $done = false;
        Alert::retrieved(function (Alert $loaded) use (&$done, $alert, $member) {
            if (!$done && $loaded->id === $alert->id) {
                $done = true;
                $this->travelBack();
                DB::table('alerts')->where('id', $loaded->id)->update(['state' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $member->id, 'resolution' => 'manual']);
            }
        });
        try {
            $this->run_();
        } finally {
            Alert::flushEventListeners();
        }

        $alert->refresh();
        $this->assertSame('resolved', $alert->state);
        $this->assertSame('manual', $alert->resolution);
        $this->assertSame(1, $alert->count, 'a resolved alert is not updated again');
    }

    public function test_auto_resolve_of_an_acknowledged_alert_still_works_and_is_announced_once(): void
    {
        $this->telegramOn();
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->run_();
        $alert = Alert::query()->sole();
        $alert->forceFill(['state' => 'acknowledged', 'acknowledged_at' => now(), 'telegram_opened_at' => now()])->save();

        \App\Support\HealthReport::beat();
        $this->assertSame(1, $this->run_()['resolved']);
        $this->assertSame(0, $this->run_()['resolved']);
        $this->assertSame(1, count(array_filter($this->sentTexts(), fn ($text) => str_contains($text, '[HAL QILINDI]'))));
    }

    // --- 5. retry race --------------------------------------------------------------------------------------------

    public function test_two_concurrent_retries_push_the_job_once(): void
    {
        $uuid = $this->failedJob();
        Queue::fake();
        $failedJobs = app(FailedJobs::class);

        // The second retry arrives while the first one is pushing (the row was read, the push not finished)
        $nested = null;
        $entered = false;
        Event::listen(JobRetryRequested::class, function () use ($failedJobs, $uuid, &$nested, &$entered) {
            if (!$entered) {
                $entered = true;
                $nested = $failedJobs->retry($uuid);
            }
        });

        $this->assertTrue($failedJobs->retry($uuid));
        $this->assertFalse($nested, 'the second retry finds the job claimed');
        $this->assertCount(1, Queue::pushedRaw());
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_a_retry_whose_push_fails_leaves_the_failed_job_in_place(): void
    {
        $uuid = $this->failedJob();
        Event::listen(JobRetryRequested::class, fn () => throw new \RuntimeException('queue down'));

        try {
            app(FailedJobs::class)->retry($uuid);
            $this->fail('the push failure should surface');
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_the_second_retry_request_gets_404_and_one_audit_entry(): void
    {
        $uuid = $this->failedJob();
        Queue::fake();
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));

        $this->adminJson('POST', "/monitoring/failed-jobs/{$uuid}/retry", $token)->assertOk();
        $this->adminJson('POST', "/monitoring/failed-jobs/{$uuid}/retry", $token)->assertNotFound();
        $this->assertCount(1, Queue::pushedRaw());
        $this->assertSame(1, AuditLog::where('action', 'admin.monitoring.failed_job_retried')->count());
    }

    // --- 7. failed job details by role ----------------------------------------------------------------------------

    public function test_a_read_only_member_sees_the_job_class_queue_and_the_first_exception_line_only(): void
    {
        $uuid = $this->failedJob();
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $response = $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $token)->assertOk();
        $data = $response->json('data');

        $this->assertSame('SendTelegramRequest', $data['job']);
        $this->assertSame('default', $data['queue']);
        $this->assertTrue($data['details_hidden']);
        $this->assertSame($data['exception_line'], $data['exception']);
        $this->assertStringNotContainsString('#0 /var/www', $data['exception']);
        $this->assertArrayNotHasKey('command', $data['payload']);
        $this->assertStringNotContainsString('Buyurtma tayyor', $response->getContent());
        $this->assertStringNotContainsString('chat_id', $response->getContent());
        $this->assertSame(SendTelegramRequest::class, $data['payload']['displayName']);
    }

    public function test_monitoring_managers_and_pii_viewers_see_the_whole_card(): void
    {
        $uuid = $this->failedJob();

        $operator = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $card = $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $operator)->assertOk()->json('data');
        $this->assertFalse($card['details_hidden']);
        $this->assertSame('sendMessage', $card['payload']['command']['method']);
        $this->assertStringContainsString('#1 {main}', $card['exception']);

        app(AdminPermissionService::class)->setOverride(PlatformRole::VIEWER, AdminPermission::PII_VIEW, true);
        $viewer = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $card = $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $viewer)->assertOk()->json('data');
        $this->assertFalse($card['details_hidden']);
        $this->assertSame('-100123', $card['payload']['command']['params']['chat_id']);
    }
}
