<?php

namespace Tests\Feature\AdminMetrics;

use App\Enums\BillingPeriod;
use App\Enums\DeviceType;
use App\Enums\PlatformRole;
use App\Enums\AdminPermission;
use App\Models\Store;
use App\Models\StoreStatusLog;
use App\Models\StoreSubscription;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AdminFixtures;
use Tests\Feature\BillingV2\BillingV2Fixtures;

/**
 * P4-31: admin members and the v2 billing fixtures for the Ko'rsatkichlar and Excel export tests, plus a store set with a
 * known history (see storesWithHistory).
 */
trait MetricsFixtures
{
    use AdminFixtures;
    use BillingV2Fixtures;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function metricsJson(string $method, string $uri, string $token, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->json($method, '/api/v1/admin' . $uri, $data);
    }

    protected function memberToken(PlatformRole $role): string
    {
        return $this->confirmedSession($this->makeMember($role));
    }

    protected function without(PlatformRole $role, AdminPermission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            app(AdminPermissionService::class)->setOverride($role, $permission, false);
        }
    }

    protected function storeCreatedOn(string $name, string $createdAt, array $attributes = []): Store
    {
        return $this->v2Store($name, $attributes + ['created_at' => $this->at($createdAt), 'updated_at' => $this->at($createdAt)]);
    }

    /**
     * Today is 2026-10-28 (Asia/Tashkent). Five stores:
     *  - Alfa: Start (149 000) from Aug 10, moved up to Asosiy (249 000) on Oct 5;
     *  - Beta: Pro, yearly (4 490 000 / 12 = 374 166.67) from Jul 15, 2 kassa + 1 KDS included; a third kassa from Sep 3
     *    (+59 000);
     *  - Gamma: Asosiy (249 000) from Jul 2, cancelled at once on Sep 20 (still an active store);
     *  - Delta: in its trial until Nov 3 (counts for nothing yet);
     *  - Epsilon: Start (149 000) from Jul 5, suspended on Sep 15 (the subscription goes on).
     *
     * MRR at Jul 1 / Aug 1 / Sep 1 / Oct 1 / now: 0 / 772 166.67 / 921 166.67 / 731 166.67 / 831 166.67.
     *
     * @return array<string, Store>
     */
    protected function storesWithHistory(): array
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $this->withoutTrials();

        $alfa = $this->storeCreatedOn('Alfa', '2026-08-05 10:00');
        $beta = $this->storeCreatedOn('Beta', '2026-06-20 10:00');
        $gamma = $this->storeCreatedOn('Gamma', '2026-06-01 10:00');
        $delta = $this->storeCreatedOn('Delta', '2026-10-20 10:00');
        $epsilon = $this->storeCreatedOn('Epsilon', '2026-06-01 10:00');

        $this->billing()->assignPlan($alfa, $this->v2('start'), false, null, $this->at('2026-08-10 09:00'));
        $this->billing()->assignPlan($alfa, $this->v2('asosiy'), true, null, $this->at('2026-10-05 10:00'));

        $this->billing()->assignPlan($beta, $this->v2('pro'), false, null, $this->at('2026-07-15 09:00'), BillingPeriod::YEARLY);
        $this->terminal($beta, '2026-07-15 09:00', null, DeviceType::POS);
        $this->terminal($beta, '2026-07-15 09:00', null, DeviceType::POS);
        $this->terminal($beta, '2026-07-15 09:00', null, DeviceType::KDS);
        $this->terminal($beta, '2026-09-03 10:00', null, DeviceType::POS);

        $subscription = $this->billing()->assignPlan($gamma, $this->v2('asosiy'), false, null, $this->at('2026-07-02 09:00'));
        $this->billing()->cancel($subscription, true, null, $this->at('2026-09-20 10:00'));

        StoreSubscription::forceCreate([
            'store_id' => $delta->id,
            'tariff_plan_id' => $this->v2('start')->id,
            'status' => 'trialing',
            'billing_period' => 'monthly',
            'started_at' => $this->at('2026-10-20 10:00'),
            'trial_ends_at' => $this->at('2026-11-03 10:00'),
            'current_period_start' => $this->at('2026-10-20 10:00'),
            'current_period_end' => $this->at('2026-11-01 00:00'),
        ]);

        $this->billing()->assignPlan($epsilon, $this->v2('start'), false, null, $this->at('2026-07-05 09:00'));
        $epsilon->forceFill(['status' => 'suspended'])->save();
        // The Store model's hook logged the change just now; it happened on Sep 15
        StoreStatusLog::where('store_id', $epsilon->id)->update(['created_at' => $this->at('2026-09-15 10:00')]);

        return ['alfa' => $alfa, 'beta' => $beta, 'gamma' => $gamma, 'delta' => $delta, 'epsilon' => $epsilon];
    }

    /**
     * A Bugun aggregate row for one local day.
     */
    protected function statsDay(string $date, string $channel, int $orders, string $gmv, string $commission, string $subscription = '0'): void
    {
        DB::table('platform_daily_stats')->insert([
            'date' => $date, 'region_id' => 0, 'channel' => $channel, 'orders_count' => $orders, 'gmv' => $gmv,
            'commission' => $commission, 'subscription_revenue' => $subscription, 'computed_at' => now(),
        ]);
    }

    protected function statsComputed(string $date): void
    {
        DB::table('platform_stats_days')->updateOrInsert(['date' => $date], ['complete_through' => Carbon::parse($date)->addDay(), 'computed_at' => now()]);
    }
}
