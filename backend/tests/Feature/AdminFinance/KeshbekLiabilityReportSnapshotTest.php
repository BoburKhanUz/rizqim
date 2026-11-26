<?php

namespace Tests\Feature\AdminFinance;

use App\Models\Store;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Reports\KeshbekLiabilityReport;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review M1: the keshbek report is read from one snapshot. Its queries (the movements, then SUM(users.balance)) run in
 * one REPEATABLE READ READ ONLY transaction, so a balance change committed by another connection between two of them
 * — a referral bonus booked with its +5 000 — is either wholly in the report or wholly out of it, never in the live
 * total alone (a false difference of 5 000). The change is made by a second connection right after the report's first
 * query, deterministically (a query listener). Fixtures are committed (the second connection must see them) and
 * removed in tearDown; the difference is compared with the one before, so other committed rows do not matter.
 */
class KeshbekLiabilityReportSnapshotTest extends TestCase
{
    private const SIDE = 'keshbek_report_side';

    private ?Store $store = null;

    private ?User $referrer = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        config(['database.connections.' . self::SIDE => config('database.connections.' . DB::getDefaultConnection())]);
        $this->store = Store::forceCreate(['name' => 'Snapshot', 'slug' => 'snapshot-' . Str::random(6), 'status' => 'active', 'commission_rate' => 10]);
        $this->referrer = User::create(['name' => 'Referrer', 'phone' => '+99893' . random_int(1000000, 9999999)]);
    }

    protected function tearDown(): void
    {
        DB::disconnect(self::SIDE);
        if ($this->store !== null) {
            DB::table('store_transactions')->where('store_id', $this->store->id)->delete();
            Store::whereKey($this->store->id)->forceDelete();
        }
        if ($this->referrer !== null) {
            User::whereKey($this->referrer->id)->forceDelete();
        }

        parent::tearDown();
    }

    public function test_a_change_committed_between_the_reports_queries_makes_no_false_difference(): void
    {
        $report = app(KeshbekLiabilityReport::class);
        $period = now()->setTimezone('Asia/Tashkent')->format('Y-m');
        $before = $report->build($period);

        $changed = false;
        $isolation = null;
        DB::listen(function (QueryExecuted $query) use (&$changed, &$isolation) {
            if ($changed || $query->connectionName === self::SIDE || !str_contains($query->sql, '"store_transactions"')) {
                return;
            }
            $changed = true;
            $isolation = [DB::selectOne('SHOW transaction_isolation')->transaction_isolation, DB::selectOne('SHOW transaction_read_only')->transaction_read_only];
            // A referral bonus booked elsewhere, committed: the ledger row and the +5 000 together, as grantReferralReward
            DB::connection(self::SIDE)->transaction(function () {
                DB::connection(self::SIDE)->table('store_transactions')->insert([
                    'store_id' => $this->store->id, 'type' => 'referral_bonus', 'amount' => '5000.00', 'balance_before' => '0.00',
                    'balance_after' => '0.00', 'notes' => LedgerService::referralBonusNote(1), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::connection(self::SIDE)->table('users')->where('id', $this->referrer->id)->increment('balance', 5000);
            });
        });

        $during = $report->build($period);

        $this->assertTrue($changed, 'the other connection committed while the report was being read');
        $this->assertSame($before['reconciliation']['difference']->toString(), $during['reconciliation']['difference']->toString(), 'no false difference');
        $this->assertSame($before['reconciliation']['live']->toString(), $during['reconciliation']['live']->toString(), 'the change is wholly outside this snapshot');
        $this->assertSame(['repeatable read', 'on'], $isolation);
        $this->assertSame('5000.00', (string) $this->referrer->fresh()->balance);

        // The next report sees all of it, and still no difference
        $after = $report->build($period);
        $this->assertSame($before['issued']['count'] + 1, $after['issued']['count']);
        $this->assertSame($before['reconciliation']['live']->plus(5000)->toString(), $after['reconciliation']['live']->toString());
        $this->assertSame($before['reconciliation']['difference']->toString(), $after['reconciliation']['difference']->toString());
    }
}
