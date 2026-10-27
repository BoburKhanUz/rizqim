<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Jobs\SendTelegramRequest;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertKind;
use App\Support\HealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-15: the Monitoring endpoints of the admin API (/api/v1/admin/monitoring): who may read and who may act, the health
 * report (reusing HealthReport), failed jobs (redacted, retry, delete), alerts (list, acknowledge, resolve) and rules.
 */
class AdminMonitoringApiTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use MonitoringFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function readEndpoints(): array
    {
        return [
            'health' => ['GET', '/monitoring/health'],
            'failed jobs' => ['GET', '/monitoring/failed-jobs'],
            'failed job' => ['GET', '/monitoring/failed-jobs/00000000-0000-4000-8000-000000000000'],
            'alerts' => ['GET', '/monitoring/alerts'],
            'alert' => ['GET', '/monitoring/alerts/1'],
            'rules' => ['GET', '/monitoring/rules'],
        ];
    }

    // --- permissions -----------------------------------------------------------------------------------------------

    public function test_nobody_without_a_session_reads_monitoring(): void
    {
        foreach (self::readEndpoints() as [$method, $uri]) {
            $this->adminJson($method, $uri)->assertUnauthorized();
        }
    }

    public function test_roles_without_monitoring_view_are_refused_everywhere(): void
    {
        foreach ([PlatformRole::SUPPORT, PlatformRole::FINANCE, PlatformRole::SALES] as $role) {
            $token = $this->confirmedSession($this->makeMember($role));

            foreach (self::readEndpoints() as [$method, $uri]) {
                $this->adminJson($method, $uri, $token)->assertForbidden()->assertJsonPath('code', 'forbidden');
            }
            $this->adminJson('POST', '/monitoring/failed-jobs/00000000-0000-4000-8000-000000000000/retry', $token)->assertForbidden();
            $this->adminJson('POST', '/monitoring/alerts/1/acknowledge', $token)->assertForbidden();
        }
    }

    public function test_viewer_reads_but_cannot_act(): void
    {
        $uuid = $this->failedJob();
        $alert = $this->makeAlert();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));

        foreach (self::readEndpoints() as $name => [$method, $uri]) {
            if (in_array($name, ['failed job', 'alert'], true)) {
                continue;
            }
            $this->adminJson($method, $uri, $token)->assertOk();
        }
        $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $token)->assertOk();
        $this->adminJson('GET', "/monitoring/alerts/{$alert->id}", $token)->assertOk();

        // Acting needs monitoring.manage, even with a fresh step-up
        $this->adminJson('POST', "/monitoring/failed-jobs/{$uuid}/retry", $token)->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$uuid}", $token, ['reason' => 'Eskirgan'])->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token)->assertForbidden();
        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/resolve", $token)->assertForbidden();
        $this->adminJson('PUT', '/monitoring/rules/queue_backlog', $token, ['enabled' => false, 'reason' => 'Shovqin'])->assertForbidden();

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame('open', $alert->refresh()->state);
    }

    public function test_operator_acts_and_superadmin_too(): void
    {
        $uuid = $this->failedJob();
        $alert = $this->makeAlert();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);

        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token)->assertOk()->assertJsonPath('data.state', 'acknowledged');
        $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $token)->assertOk();
    }

    // --- health -----------------------------------------------------------------------------------------------------

    public function test_health_reuses_the_health_report_and_adds_queues_failed_jobs_backup_and_app(): void
    {
        HealthReport::beat();
        $this->failedJob();
        $this->failedJob(['failed_at' => now()->subDays(3)]);
        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHours(3)]);
        config(['monitoring.version' => '1.4.0', 'monitoring.commit' => 'abc1234', 'monitoring.sentry_project_url' => 'https://sentry.io/organizations/rizqim/projects/rizqim-backend/']);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $data = $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data');

        $this->assertSame('ok', $data['status']);
        $this->assertSame('ok', $data['database']['status']);
        $this->assertArrayHasKey('latency_ms', $data['database']);
        $this->assertSame('not_used', $data['redis']['status']);
        $this->assertSame('ok', $data['scheduler']['status']);
        $this->assertSame(180, $data['scheduler']['max_age_seconds']);
        $this->assertLessThanOrEqual(2, $data['scheduler']['last_heartbeat_age_seconds']);

        $names = array_column($data['queue']['queues'], 'name');
        $this->assertContains('default', $names);
        $this->assertContains('broadcasts', $names);
        $this->assertContains('reports', $names);
        $this->assertSame(['total' => 2, 'last_24h' => 1], array_intersect_key($data['queue']['failed_jobs'], ['total' => 1, 'last_24h' => 1]));
        $this->assertNotNull($data['queue']['failed_jobs']['latest_failed_at']);

        $this->assertSame('ok', $data['backup']['status']);
        $this->assertSame(3, $data['backup']['age_hours']);
        $this->assertSame('1.4.0', $data['app']['version']);
        $this->assertSame('abc1234', $data['app']['commit']);
        $this->assertFalse($data['sentry']['configured']);
        $this->assertSame('https://sentry.io/organizations/rizqim/projects/rizqim-backend/', $data['sentry']['project_url']);
        $this->assertSame(['open' => 0, 'acknowledged' => 0, 'critical_unresolved' => 0], $data['alerts']);
    }

    public function test_health_says_degraded_for_a_missing_scheduler_heartbeat_and_a_missing_backup(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));

        $data = $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data');

        $this->assertSame('degraded', $data['status']);
        $this->assertSame('missing', $data['scheduler']['status']);
        $this->assertSame('missing', $data['backup']['status']);
        $this->assertNull($data['backup']['last_backup_at']);
    }

    public function test_health_marks_an_old_backup_stale_by_the_rules_hours(): void
    {
        HealthReport::beat();
        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHours(30)]);
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));

        $data = $this->adminJson('GET', '/monitoring/health', $token)->json('data');
        $this->assertSame('stale', $data['backup']['status']);
        $this->assertSame(26, $data['backup']['max_age_hours']);
        $this->assertSame('degraded', $data['status']);

        $this->rule(AlertKind::BACKUP_STALE, ['threshold' => 48]);
        $this->assertSame('ok', $this->adminJson('GET', '/monitoring/health', $token)->json('data.backup.status'));
    }

    public function test_health_reports_reverb_reachability_and_never_breaks_when_it_is_down(): void
    {
        HealthReport::beat();
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));

        $this->assertSame('not_configured', $this->adminJson('GET', '/monitoring/health', $token)->json('data.reverb.status'));

        config(['broadcasting.default' => 'reverb', 'reverb.apps.apps.0.options' => ['host' => 'reverb.internal', 'port' => 8080, 'scheme' => 'http']]);
        $reverb = new \stdClass();
        $reverb->answer = fn () => Http::response('ok', 200);
        Http::fake(fn () => ($reverb->answer)());
        $this->assertSame('ok', $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data.reverb.status'));

        Cache::flush();
        $reverb->answer = fn () => Http::response('', 503);
        $this->assertSame('fail', $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data.reverb.status'));

        Cache::flush();
        $reverb->answer = fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused: reverb.internal');
        $response = $this->adminJson('GET', '/monitoring/health', $token)->assertOk();
        $this->assertSame('fail', $response->json('data.reverb.status'));
        $this->assertSame('degraded', $response->json('data.status'));
        $this->assertStringNotContainsString('reverb.internal', $response->getContent());
    }

    // --- failed jobs ------------------------------------------------------------------------------------------------

    public function test_failed_jobs_are_listed_newest_first_with_cursor_pages_and_filters(): void
    {
        $old = $this->failedJob(['failed_at' => now()->subDays(2), 'queue' => 'reports']);
        $mid = $this->failedJob();
        $new = $this->failedJob();
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $first = $this->adminJson('GET', '/monitoring/failed-jobs?per_page=2', $token)->assertOk();
        $this->assertSame([$new, $mid], array_column($first->json('data.data'), 'uuid'));
        $this->assertSame(3, $first->json('data.total'));
        $row = $first->json('data.data.0');
        $this->assertSame('SendTelegramRequest', $row['job']);
        $this->assertSame(SendTelegramRequest::class, $row['job_class']);
        $this->assertSame('default', $row['queue']);
        $this->assertStringStartsWith('Illuminate\Http\Client\ConnectionException: cURL error 28', $row['exception_line']);
        $this->assertNotNull($row['failed_at']);

        $second = $this->adminJson('GET', '/monitoring/failed-jobs?per_page=2&cursor=' . $first->json('data.next_cursor'), $token)->assertOk();
        $this->assertSame([$old], array_column($second->json('data.data'), 'uuid'));
        $this->assertNull($second->json('data.next_cursor'));

        $this->assertSame([$old], array_column($this->adminJson('GET', '/monitoring/failed-jobs?queue=reports', $token)->json('data.data'), 'uuid'));
        $this->assertCount(3, $this->adminJson('GET', '/monitoring/failed-jobs?q=' . urlencode('Jobs\SendTelegram'), $token)->json('data.data'));
        $this->assertCount(0, $this->adminJson('GET', '/monitoring/failed-jobs?q=NoSuchJob', $token)->json('data.data'));
        $this->assertCount(2, $this->adminJson('GET', '/monitoring/failed-jobs?from=' . now()->toDateString(), $token)->json('data.data'));
        $this->assertSame([$old], array_column($this->adminJson('GET', '/monitoring/failed-jobs?to=' . now()->subDay()->toDateString(), $token)->json('data.data'), 'uuid'));
    }

    public function test_the_list_and_the_card_never_show_secrets_or_phone_numbers(): void
    {
        $uuid = $this->failedJob();
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $list = $this->adminJson('GET', '/monitoring/failed-jobs', $token)->assertOk()->getContent();
        $card = $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $token)->assertOk();

        foreach ([$list, $card->getContent()] as $body) {
            $this->assertStringNotContainsString(self::SECRET_BOT_TOKEN, $body);
            $this->assertStringNotContainsString('AAHsecretbottokenvalue', $body);
            $this->assertStringNotContainsString(self::SECRET_API_KEY, $body);
            $this->assertStringNotContainsString('901234567', $body);
        }

        $data = $card->json('data');
        $this->assertStringContainsString('cURL error 28', $data['exception']);
        $this->assertStringContainsString('/bot[Filtered]/sendMessage', $data['exception']);
        $this->assertStringContainsString('#1 {main}', $data['exception']);
        $this->assertSame(5, $data['max_tries']);
        $this->assertSame(SendTelegramRequest::class, $data['payload']['displayName']);

        // The serialized job is shown as data: its method and params, secrets by key redacted
        $command = $data['payload']['command'];
        $this->assertSame('sendMessage', $command['method']);
        $this->assertSame('-100123', $command['params']['chat_id']);
        $this->assertSame('[redacted]', $command['params']['api_key']);
        $this->assertSame('[redacted]', $command['params']['reply_markup']['bot_token']);
        $this->assertStringStartsWith('Buyurtma tayyor, mijoz +99890', $command['params']['text']);
        $this->assertStringContainsString('*', $command['params']['text']);
    }

    public function test_an_encrypted_or_damaged_command_is_reported_as_unreadable_not_as_an_error(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'failed_at' => now(),
            'payload' => json_encode(['displayName' => 'App\\Jobs\\Secretive', 'data' => ['command' => 'eyJpdiI6Imh1c2gifQ==']]),
            'exception' => 'RuntimeException: boom',
        ]);
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $data = $this->adminJson('GET', "/monitoring/failed-jobs/{$uuid}", $token)->assertOk()->json('data');

        $this->assertSame('Secretive', $data['job']);
        $this->assertSame("[o'qib bo'lmadi]", $data['payload']['command']);
    }

    public function test_a_missing_failed_job_is_404(): void
    {
        $token = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $unknown = '11111111-1111-4111-8111-111111111111';

        $this->adminJson('GET', "/monitoring/failed-jobs/{$unknown}", $token)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->adminJson('POST', "/monitoring/failed-jobs/{$unknown}/retry", $token)->assertNotFound();
        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$unknown}", $token, ['reason' => 'Kerak emas'])->assertNotFound();
        $this->adminJson('GET', '/monitoring/failed-jobs/not-a-uuid', $token)->assertNotFound();
    }

    public function test_retry_pushes_the_job_back_removes_the_row_and_is_audited(): void
    {
        $uuid = $this->failedJob();
        Queue::fake();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);

        // No step-up needed for a retry
        $this->adminJson('POST', "/monitoring/failed-jobs/{$uuid}/retry", $token, ['reason' => 'Telegram tiklandi'])
            ->assertOk()->assertJsonPath('data.queue', 'default')->assertJsonPath('data.job', 'SendTelegramRequest');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $pushed = Queue::pushedRaw();
        $this->assertCount(1, $pushed);
        $this->assertSame('default', $pushed[0]['queue']);
        $this->assertSame(0, json_decode($pushed[0]['payload'], true)['attempts']);

        $entry = AuditLog::where('action', 'admin.monitoring.failed_job_retried')->sole();
        $this->assertSame($operator->id, $entry->user_id);
        $this->assertSame($uuid, $entry->payload_json['failed_job_uuid']);
        $this->assertSame(SendTelegramRequest::class, $entry->payload_json['job']);
        $this->assertSame('Telegram tiklandi', $entry->payload_json['reason']);
        $this->assertStringNotContainsString('AAHsecretbottokenvalue', json_encode($entry->payload_json));
        $this->assertStringNotContainsString('901234567', json_encode($entry->payload_json));

        // Retried twice: the second answer is 404 and writes nothing
        $this->adminJson('POST', "/monitoring/failed-jobs/{$uuid}/retry", $token)->assertNotFound();
        $this->assertSame(1, AuditLog::where('action', 'admin.monitoring.failed_job_retried')->count());
    }

    public function test_delete_needs_step_up_and_a_reason_and_is_audited_without_the_payload(): void
    {
        $uuid = $this->failedJob();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);

        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$uuid}", $token, ['reason' => 'Eskirgan'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);

        $this->stepUp($operator, $token);
        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$uuid}", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$uuid}", $token, ['reason' => 'ab'])->assertStatus(422);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);

        $this->adminJson('DELETE', "/monitoring/failed-jobs/{$uuid}", $token, ['reason' => 'Telegram chat o\'chirilgan, kerak emas'])->assertOk();
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        $entry = AuditLog::where('action', 'admin.monitoring.failed_job_deleted')->sole();
        $this->assertSame($operator->id, $entry->user_id);
        $this->assertSame("Telegram chat o'chirilgan, kerak emas", $entry->payload_json['reason']);
        $this->assertSame($uuid, $entry->payload_json['failed_job_uuid']);
        $this->assertSame('default', $entry->payload_json['queue']);
        $this->assertStringNotContainsString('AAHsecretbottokenvalue', json_encode($entry->payload_json));
        $this->assertStringNotContainsString(self::SECRET_API_KEY, json_encode($entry->payload_json));
    }

    // --- alerts -----------------------------------------------------------------------------------------------------

    public function test_alerts_list_open_first_then_critical_then_newest_and_filter(): void
    {
        $warningOld = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'default']);
        $criticalAck = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global', 'state' => 'acknowledged', 'acknowledged_by' => null, 'acknowledged_at' => now()]);
        $criticalOpen = $this->makeAlert(AlertKind::BACKUP_STALE, ['subject_key' => 'global']);
        $warningNew = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'reports']);
        $resolved = $this->makeAlert(AlertKind::FISCAL_FAILURES, ['subject_key' => 'global', 'state' => 'resolved', 'resolved_at' => now(), 'resolution' => 'auto']);
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $active = $this->adminJson('GET', '/monitoring/alerts', $token)->assertOk();
        $this->assertSame([$criticalOpen->id, $warningNew->id, $warningOld->id, $criticalAck->id], array_column($active->json('data.data'), 'id'));
        $this->assertSame(['open' => 3, 'acknowledged' => 1, 'resolved_7d' => 1], $active->json('data.counts'));
        $this->assertNull($active->json('data.next_cursor'));

        $first = $active->json('data.data.0');
        $this->assertSame('backup_stale', $first['kind']);
        $this->assertSame('Zaxira nusxa eskirgan', $first['kind_label']);
        $this->assertSame('critical', $first['severity']);
        $this->assertNull($first['acknowledged']);
        $this->assertNull($first['resolved']);

        $this->assertSame([$criticalAck->id], array_column($this->adminJson('GET', '/monitoring/alerts?state=acknowledged', $token)->json('data.data'), 'id'));
        $this->assertSame([$criticalOpen->id, $warningNew->id, $warningOld->id], array_column($this->adminJson('GET', '/monitoring/alerts?state=open', $token)->json('data.data'), 'id'));
        $this->assertSame([$warningNew->id, $warningOld->id], array_column($this->adminJson('GET', '/monitoring/alerts?state=open&kind=queue_backlog', $token)->json('data.data'), 'id'));
        $this->assertSame([$criticalOpen->id], array_column($this->adminJson('GET', '/monitoring/alerts?state=open&severity=critical', $token)->json('data.data'), 'id'));

        $history = $this->adminJson('GET', '/monitoring/alerts?state=resolved', $token)->assertOk();
        $this->assertSame([$resolved->id], array_column($history->json('data.data'), 'id'));
        $this->assertSame('auto', $history->json('data.data.0.resolved.resolution'));

        $all = $this->adminJson('GET', '/monitoring/alerts?state=all&per_page=2', $token)->assertOk();
        $this->assertSame([$resolved->id, $warningNew->id], array_column($all->json('data.data'), 'id'));
        $this->assertNotNull($all->json('data.next_cursor'));

        $this->adminJson('GET', '/monitoring/alerts?state=bogus', $token)->assertStatus(422);
        $this->adminJson('GET', '/monitoring/alerts?kind=bogus', $token)->assertStatus(422);
    }

    public function test_acknowledge_records_who_and_when_and_is_audited(): void
    {
        $alert = $this->makeAlert(AlertKind::SCHEDULER_STALE, ['subject_key' => 'global']);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);

        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token, ['note' => 'Serverga kirdim'])
            ->assertOk()
            ->assertJsonPath('data.state', 'acknowledged')
            ->assertJsonPath('data.acknowledged.by.id', $operator->id)
            ->assertJsonPath('data.acknowledged.note', 'Serverga kirdim');

        $alert->refresh();
        $this->assertSame($operator->id, $alert->acknowledged_by);
        $this->assertNotNull($alert->acknowledged_at);

        $entry = AuditLog::where('action', 'admin.monitoring.alert_acknowledged')->sole();
        $this->assertSame(['state' => 'open'], $entry->before);
        $this->assertSame(['state' => 'acknowledged'], $entry->after);
        $this->assertSame($alert->id, (int) $entry->model_id);
        $this->assertSame('Serverga kirdim', $entry->payload_json['note']);

        // Only an open alert can be acknowledged
        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token)->assertStatus(409)->assertJsonPath('code', 'alert_not_open');
        $this->adminJson('POST', '/monitoring/alerts/999999/acknowledge', $token)->assertNotFound();
        $this->adminJson('POST', "/monitoring/alerts/{$alert->id}/acknowledge", $token, ['note' => str_repeat('x', 501)])->assertStatus(422);
    }

    public function test_resolve_records_who_works_from_both_states_and_is_audited(): void
    {
        Bus::fake();
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777']);
        $open = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'default', 'telegram_opened_at' => now()]);
        $acknowledged = $this->makeAlert(AlertKind::QUEUE_BACKLOG, ['subject_key' => 'reports', 'state' => 'acknowledged']);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);

        $this->adminJson('POST', "/monitoring/alerts/{$open->id}/resolve", $token, ['note' => 'Navbat bo\'shadi'])
            ->assertOk()
            ->assertJsonPath('data.state', 'resolved')
            ->assertJsonPath('data.resolved.by.id', $operator->id)
            ->assertJsonPath('data.resolved.resolution', 'manual')
            ->assertJsonPath('data.resolved.note', "Navbat bo'shadi");
        $this->adminJson('POST', "/monitoring/alerts/{$acknowledged->id}/resolve", $token)->assertOk();

        $this->assertSame(2, AuditLog::where('action', 'admin.monitoring.alert_resolved')->count());
        $this->adminJson('POST', "/monitoring/alerts/{$open->id}/resolve", $token)->assertStatus(409)->assertJsonPath('code', 'alert_resolved');

        // The group hears it for the alert it was told about, and who closed it
        Bus::assertDispatchedTimes(SendTelegramRequest::class, 1);
        Bus::assertDispatched(SendTelegramRequest::class, fn ($job) => $job->params['chat_id'] === '-100777'
            && str_contains($job->params['text'], '[HAL QILINDI]') && str_contains($job->params['text'], $operator->name));
    }

    // --- rules ------------------------------------------------------------------------------------------------------

    public function test_rules_are_listed_with_defaults_fields_and_telegram_state(): void
    {
        $this->makeAlert(AlertKind::QUEUE_BACKLOG);
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $data = $this->adminJson('GET', '/monitoring/rules', $token)->assertOk()->json('data');

        $this->assertSame(AlertKind::values(), array_column($data['rules'], 'kind'));
        $this->assertFalse($data['telegram_configured']);
        $queue = collect($data['rules'])->firstWhere('kind', 'queue_backlog');
        $this->assertTrue($queue['enabled']);
        $this->assertTrue($queue['telegram']);
        $this->assertSame(500, $queue['threshold']);
        $this->assertSame(10, $queue['threshold_secondary']);
        $this->assertNull($queue['window_minutes']);
        $this->assertSame(1, $queue['unresolved_alerts']);
        $this->assertSame('warning', $queue['severity']);
        $this->assertNull($queue['fields']['window']);
        $this->assertSame('daqiqa', $queue['fields']['threshold_secondary']['unit']);
        $this->assertSame(['threshold' => 500, 'threshold_secondary' => 10, 'window_minutes' => null], $queue['defaults']);
        $this->assertNull($queue['updated_by']);

        config(['services.telegram.superadmin_chat_id' => '-100555']);
        $this->assertTrue($this->adminJson('GET', '/monitoring/rules', $token)->json('data.telegram_configured'));
    }

    public function test_changing_a_rule_needs_step_up_and_a_reason_and_is_audited(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);
        $body = ['threshold' => 800, 'telegram' => false, 'reason' => 'Ish soatida shovqin ko\'p'];

        $this->adminJson('PUT', '/monitoring/rules/queue_backlog', $token, $body)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($operator, $token);

        unset($body['reason']);
        $this->adminJson('PUT', '/monitoring/rules/queue_backlog', $token, $body)->assertStatus(422)->assertJsonValidationErrors('reason');
        $body['reason'] = 'Ish soatida shovqin ko\'p';

        $response = $this->adminJson('PUT', '/monitoring/rules/queue_backlog', $token, $body)->assertOk();
        $rule = collect($response->json('data.rules'))->firstWhere('kind', 'queue_backlog');
        $this->assertSame(800, $rule['threshold']);
        $this->assertFalse($rule['telegram']);
        $this->assertSame($operator->id, $rule['updated_by']['id']);

        $entry = AuditLog::where('action', 'admin.monitoring.rule_updated')->sole();
        $this->assertEquals(['threshold' => 500, 'telegram' => true], $entry->before);
        $this->assertEquals(['threshold' => 800, 'telegram' => false], $entry->after);
        $this->assertSame('queue_backlog', $entry->payload_json['kind']);
        $this->assertSame("Ish soatida shovqin ko'p", $entry->payload_json['reason']);

        // Nothing changed: nothing audited
        $this->adminJson('PUT', '/monitoring/rules/queue_backlog', $token, ['threshold' => 800, 'reason' => 'Bir xil qiymat'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.monitoring.rule_updated')->count());
    }

    public function test_rule_values_stay_in_the_kinds_range_and_unused_thresholds_are_refused(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($operator);
        $put = fn (string $kind, array $data) => $this->adminJson('PUT', "/monitoring/rules/{$kind}", $token, $data + ['reason' => 'Sinov uchun']);

        $put('queue_backlog', ['threshold' => 0])->assertStatus(422)->assertJsonValidationErrors('threshold');
        $put('queue_backlog', ['threshold' => 'ko\'p'])->assertStatus(422);
        $put('queue_backlog', ['threshold_secondary' => 5000])->assertStatus(422)->assertJsonValidationErrors('threshold_secondary');
        $put('queue_backlog', ['window_minutes' => 10])->assertStatus(422)->assertJsonValidationErrors('window_minutes');
        $put('backup_stale', ['threshold_secondary' => 10])->assertStatus(422);
        $put('scheduler_stale', ['threshold' => 30])->assertStatus(422);
        $put('failed_jobs_spike', ['threshold' => 5, 'window_minutes' => 30])->assertOk();
        $put('pos_device_offline', ['enabled' => false])->assertOk();
        $put('no_such_rule', ['enabled' => false])->assertNotFound();

        $this->assertFalse($this->rule(AlertKind::POS_DEVICE_OFFLINE)->enabled);
        $spike = $this->rule(AlertKind::FAILED_JOBS_SPIKE);
        $this->assertSame([5, 30], [$spike->threshold, $spike->window_minutes]);
    }

    public function test_every_monitoring_route_is_named_audited_and_guarded(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/monitoring'));
        $this->assertCount(11, $routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertStringStartsWith('admin.monitoring.', $route->getName());
            $this->assertContains('admin.audit', $middleware);
            $this->assertTrue(
                in_array('admin.can:monitoring.view', $middleware, true) || in_array('admin.can:monitoring.manage', $middleware, true),
                "{$route->uri()} has no monitoring permission"
            );

            // Deleting a failed job and changing a rule need a step-up
            if (in_array($route->getName(), ['admin.monitoring.failed_job_deleted', 'admin.monitoring.rule_updated'], true)) {
                $this->assertContains('admin.recent', $middleware);
            }
            if (array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
                $this->assertContains('admin.can:monitoring.manage', $middleware, "{$route->uri()} writes with only view");
            }
        }
    }
}
