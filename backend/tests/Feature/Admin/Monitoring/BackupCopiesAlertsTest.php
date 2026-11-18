<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Enums\PlatformRole;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminFixtures;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * M3: where the copies live. No off-site PITR copy (a standing warning), a drill without the app check (passed, not
 * fully verified; a fully verified one wanted about once a quarter), and the journal archive's storage (a key that
 * can delete, months archived only on this server).
 */
class BackupCopiesAlertsTest extends TestCase
{
    use AdminFixtures;
    use MonitoringFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['monitoring.pitr.enabled' => false]);
    }

    private function run_(): void
    {
        app(AlertEvaluator::class)->run();
    }

    private function subjects(AlertKind $kind): array
    {
        return Alert::query()->where('kind', $kind->value)->unresolved()->orderBy('subject_key')->pluck('subject_key')->all();
    }

    private function alert(AlertKind $kind, string $subject): Alert
    {
        return Alert::query()->where('kind', $kind->value)->where('subject_key', $subject)->unresolved()->sole();
    }

    private function oldRule(AlertKind $kind): void
    {
        $rule = $this->onlyRule($kind);
        DB::table('alert_rules')->where('id', $rule->id)->update(['created_at' => now()->subDays(400)]);
    }

    private function marker(string $key, array $meta): void
    {
        MonitoringMarker::query()->updateOrCreate(['key' => $key], ['recorded_at' => now(), 'meta' => $meta]);
    }

    private function pitr(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true, 'status' => 'ok', 'repo2' => 'on', 'offsite' => true, 'repo2_note' => null,
            'last_full_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_backup_at' => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z'),
        ], $overrides);
    }

    private function drill(Carbon $at, string $appSmoke, string $trigger): void
    {
        DB::table('restore_drills')->insert([
            'started_at' => $at, 'finished_at' => $at->copy()->addMinutes(4), 'status' => 'ok', 'repo' => '1', 'restore_seconds' => 200, 'data_loss_seconds' => 30,
            'checks' => json_encode(['probe' => 'ok', 'app_smoke' => $appSmoke, 'trigger' => $trigger]), 'created_at' => $at,
        ]);
    }

    // --- 1. no off-site copy ----------------------------------------------------------------------------------------

    public function test_no_offsite_copy_is_a_standing_warning_while_pitr_is_on(): void
    {
        $this->onlyRule(AlertKind::PITR_BACKUP_STALE);
        // PITR off (the sidecar says enabled: false, the backend has no PITR_ENABLED): quiet
        $this->marker('pitr', ['enabled' => false, 'offsite' => false]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE), 'PITR off: quiet');

        $this->marker('pitr', $this->pitr(['offsite' => false, 'repo2' => 'off', 'repo2_note' => "S3 sozlanmagan: faqat mahalliy nusxa"]));
        $this->run_();
        $alert = $this->alert(AlertKind::PITR_BACKUP_STALE, 'offsite_missing');
        $this->assertSame('warning', $alert->severity);
        $this->assertStringStartsWith("Zaxira faqat shu serverda: server diski buzilsa, baza ham, barcha zaxiralar ham yo'qoladi", $alert->title);
        $this->assertStringContainsString("17-bo'lim", $alert->title);

        // Still there days later: the same alert, not a new one each run
        $this->travel(3)->days();
        $this->marker('pitr', $this->pitr(['offsite' => false, 'repo2' => 'off']));
        $this->run_();
        $this->assertSame($alert->id, $this->alert(AlertKind::PITR_BACKUP_STALE, 'offsite_missing')->id);

        $this->marker('pitr', $this->pitr());
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE));
    }

    // --- 2. drills without the app check ----------------------------------------------------------------------------

    public function test_an_automatic_drill_satisfies_the_monthly_rule_but_a_fully_verified_one_is_wanted_each_quarter(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        $this->oldRule(AlertKind::RESTORE_DRILL_STALE);

        $this->drill(now()->subDays(2), 'skipped', 'auto');
        $this->run_();
        $this->assertSame(['app_check'], $this->subjects(AlertKind::RESTORE_DRILL_STALE));
        $alert = $this->alert(AlertKind::RESTORE_DRILL_STALE, 'app_check');
        $this->assertSame('warning', $alert->severity);
        $this->assertStringContainsString('scripts/restore-drill.sh', $alert->title);

        $this->drill(now()->subDay(), 'ok', 'manual');
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::RESTORE_DRILL_STALE));

        // Monthly automatic drills go on, the manual one ages past 100 days
        $this->travel(101)->days();
        $this->drill(now()->subDay(), 'skipped', 'auto');
        $this->run_();
        $this->assertSame(['app_check'], $this->subjects(AlertKind::RESTORE_DRILL_STALE));
        $this->assertStringContainsString('100 kundan beri', $this->alert(AlertKind::RESTORE_DRILL_STALE, 'app_check')->title);
    }

    public function test_the_page_shows_a_drill_without_the_app_check_as_passed_with_its_trigger(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        $this->drill(now()->subDays(40), 'ok', 'manual');
        $this->drill(now()->subDay(), 'skipped', 'auto');
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $drill = $this->adminJson('GET', '/monitoring/health', $token)->assertOk()->json('data.drill');

        $this->assertSame('ok', $drill['status']);
        $this->assertSame('auto', $drill['trigger']);
        $this->assertSame('skipped', $drill['app_check']);
        $this->assertNotNull($drill['last_app_check_ok_at']);
        $this->assertSame(100, $drill['app_check_max_age_days']);
    }

    // --- 3. the journal archive's storage ---------------------------------------------------------------------------

    public function test_an_archive_key_that_can_delete_and_months_kept_only_on_this_server_are_warnings(): void
    {
        $this->onlyRule(AlertKind::PARTITIONS);

        $this->marker('archive', ['offsite' => true, 'delete_allowed' => true, 'target' => 's3://rizqim/archive/']);
        $this->run_();
        $alert = $this->alert(AlertKind::PARTITIONS, 'archive_delete_allowed');
        $this->assertSame('warning', $alert->severity);
        $this->assertStringContainsString("O'chirish huquqi yo'q alohida kalit bering", $alert->title);

        $this->marker('archive', ['offsite' => true, 'delete_allowed' => false, 'target' => 's3://rizqim/archive/']);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PARTITIONS));

        // No S3: a month archived to a local file only
        $this->marker('archive', ['offsite' => false, 'delete_allowed' => null, 'target' => null]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PARTITIONS), 'nothing waiting to be archived');
        DB::table('partition_archives')->insert([
            'table_name' => 'audit_logs', 'partition_name' => 'audit_logs_2026_01', 'range_from' => '2026-01-01', 'range_to' => '2026-02-01',
            'status' => 'uploaded', 'storage_uri' => 'file:///backups/archive/audit_logs/2026-01.dump', 'detached_at' => now(), 'uploaded_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->run_();
        $this->assertStringStartsWith('1 ta jurnal oyi faqat shu serverda arxivlangan', $this->alert(AlertKind::PARTITIONS, 'archive_local_only')->title);

        DB::table('partition_archives')->update(['status' => 'verified']);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PARTITIONS));
    }
}
