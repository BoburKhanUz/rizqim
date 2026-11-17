<?php

namespace Tests\Feature\Partitions;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave M3 (design section 5): the archive's status machine. Months older than online_months are detached (audit,
 * fiscal) or detached and dropped (notifications); a detached month is dropped only once its off-site copy is
 * verified and 3 days old, or with ARCHIVE_ALLOW_LOCAL_ONLY=1 from a local copy; never without sha256 and storage_uri.
 */
class PartitionArchiverTest extends TestCase
{
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    public function test_old_months_are_detached_kept_append_only_and_dropped_only_after_a_verified_copy(): void
    {
        $user = User::factory()->create();
        $this->travelTo('2026-03-10 10:00:00');
        $march = AuditLog::create(['action' => 'partitions.archive', 'user_id' => $user->id]);
        DB::table('notification_logs')->insert(['type' => 't', 'status' => 'sent', 'sent_at' => now()]);
        $this->travelTo('2026-11-17 22:40:00');
        $live = AuditLog::create(['action' => 'partitions.archive']);

        $this->artisan('db:partitions:archive')->assertSuccessful();

        // audit: 7 months online (2026-05 .. 2026-11), 2026-01 .. 2026-04 detached and catalogued
        $audit = DB::table('partition_archives')->where('table_name', 'audit_logs')->orderBy('partition_name')->get();
        $this->assertSame(['audit_logs_2026_01', 'audit_logs_2026_02', 'audit_logs_2026_03', 'audit_logs_2026_04'], $audit->pluck('partition_name')->all());
        $this->assertSame(['detached'], $audit->pluck('status')->unique()->values()->all());
        $marchRow = $audit->firstWhere('partition_name', 'audit_logs_2026_03');
        $this->assertSame(1, (int) $marchRow->rows);
        $this->assertSame($march->id, (int) $marchRow->min_id);
        $this->assertSame('2026-03-01 00:00:00', $marchRow->range_from);
        $this->assertNotContains('audit_logs_2026_03', array_column(app(PartitionManager::class)->partitions('audit_logs'), 'name'));
        $this->assertNull(AuditLog::find($march->id), 'the detached month left the live table');
        $this->assertNotNull(AuditLog::find($live->id));

        // The detached month: no foreign key left (a user delete is not blocked and does not touch it), still append-only
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_constraint WHERE conrelid = 'audit_logs_2026_03'::regclass AND contype = 'f'")->n);
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertSame($user->id, (int) DB::table('audit_logs_2026_03')->value('user_id'));
        $this->assertRaises(fn () => DB::statement("UPDATE audit_logs_2026_03 SET action = 'x'"));

        // notifications: 6 months online, older months detached and dropped (no archive), recorded as dropped
        $notifications = DB::table('partition_archives')->where('table_name', 'notification_logs')->orderBy('partition_name')->get();
        $this->assertSame(['notification_logs_2026_01', 'notification_logs_2026_02', 'notification_logs_2026_03', 'notification_logs_2026_04', 'notification_logs_2026_05'], $notifications->pluck('partition_name')->all());
        $this->assertSame(['dropped'], $notifications->pluck('status')->unique()->values()->all());
        $this->assertSame(1, (int) $notifications->firstWhere('partition_name', 'notification_logs_2026_03')->rows);
        $this->assertFalse(app(PartitionManager::class)->exists('notification_logs_2026_03'));

        // fiscal: 13 months online, nothing due yet
        $this->assertSame(0, DB::table('partition_archives')->where('table_name', 'fiscal_receipts')->count());

        // A second run: detached months stay (no verified copy)
        $this->artisan('db:partitions:archive')->assertSuccessful();
        $this->assertTrue(app(PartitionManager::class)->exists('audit_logs_2026_01'));

        // The script's results: uploaded (local only), verified recently, verified without sha256, verified long ago
        $this->catalogue('audit_logs_2026_01', ['status' => 'uploaded', 'sha256' => str_repeat('a', 64), 'storage_uri' => '/backups/archive/audit_logs/2026-01.dump', 'uploaded_at' => now()->subDays(10)]);
        $this->catalogue('audit_logs_2026_02', ['status' => 'verified', 'sha256' => str_repeat('b', 64), 'storage_uri' => 's3://bucket/archive/audit_logs/2026-02.dump', 'uploaded_at' => now()->subDay(), 'verified_at' => now()->subDay()]);
        $this->catalogue('audit_logs_2026_03', ['status' => 'verified', 'sha256' => null, 'storage_uri' => 's3://bucket/archive/audit_logs/2026-03.dump', 'verified_at' => now()->subDays(10)]);
        $this->catalogue('audit_logs_2026_04', ['status' => 'verified', 'sha256' => str_repeat('d', 64), 'storage_uri' => 's3://bucket/archive/audit_logs/2026-04.dump', 'verified_at' => now()->subDays(4)]);

        $this->artisan('db:partitions:archive')->assertSuccessful();
        $this->assertSame([
            'audit_logs_2026_01' => 'uploaded', 'audit_logs_2026_02' => 'verified', 'audit_logs_2026_03' => 'verified', 'audit_logs_2026_04' => 'dropped',
        ], DB::table('partition_archives')->where('table_name', 'audit_logs')->orderBy('partition_name')->pluck('status', 'partition_name')->all());
        $this->assertFalse(app(PartitionManager::class)->exists('audit_logs_2026_04'));
        $this->assertTrue(app(PartitionManager::class)->exists('audit_logs_2026_01'), 'a local-only copy is not enough');
        $this->assertTrue(app(PartitionManager::class)->exists('audit_logs_2026_03'), 'never dropped without a sha256');
        $this->assertNotNull(DB::table('partition_archives')->where('partition_name', 'audit_logs_2026_04')->value('dropped_at'));

        // Three days after its verification the recent one goes; the owner allows local-only copies: the uploaded one too
        $this->travel(3)->days();
        config(['partitions.allow_local_only' => true]);
        $this->artisan('db:partitions:archive')->assertSuccessful();
        $this->assertFalse(app(PartitionManager::class)->exists('audit_logs_2026_02'));
        $this->assertFalse(app(PartitionManager::class)->exists('audit_logs_2026_01'));
        $this->assertTrue(app(PartitionManager::class)->exists('audit_logs_2026_03'));

        // ensure never brings an archived month back
        $report = app(PartitionManager::class)->ensure('audit_logs');
        $this->assertNotContains('audit_logs_2026_01', $report['created']);
        $this->assertNotContains('audit_logs_2026_01', array_column(app(PartitionManager::class)->partitions('audit_logs'), 'name'));
    }

    public function test_fiscal_months_stay_in_the_database_while_the_copy_is_only_local(): void
    {
        $this->setUpLifecycleStore();
        $this->travelTo('2026-02-15 12:00:00');
        $receipt = $this->fiscalReceipt();
        $this->travelTo('2027-04-02 22:40:00');

        $this->artisan('db:partitions:archive')->assertSuccessful();
        $row = DB::table('partition_archives')->where('partition_name', 'fiscal_receipts_2026_02')->first();
        $this->assertSame('detached', $row->status);
        $this->assertSame(1, (int) $row->rows);
        // The store's foreign key (ON DELETE CASCADE) is gone from the detached month: a store delete cannot erase it
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_constraint WHERE conrelid = 'fiscal_receipts_2026_02'::regclass AND contype = 'f'")->n);
        $this->assertSame($receipt, (int) DB::table('fiscal_receipts_2026_02')->value('id'));

        // S3 not configured: the script leaves it uploaded; months later it is still in the database
        $this->catalogue('fiscal_receipts_2026_02', ['status' => 'uploaded', 'sha256' => str_repeat('e', 64), 'storage_uri' => '/backups/archive/fiscal_receipts/2026-02.dump', 'uploaded_at' => now()]);
        $this->travel(90)->days();
        $this->artisan('db:partitions:archive')->assertSuccessful();
        $this->assertSame('uploaded', DB::table('partition_archives')->where('partition_name', 'fiscal_receipts_2026_02')->value('status'));
        $this->assertTrue(app(PartitionManager::class)->exists('fiscal_receipts_2026_02'));
    }

    public function test_nothing_is_detached_while_an_online_conversion_keeps_a_copy(): void
    {
        DB::statement('CREATE TABLE audit_logs_m3_old (id bigint)');
        $this->travelTo('2026-11-17 22:40:00');
        $this->artisan('db:partitions:archive')->expectsOutputToContain('audit_logs: onlayn konversiya tugallanmagan')->assertSuccessful();
        $this->assertSame(0, DB::table('partition_archives')->where('table_name', 'audit_logs')->count());
        $this->assertTrue(app(PartitionManager::class)->exists('audit_logs_2026_01'));
    }

    public function test_the_catalogue_refuses_an_unknown_status(): void
    {
        $this->assertRaises(fn () => DB::table('partition_archives')->insert([
            'table_name' => 'audit_logs', 'partition_name' => 'audit_logs_2025_01', 'range_from' => '2025-01-01', 'range_to' => '2025-02-01', 'status' => 'archived',
        ]));
        $this->assertRaises(fn () => DB::table('restore_drills')->insert(['started_at' => now(), 'status' => 'running']));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function catalogue(string $partition, array $values): void
    {
        DB::table('partition_archives')->where('partition_name', $partition)->update($values);
    }

    private function fiscalReceipt(): int
    {
        return DB::table('fiscal_receipts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'status' => 'sent',
            'driver' => 'fake', 'idempotency_key' => 'k-' . Str::random(8), 'total' => 1000, 'lines' => '[]', 'payments' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertRaises(callable $statement): void
    {
        try {
            DB::transaction($statement);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('the statement must be refused');
    }
}
