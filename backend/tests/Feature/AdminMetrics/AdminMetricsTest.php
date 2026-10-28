<?php

namespace Tests\Feature\AdminMetrics;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Enums\DeviceType;
use App\Models\StoreApplication;
use App\Models\StoreSubscription;
use App\Services\Admin\Metrics\MetricsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * P4-31: Ko'rsatkichlar (GET /admin/metrics, /admin/metrics/funnel): MRR and its movement, churn, active stores, GMV and
 * commission from the Bugun aggregate, and the application conversion funnel, over a store set with a known history.
 */
class AdminMetricsTest extends TestCase
{
    use MetricsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mrr_counts_paying_stores_with_yearly_over_twelve_extra_terminals_and_no_trials(): void
    {
        $this->storesWithHistory();
        $token = $this->memberToken(PlatformRole::FINANCE);

        $data = $this->metricsJson('GET', '/metrics?months=4', $token)->assertOk()->json('data');
        $cards = $data['cards'];

        // Alfa 249 000 + Beta 374 166.67 + 59 000 (third kassa) + Epsilon 149 000; Gamma ended, Delta is in its trial
        $this->assertSame('831166.67', $cards['mrr']);
        $this->assertSame('9974000.04', $cards['arr']);
        $this->assertSame(3, $cards['paying_stores']);
        $this->assertSame(1, $cards['trialing_stores']);
        $this->assertSame('277055.56', $cards['arpa']);
        $this->assertSame(['direction' => 'up', 'amount' => '100000.00', 'percent' => '13.7'], $cards['mrr_change']);
        $this->assertSame('Asia/Tashkent', $data['timezone']);

        $this->assertSame(['2026-07', '2026-08', '2026-09', '2026-10'], array_column($data['series'], 'month'));
        $this->assertSame(['772166.67', '921166.67', '731166.67', '831166.67'], array_column($data['series'], 'mrr'));
        $this->assertSame([false, false, false, true], array_column($data['series'], 'partial'));
        $this->assertSame([3, 4, 3, 3], array_column($data['series'], 'paying_stores'));
    }

    public function test_the_movement_splits_new_expansion_contraction_and_churned_and_adds_up(): void
    {
        $this->storesWithHistory();
        $movement = $this->metricsJson('GET', '/metrics?months=4', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data.movement');

        [$july, $august, $september, $october] = $movement;
        $this->assertSame('0.00', $july['opening']);
        $this->assertSame(['mrr' => '772166.67', 'stores' => 3], $july['new']);
        $this->assertSame(['mrr' => '149000.00', 'stores' => 1], $august['new']);
        $this->assertSame(['mrr' => '59000.00', 'stores' => 1], $september['expansion']);
        $this->assertSame(['mrr' => '249000.00', 'stores' => 1], $september['churned']);
        $this->assertSame('-190000.00', $september['net_new']);
        $this->assertSame(['mrr' => '100000.00', 'stores' => 1], $october['expansion']);
        $this->assertTrue($october['partial']);

        foreach ($movement as $month) {
            $sum = (float) $month['opening'] + (float) $month['new']['mrr'] + (float) $month['expansion']['mrr'] - (float) $month['contraction']['mrr'] - (float) $month['churned']['mrr'];
            $this->assertEqualsWithDelta((float) $month['closing'], $sum, 0.005, "{$month['month']}: opening + movement = closing");
        }
    }

    public function test_churn_counts_stores_that_went_inactive_or_whose_subscription_ended(): void
    {
        $this->storesWithHistory();
        $data = $this->metricsJson('GET', '/metrics?months=4', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data');
        $churn = array_column($data['churn'], null, 'month');

        $this->assertSame(['active_at_start' => 3, 'active_at_end' => 3, 'churned' => 0, 'rate' => '0.0'], array_intersect_key($churn['2026-07'], array_flip(['active_at_start', 'active_at_end', 'churned', 'rate'])));
        // September: Epsilon was suspended (went inactive), Gamma's subscription ended while the store stayed active
        $this->assertSame(['active_at_start' => 4, 'active_at_end' => 3, 'churned' => 2, 'went_inactive' => 1, 'subscription_ended' => 1, 'rate' => '50.0', 'revenue_rate' => '27.0'], array_intersect_key($churn['2026-09'], array_flip(['active_at_start', 'active_at_end', 'churned', 'went_inactive', 'subscription_ended', 'rate', 'revenue_rate'])));
        $this->assertTrue($churn['2026-10']['partial']);
        $this->assertSame(4, $churn['2026-10']['active_at_end']);

        // The card shows the latest FINISHED month
        $this->assertSame(['50.0', '2026-09', 4], [$data['cards']['churn_rate'], $data['cards']['churn_month'], $data['cards']['active_stores']]);
        $this->assertSame([3, 4, 3, 4], array_column($data['series'], 'active_stores'));
    }

    public function test_gmv_and_commission_come_from_the_marketplace_channels_of_the_aggregate(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $this->statsDay('2026-10-03', 'telegram', 10, '1000000.00', '100000.00');
        $this->statsDay('2026-10-03', 'mobile', 5, '500000.00', '50000.00');
        $this->statsDay('2026-10-03', 'pos_hall', 40, '9000000.00', '0.00');
        $this->statsDay('2026-10-03', 'subscriptions', 0, '0', '0', '149000.00');
        $this->statsDay('2026-09-05', 'telegram', 2, '200000.00', '20000.00');
        $this->statsComputed('2026-10-03');

        $data = $this->metricsJson('GET', '/metrics?months=3', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data');
        $revenue = array_column($data['revenue'], null, 'month');

        $this->assertSame(['orders' => 15, 'gmv' => '1500000.00', 'commission' => '150000.00', 'subscription_revenue' => '149000.00'], array_intersect_key($revenue['2026-10'], array_flip(['orders', 'gmv', 'commission', 'subscription_revenue'])));
        $this->assertSame('200000.00', $revenue['2026-09']['gmv']);
        $this->assertSame(['1500000.00', '150000.00'], [$data['cards']['gmv_month'], $data['cards']['commission_month']]);
        // Only one of 28 days of October is computed: the numbers say they are not complete
        $this->assertFalse($revenue['2026-10']['complete']);
        $this->assertSame(27, $revenue['2026-10']['missing_days']);
        $this->assertTrue($revenue['2026-10']['partial']);
    }

    public function test_a_month_with_every_day_computed_is_complete(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        for ($day = 1; $day <= 30; $day++) {
            $this->statsComputed(sprintf('2026-09-%02d', $day));
        }

        $revenue = array_column($this->metricsJson('GET', '/metrics?months=3', $this->memberToken(PlatformRole::FINANCE))->json('data.revenue'), null, 'month');

        $this->assertTrue($revenue['2026-09']['complete']);
        $this->assertSame(0, $revenue['2026-09']['missing_days']);
        $this->assertFalse($revenue['2026-08']['complete']);
    }

    public function test_an_empty_platform_answers_zeros_not_errors(): void
    {
        $data = $this->metricsJson('GET', '/metrics', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data');

        $this->assertSame(12, $data['months']);
        $this->assertCount(12, $data['series']);
        $this->assertSame(['0.00', 0, null, null], [$data['cards']['mrr'], $data['cards']['paying_stores'], $data['cards']['arpa'], $data['cards']['churn_rate']]);
        $this->assertSame('same', $data['cards']['mrr_change']['direction']);
    }

    public function test_permissions_months_validation_and_the_cache(): void
    {
        $this->storesWithHistory();
        $this->without(PlatformRole::SUPPORT, AdminPermission::FINANCE_VIEW, AdminPermission::STORES_VIEW);
        $this->without(PlatformRole::OPERATOR, AdminPermission::FINANCE_VIEW, AdminPermission::STORES_VIEW);

        $this->metricsJson('GET', '/metrics', $this->memberToken(PlatformRole::SUPPORT))->assertForbidden();
        $this->metricsJson('GET', '/metrics/funnel', $this->memberToken(PlatformRole::OPERATOR))->assertForbidden();
        // stores.view alone, finance.view alone, and the read-only role may read
        $this->metricsJson('GET', '/metrics?months=3', $this->memberToken(PlatformRole::SALES))->assertOk();
        $finance = $this->memberToken(PlatformRole::FINANCE);
        $this->metricsJson('GET', '/metrics?months=3', $finance)->assertOk();
        $this->metricsJson('GET', '/metrics/funnel', $this->memberToken(PlatformRole::VIEWER))->assertOk();

        $this->metricsJson('GET', '/metrics?months=2', $finance)->assertStatus(422);
        $this->metricsJson('GET', '/metrics?months=25', $finance)->assertStatus(422);
        $this->metricsJson('GET', '/metrics/funnel?months=abc', $finance)->assertStatus(422);

        // Cached: a new paying store shows after the cache is dropped (or refresh=1)
        $before = $this->metricsJson('GET', '/metrics?months=4', $finance)->json('data.cards.mrr');
        $zeta = $this->storeCreatedOn('Zeta', '2026-10-01 10:00');
        $this->billing()->assignPlan($zeta, $this->v2('start'), false, null, $this->at('2026-10-10 09:00'));
        $this->assertSame($before, $this->metricsJson('GET', '/metrics?months=4', $finance)->json('data.cards.mrr'));
        $this->assertSame('980166.67', $this->metricsJson('GET', '/metrics?months=4&refresh=1', $finance)->json('data.cards.mrr'));
        $this->assertTrue(Cache::has(MetricsReport::cacheKey(4)));
    }

    public function test_the_funnel_reads_stage_moves_from_the_audit_log(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $moves = fn (StoreApplication $application, array $steps) => array_walk($steps, function (array $step) use ($application) {
            [$stage, $at] = $step;
            $log = new AuditLog();
            $log->forceFill([
                'action' => $stage === 'rejected' ? 'admin.stores.applications.rejected' : 'admin.stores.applications.stage_changed',
                'model_type' => $application->getMorphClass(), 'model_id' => $application->id,
                'after' => ['stage' => $stage], 'created_at' => $this->at($at),
            ])->save();
        });
        $application = function (string $name, string $createdAt, string $stage, array $extra = []) {
            return StoreApplication::forceCreate($extra + [
                'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => $name, 'format' => 'restaurant', 'owner_name' => 'Ega', 'phone' => '+998901234567',
                'source' => 'website', 'stage' => $stage, 'created_at' => $this->at($createdAt), 'updated_at' => $this->at($createdAt),
            ]);
        };

        $one = $application('Bir', '2026-08-01 10:00', 'active', ['activated_at' => $this->at('2026-08-15 10:00')]);
        $moves($one, [['review', '2026-08-03 10:00'], ['menu', '2026-08-06 10:00'], ['devices', '2026-08-07 10:00'], ['training', '2026-08-09 10:00'], ['trial', '2026-08-10 10:00'], ['active', '2026-08-15 10:00']]);
        $two = $application('Ikki', '2026-08-02 10:00', 'rejected', ['rejected_at' => $this->at('2026-08-05 10:00')]);
        $moves($two, [['review', '2026-08-04 10:00'], ['rejected', '2026-08-05 10:00']]);
        $application('Uch', '2026-09-01 10:00', 'application');
        $four = $application('Tort', '2026-09-10 10:00', 'menu');
        $moves($four, [['review', '2026-09-12 10:00'], ['menu', '2026-09-14 10:00']]);
        // Before the window (created in June; the window starts July 1)
        $application('Eski', '2026-06-10 10:00', 'active', ['activated_at' => $this->at('2026-06-20 10:00')]);

        $data = $this->metricsJson('GET', '/metrics/funnel?months=4', $this->memberToken(PlatformRole::SALES))->assertOk()->json('data');
        $stages = array_column($data['stages'], null, 'stage');

        $this->assertEquals([4, 1, 1, 2, 25.0, 14.0], [$data['applications'], $data['active'], $data['rejected'], $data['open'], (float) $data['conversion'], $data['median_days_to_active']]);
        $this->assertSame([4, 3, 2, 1, 1, 1, 1], array_column($data['stages'], 'reached'));
        $this->assertSame(['100.0', '75.0', '50.0', '25.0'], array_slice(array_column($data['stages'], 'share'), 0, 4));
        $this->assertEquals([2.0, 2.0, 1.0, 2.0, 1.0, 5.0], [$stages['application']['median_days'], $stages['review']['median_days'], $stages['menu']['median_days'], $stages['devices']['median_days'], $stages['training']['median_days'], $stages['trial']['median_days']]);
        $this->assertNull($stages['active']['median_days']);
        $this->assertSame(3, $stages['application']['samples']);
    }

    public function test_an_application_without_audit_entries_is_read_from_its_row(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        StoreApplication::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Seed', 'format' => 'restaurant', 'owner_name' => 'Ega', 'phone' => '+998901234567',
            'source' => 'admin', 'stage' => 'devices', 'stage_changed_at' => $this->at('2026-10-10 10:00'), 'created_at' => $this->at('2026-10-01 10:00'), 'updated_at' => $this->at('2026-10-01 10:00'),
        ]);

        $data = $this->metricsJson('GET', '/metrics/funnel?months=3', $this->memberToken(PlatformRole::SALES))->assertOk()->json('data');

        $this->assertSame([1, 1, 1, 1, 0], array_slice(array_column($data['stages'], 'reached'), 0, 5));
        // Created Oct 1, moved to "qurilmalar" on Oct 10: nine days in "ariza" (no step in between is logged)
        $this->assertEquals(9.0, $data['stages'][0]['median_days']);
    }

    public function test_a_legacy_plan_is_worth_its_base_fee_plus_the_terminals_beyond_the_included_ones(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $store = $this->storeCreatedOn('Eski tarif', '2026-08-01 10:00');
        $plan = $this->plan(['monthly_base_fee' => '100000', 'monthly_fee_per_terminal' => '50000', 'included_terminals' => 1]);
        StoreSubscription::forceCreate([
            'store_id' => $store->id, 'tariff_plan_id' => $plan->id, 'status' => 'active', 'billing_period' => 'monthly',
            'started_at' => $this->at('2026-09-01 00:00'), 'current_period_start' => $this->at('2026-10-01 00:00'), 'current_period_end' => $this->at('2026-11-01 00:00'),
        ]);
        foreach ([DeviceType::POS, DeviceType::POS, DeviceType::KDS] as $type) {
            $this->terminal($store, '2026-09-02 10:00', null, $type);
        }
        // A waiter's phone is not a terminal
        $this->terminal($store, '2026-09-02 10:00', null, DeviceType::PARTNER_MOBILE);

        $cards = $this->metricsJson('GET', '/metrics?months=3', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data.cards');

        // 100 000 + (3 terminals - 1 included) x 50 000
        $this->assertSame(['200000.00', 1, 0], [$cards['mrr'], $cards['paying_stores'], $cards['trialing_stores']]);
    }

    public function test_a_trial_that_has_ended_counts_and_one_that_has_not_does_not(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $ended = $this->storeCreatedOn('Sinov tugadi', '2026-10-01 10:00');
        $running = $this->storeCreatedOn('Sinovda', '2026-10-01 10:00');
        foreach ([[$ended, '2026-10-24 10:00'], [$running, '2026-11-02 10:00']] as [$store, $trialEnds]) {
            StoreSubscription::forceCreate([
                'store_id' => $store->id, 'tariff_plan_id' => $this->v2('start')->id, 'status' => 'active', 'billing_period' => 'monthly',
                'started_at' => $this->at('2026-10-10 10:00'), 'trial_ends_at' => $this->at($trialEnds),
                'current_period_start' => $this->at('2026-10-01 00:00'), 'current_period_end' => $this->at('2026-11-01 00:00'),
            ]);
        }

        $data = $this->metricsJson('GET', '/metrics?months=3', $this->memberToken(PlatformRole::FINANCE))->assertOk()->json('data');

        $this->assertSame(['149000.00', 1, 1], [$data['cards']['mrr'], $data['cards']['paying_stores'], $data['cards']['trialing_stores']]);
        // A trial turning into a paying subscription is "new" MRR of the month it began paying in
        $this->assertSame(['mrr' => '149000.00', 'stores' => 1], $data['movement'][2]['new']);
    }
}
