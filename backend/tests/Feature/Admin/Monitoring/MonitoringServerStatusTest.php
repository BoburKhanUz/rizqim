<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Domain\Fiscal\FiscalReceiptLock;
use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Jobs\EvaluateAlertsJob;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertKind;
use App\Support\HealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\AdminFixtures;
use Tests\Feature\MonitoringFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * M3: Monitoring → Holat's server blocks (postgres, pitr, drill, partitions, disk, redis.memory, queue.worker), the
 * dead-man ping after an evaluation and `db:restore-smoke`.
 */
class MonitoringServerStatusTest extends TestCase
{
    use AdminFixtures;
    use MonitoringFixtures;
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['monitoring.pitr.enabled' => false]);
    }

    private function health(): array
    {
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        return $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data');
    }

    private function marker(string $key, array $meta, ?\Illuminate\Support\Carbon $at = null): void
    {
        MonitoringMarker::query()->updateOrCreate(['key' => $key], ['recorded_at' => $at ?? now(), 'meta' => $meta]);
    }

    // --- the page ---------------------------------------------------------------------------------------------------

    public function test_a_fresh_install_shows_pitr_and_the_drill_as_off_and_the_disk_as_not_reported(): void
    {
        HealthReport::beat();
        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHour()]);

        $data = $this->health();

        $this->assertSame('ok', $data['status']);
        $this->assertSame('off', $data['pitr']['status']);
        $this->assertFalse($data['pitr']['enabled']);
        $this->assertSame('off', $data['drill']['status']);
        $this->assertSame('missing', $data['disk']['status']);
        $this->assertSame(35, $data['drill']['max_age_days']);
        $this->assertContains($data['postgres']['status'], ['ok', 'unknown']);
        $this->assertGreaterThan(0, $data['postgres']['connections']['max']);
        $this->assertSame('ok', $data['partitions']['status']);
        $this->assertGreaterThanOrEqual(2, $data['partitions']['months_ahead']);
        $this->assertSame(['audit_logs', 'fiscal_receipts', 'notification_logs', 'analytics_events'], array_column($data['partitions']['tables'], 'table'));
        $this->assertNull($data['redis']['memory'], 'Redis is not used in tests');
        $this->assertNull($data['queue']['worker']['last_heartbeat_age_seconds']);
        $this->assertSame(180, $data['queue']['worker']['max_age_seconds']);
    }

    public function test_a_working_pitr_shows_backups_wal_offsite_disk_and_the_last_drill(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        HealthReport::beat();
        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHour()]);
        $this->marker('pitr', [
            'enabled' => true, 'status' => 'ok', 'repo2' => 'on',
            'last_full_at' => now()->subDays(3)->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_backup_at' => now()->subHours(5)->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_backup_type' => 'diff', 'archived_at' => now()->subMinutes(2)->utc()->format('Y-m-d\TH:i:s\Z'),
            'archive_failing' => false, 'ready_wal' => 1,
        ]);
        $this->marker('disk', ['used_percent' => 42.5, 'fs_avail_bytes' => 57 * 1024 ** 3, 'fs_total_bytes' => 100 * 1024 ** 3, 'pg_wal_bytes' => 300 * 1024 ** 2, 'spool_bytes' => 0, 'db_bytes' => 12 * 1024 ** 3, 'repo_used_percent' => 42.5, 'repo_avail_bytes' => 57 * 1024 ** 3]);
        DB::table('restore_drills')->insert(['started_at' => now()->subDays(3), 'finished_at' => now()->subDays(3)->addMinutes(4), 'status' => 'ok', 'repo' => '1', 'reached_time' => now()->subDays(3), 'restore_seconds' => 252, 'data_loss_seconds' => 38, 'backup_label' => '20261114-213000F', 'checks' => '{}', 'created_at' => now()]);

        $data = $this->health();

        $pitr = $data['pitr'];
        $this->assertSame('ok', $pitr['status']);
        $this->assertSame('diff', $pitr['last_backup_type']);
        $this->assertEqualsWithDelta(5 * 3600, $pitr['last_backup_age_seconds'], 90);
        $this->assertSame('on', $pitr['offsite']);
        $this->assertNotNull($pitr['last_wal_archived_at']);
        $this->assertSame(26, $pitr['max_backup_age_hours']);
        $this->assertSame(['status' => 'ok', 'restore_seconds' => 252, 'data_loss_seconds' => 38, 'backup_label' => '20261114-213000F'], array_intersect_key($data['drill'], array_flip(['status', 'restore_seconds', 'data_loss_seconds', 'backup_label'])));
        $this->assertSame('ok', $data['disk']['status']);
        $this->assertSame(42.5, $data['disk']['used_percent']);
        $this->assertSame([80, 90], [$data['disk']['warn_percent'], $data['disk']['critical_percent']]);

        // No off-site copy: works, but the owner should know
        $this->marker('pitr', ['enabled' => true, 'status' => 'ok', 'repo2' => 'off', 'last_full_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'last_backup_at' => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->assertSame(['warning', 'off'], [$this->health()['pitr']['status'], $this->health()['pitr']['offsite']]);
    }

    public function test_a_silent_backup_service_shows_stale_and_degrades_the_overall_verdict(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        HealthReport::beat();
        MonitoringMarker::query()->create(['key' => 'backup', 'recorded_at' => now()->subHour()]);
        $this->marker('pitr', ['enabled' => true, 'status' => 'ok', 'repo2' => 'on', 'last_backup_at' => now()->subHours(2)->utc()->format('Y-m-d\TH:i:s\Z')], now()->subMinutes(20));
        $this->marker('disk', ['used_percent' => 40], now()->subMinutes(20));
        DB::table('restore_drills')->insert(['started_at' => now(), 'status' => 'failed', 'error' => 'probe token missing', 'created_at' => now()]);

        $data = $this->health();

        $this->assertSame('degraded', $data['status']);
        $this->assertSame('stale', $data['pitr']['status']);
        $this->assertEqualsWithDelta(1200, $data['pitr']['marker_age_seconds'], 90);
        $this->assertSame('stale', $data['disk']['status']);
        $this->assertSame('failed', $data['drill']['status']);
        $this->assertSame('probe token missing', $data['drill']['error']);
    }

    public function test_the_rules_list_carries_what_to_do(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $rules = collect($this->adminJson('GET', '/monitoring/rules', $token)->assertOk()->json('data.rules'));

        $this->assertCount(count(AlertKind::cases()), $rules);
        $disk = $rules->firstWhere('kind', 'disk_usage');
        $this->assertSame('critical', $disk['severity']);
        $this->assertSame(['threshold' => 80, 'threshold_secondary' => 90, 'window_minutes' => 15], $disk['defaults']);
        $this->assertSame('%', $disk['fields']['threshold']['unit']);
        $this->assertStringContainsString('diskni kengaytiring', $disk['action']);
    }

    // --- dead-man ping ----------------------------------------------------------------------------------------------

    public function test_a_finished_evaluation_pings_the_dead_man_switch_only_when_it_is_set(): void
    {
        AlertRule::ensureAll();
        AlertRule::query()->update(['enabled' => false]);
        Http::fake(['hc-ping.example/*' => Http::response('OK')]);

        (new EvaluateAlertsJob)->handle(app(\App\Services\Admin\Monitoring\AlertEvaluator::class));
        Http::assertNothingSent();

        config(['monitoring.healthchecks_ping_url' => 'https://hc-ping.example/0b1c2d3e-uuid']);
        (new EvaluateAlertsJob)->handle(app(\App\Services\Admin\Monitoring\AlertEvaluator::class));
        Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.example/0b1c2d3e-uuid' && $request->method() === 'GET');

        // An unreachable ping service never fails the evaluation
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        (new EvaluateAlertsJob)->handle(app(\App\Services\Admin\Monitoring\AlertEvaluator::class));
        $this->assertTrue(true);
    }

    // --- db:restore-smoke -------------------------------------------------------------------------------------------

    private function smoke(): array
    {
        $writes = 0;
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|truncate|create|alter|drop)\b/i', $query->sql)) {
                $writes++;
            }
        });
        $code = Artisan::call('db:restore-smoke');

        return [$code, Artisan::output(), $writes];
    }

    public function test_restore_smoke_passes_on_a_sound_copy_and_reads_the_newest_order_receipt_and_audit_row(): void
    {
        $this->setUpLifecycleStore();
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        FiscalReceiptLock::outsideIssuerForTests(fn () => \App\Models\FiscalReceipt::query()->forceCreate([
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'status' => 'issued',
            'driver' => 'sandbox', 'idempotency_key' => Str::random(20), 'total' => '50000.00', 'lines' => [], 'payments' => [],
        ]));
        AuditLog::query()->create(['action' => 'test.smoke', 'payload_json' => []]);

        [$code, $output, $writes] = $this->smoke();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString("OK: migratsiyalar: hammasi bajarilgan", $output);
        $this->assertStringContainsString("OK: buyurtma: oxirgisi #{$order->id} o'qildi", $output);
        $this->assertStringContainsString('OK: fiskal chek: oxirgisi', $output);
        $this->assertStringContainsString('OK: audit jurnali: oxirgi yozuv', $output);
        $this->assertStringContainsString("OK: jurnallarning oylik bo'limlari: audit_logs, fiscal_receipts, notification_logs, analytics_events joyida", $output);
        $this->assertStringContainsString('OK: audit himoyasi', $output);
        $this->assertStringNotContainsString('XATO', $output);
        $this->assertSame(0, $writes, 'the smoke check never writes');
    }

    // M4: does DDL itself: as the schema owner when the roles are split
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_restore_smoke_fails_with_one_line_per_broken_check(): void
    {
        $last = DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $last)->delete();
        DB::unprepared('DROP TRIGGER audit_logs_append_only ON audit_logs');

        [$code, $output] = $this->smoke();

        $this->assertSame(1, $code);
        $this->assertStringContainsString("XATO: 1 ta migratsiya bajarilmagan (masalan {$last})", $output);
        $this->assertStringContainsString("XATO: audit himoyasi: audit_logs_append_only triggeri yo'q", $output);
        $this->assertStringContainsString('OK: buyurtma', $output, 'the other checks still run');
        $this->assertStringContainsString("Natija: 2 ta tekshiruv o'tmadi.", $output);
    }
}
