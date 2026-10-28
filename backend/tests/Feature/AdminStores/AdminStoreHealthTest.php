<?php

namespace Tests\Feature\AdminStores;

use App\Enums\DeviceType;
use App\Enums\PlatformRole;
use App\Enums\StoreHealthLevel;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\RefreshStoreHealthJob;
use App\Models\Device;
use App\Models\Store;
use App\Models\StoreHealthScore;
use App\Models\User;
use App\Services\Admin\Stores\StoreHealthCalculator;
use App\Services\Admin\Today\PlatformStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4-22: the health score: each signal, the levels, the hourly refresh, the "Ketish xavfi" attention rows.
 */
class AdminStoreHealthTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::forceCreate(['name' => 'Mijoz', 'role' => UserRole::CUSTOMER->value]);
    }

    private function oldStore(array $attributes = []): Store
    {
        $store = $this->makeStore($attributes);
        $store->forceFill(['created_at' => now()->subDays(90)])->saveQuietly();

        return $store->refresh();
    }

    private function orders(Store $store, int $count, int $daysAgo, string $status = 'delivered'): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'store_id' => $store->id,
                'user_id' => $this->customer->id,
                'status' => $status,
                'total_price' => 50000,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ];
        }
        DB::table('orders')->insert($rows);
    }

    private function ownerActiveDaysAgo(Store $store, int $days): void
    {
        $owner = $this->makeOwner($store);
        $owner->createToken('panel');
        DB::table('personal_access_tokens')->where('tokenable_id', $owner->id)->update(['created_at' => now()->subDays($days), 'last_used_at' => now()->subDays($days)]);
    }

    /**
     * @return array{level: StoreHealthLevel, score: int, reasons: list<array<string, mixed>>, metrics: array<string, mixed>}
     */
    private function health(Store $store): array
    {
        return app(StoreHealthCalculator::class)->compute([$store->refresh()], PlatformStats::now())[$store->id];
    }

    private function keys(array $health): array
    {
        return array_column($health['reasons'], 'level', 'key');
    }

    public function test_a_busy_store_with_an_active_owner_is_good(): void
    {
        $store = $this->oldStore();
        $this->orders($store, 12, 20);
        $this->orders($store, 12, 3);
        $this->ownerActiveDaysAgo($store, 1);
        Device::forceCreate(['store_id' => $store->id, 'type' => DeviceType::POS->value, 'name' => 'Kassa', 'last_seen_at' => now()->subMinutes(2)]);

        $health = $this->health($store);
        $this->assertSame(StoreHealthLevel::GOOD, $health['level']);
        $this->assertSame(100, $health['score']);
        $this->assertSame([], $health['reasons']);
        $this->assertSame(12, $health['metrics']['orders_recent']);
    }

    public function test_falling_orders_and_silence_mean_churn_risk_with_reasons(): void
    {
        $store = $this->oldStore();
        $this->orders($store, 20, 20);
        $this->orders($store, 8, 2);
        $this->orders($store, 5, 2, 'canceled'); // cancelled orders are not counted
        $this->ownerActiveDaysAgo($store, 1);

        $health = $this->health($store);
        $this->assertSame(StoreHealthLevel::CHURN_RISK, $health['level']);
        $this->assertSame(['orders_dropped' => 'churn_risk'], $this->keys($health));
        $this->assertStringContainsString('60% kamaydi', $health['reasons'][0]['label']);

        $quiet = $this->oldStore();
        $this->orders($quiet, 5, 40);
        $this->ownerActiveDaysAgo($quiet, 1);
        $health = $this->health($quiet);
        $this->assertSame('churn_risk', $this->keys($health)['no_orders']);
        $this->assertStringContainsString("40 kundan beri buyurtma yo'q", $health['reasons'][0]['label']);
    }

    public function test_devices_fiscal_debt_invoices_complaints_and_owner_activity(): void
    {
        $store = $this->oldStore();
        $this->orders($store, 3, 1);
        Device::forceCreate(['store_id' => $store->id, 'type' => DeviceType::POS->value, 'name' => 'Kassa 1', 'last_seen_at' => now()->subDays(5)]);
        Device::forceCreate(['store_id' => $store->id, 'type' => DeviceType::KDS->value, 'name' => 'Oshxona', 'last_seen_at' => now()]);
        $store->forceFill(['debt_balance' => '250000.00'])->save();
        $this->ownerActiveDaysAgo($store, 20);

        $health = $this->health($store);
        $keys = $this->keys($health);
        $this->assertSame('attention', $keys['devices_silent']);
        $this->assertSame('churn_risk', $keys['debt_over_limit']);
        $this->assertSame('attention', $keys['owner_inactive']);
        $this->assertSame(StoreHealthLevel::CHURN_RISK, $health['level']);
        // Churn reasons first
        $this->assertSame('debt_over_limit', $health['reasons'][0]['key']);
        $this->assertSame(100 - 10 - 25 - 10, $health['score']);

        // Unpaid subscription invoices are not commission debt: no debt reason for them
        $invoices = $this->oldStore();
        $this->orders($invoices, 3, 1);
        $this->ownerActiveDaysAgo($invoices, 1);
        $invoices->forceFill(['debt_balance' => '250000.00', 'subscription_debt' => '200000.00'])->save();
        $health = $this->health($invoices);
        $this->assertArrayNotHasKey('debt_over_limit', $this->keys($health));
        $this->assertSame('50000.00', $health['metrics']['commission_debt']);
        $this->assertSame('200000.00', $health['metrics']['subscription_debt']);
        // ...and the hourly refresh reads the column too (not the whole debt)
        app(StoreHealthCalculator::class)->refresh([$invoices->id]);
        $this->assertSame(StoreHealthLevel::GOOD, StoreHealthScore::findOrFail($invoices->id)->level);

        // Every terminal silent, an owner gone for 30+ days
        $other = $this->oldStore();
        $this->orders($other, 3, 1);
        Device::forceCreate(['store_id' => $other->id, 'type' => DeviceType::POS->value, 'name' => 'Kassa', 'last_seen_at' => now()->subDays(4)]);
        $this->makeOwner($other);
        $keys = $this->keys($this->health($other));
        $this->assertSame('churn_risk', $keys['devices_silent']);
        $this->assertSame('churn_risk', $keys['owner_inactive']);
    }

    public function test_a_store_being_onboarded_gets_no_trend_or_activity_reasons(): void
    {
        $store = $this->oldStore(['status' => StoreStatus::PENDING->value, 'is_active' => false]);
        $health = $this->health($store);
        $this->assertSame(StoreHealthLevel::GOOD, $health['level']);

        $suspended = $this->oldStore(['status' => StoreStatus::SUSPENDED->value]);
        $this->orders($suspended, 2, 1);
        $this->ownerActiveDaysAgo($suspended, 1);
        $this->assertSame(['status' => 'attention'], $this->keys($this->health($suspended)));
    }

    public function test_the_refresh_job_writes_scores_and_bugun_lists_live_stores_at_risk(): void
    {
        $risky = $this->oldStore();
        $risky->forceFill(['debt_balance' => '900000.00'])->save();
        $fine = $this->oldStore();
        $this->orders($fine, 3, 1);
        $this->ownerActiveDaysAgo($fine, 1);
        $gone = $this->oldStore();
        $gone->delete();

        (new RefreshStoreHealthJob())->handle(app(StoreHealthCalculator::class));

        $this->assertSame(StoreHealthLevel::CHURN_RISK, StoreHealthScore::findOrFail($risky->id)->level);
        $this->assertSame(StoreHealthLevel::GOOD, StoreHealthScore::findOrFail($fine->id)->level);
        $this->assertNull(StoreHealthScore::find($gone->id));

        $groups = collect($this->adminJson('GET', '/today/attention', $this->signIn($this->makeMember(PlatformRole::OPERATOR)))->json('data.groups'));
        $group = $groups->firstWhere('kind', 'store_churn_risk');
        $this->assertSame(1, $group['count']);
        $this->assertSame('/stores/' . $risky->id, $group['items'][0]['href']);
        $this->assertStringContainsString('kredit limitidan oshgan', $group['items'][0]['subtitle']);

        $this->artisan('admin:store-health', ['--store' => [$fine->id]])->assertSuccessful();
    }
}
