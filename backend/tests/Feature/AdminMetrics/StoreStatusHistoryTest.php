<?php

namespace Tests\Feature\AdminMetrics;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreStatusLog;
use App\Models\User;
use App\Services\Admin\Metrics\StoreActivity;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminStores\AdminStoresFixtures;
use Tests\TestCase;

/**
 * Review W3 C#3: every store status change is logged in one place (the Store model), so the Ko'rsatkichlar history
 * (StoreActivity) is right for changes LedgerService, AdjustmentService, NettingService and PlanMigration make too.
 */
class StoreStatusHistoryTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Tashkent');
    }

    public function test_ledger_driven_status_flip_is_logged_as_system(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $store = $this->makeStore(['created_at' => $this->at('2026-06-01 10:00')]);
        $customer = User::forceCreate(['name' => 'Mijoz', 'phone' => '+998901112288', 'role' => UserRole::CUSTOMER->value]);
        $order = Order::create(['store_id' => $store->id, 'user_id' => $customer->id, 'total_price' => 500000, 'status' => 'new', 'payment_method' => 'cash', 'payment_status' => 'pending']);
        $store->forceFill(['credit_limit' => 0])->save();

        app(LedgerService::class)->deductCommission($store, '250000', $order);

        $this->assertSame('payment_required', $store->fresh()->status->value);
        $log = StoreStatusLog::where('store_id', $store->id)->sole();
        $this->assertSame('active', $log->from_status);
        $this->assertSame('payment_required', $log->to_status);
        $this->assertNull($log->admin_id);
        $this->assertSame('system', $log->comment);
    }

    public function test_any_status_change_is_logged_once_and_unrelated_saves_are_not(): void
    {
        $store = $this->makeStore();
        $store->forceFill(['name' => 'Yangi nom'])->save();
        $this->assertSame(0, StoreStatusLog::where('store_id', $store->id)->count());

        $store->forceFill(['status' => 'finance_hold'])->save();
        $store->forceFill(['status' => 'finance_hold'])->save();
        $this->assertSame([['active', 'finance_hold']], StoreStatusLog::where('store_id', $store->id)->get(['from_status', 'to_status'])->map(fn ($l) => [$l->from_status, $l->to_status])->all());
    }

    public function test_admin_action_logs_the_member_and_reason_exactly_once(): void
    {
        $store = $this->makeStore();
        $member = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($member);

        $this->adminJson('POST', "/stores/{$store->id}/suspend", $token, ['reason' => 'Gigiyena tekshiruvi'])->assertOk();

        $log = StoreStatusLog::where('store_id', $store->id)->sole();
        $this->assertSame($member->id, (int) $log->admin_id);
        $this->assertSame('Gigiyena tekshiruvi', $log->comment);
    }

    public function test_churn_history_survives_a_ledger_flip_of_a_never_logged_store(): void
    {
        $this->travelTo($this->at('2026-10-28 12:00'));
        $store = $this->makeStore(['created_at' => $this->at('2026-06-01 10:00')]);
        $august = $this->at('2026-08-01 00:00');
        $this->assertArrayHasKey($store->id, app(StoreActivity::class)->at([$august])[0]);

        // As LedgerService::deductCommission does when the debt passes the credit limit today
        $store->forceFill(['status' => 'payment_required'])->save();

        $active = app(StoreActivity::class)->at([$august, $this->at('2026-10-28 13:00')]);
        $this->assertArrayHasKey($store->id, $active[0], 'the store was active in August');
        $this->assertArrayNotHasKey($store->id, $active[1]);
    }

    public function test_baseline_migration_logs_current_status_for_stores_without_a_history(): void
    {
        $logged = $this->makeStore(['created_at' => $this->at('2026-06-01 10:00')]);
        $logged->forceFill(['status' => 'suspended'])->save();
        $bare = $this->makeStore(['status' => 'payment_required', 'created_at' => $this->at('2026-07-01 10:00')]);
        $deleted = $this->makeStore(['created_at' => $this->at('2026-07-02 10:00')]);
        $deleted->delete();
        StoreStatusLog::query()->where('store_id', $bare->id)->delete();
        StoreStatusLog::query()->where('store_id', $deleted->id)->delete();

        $migration = require base_path('database/migrations/2026_11_07_100100_backfill_store_status_baseline_logs.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame(1, StoreStatusLog::where('store_id', $logged->id)->count());
        $rows = StoreStatusLog::where('store_id', $bare->id)->get();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->from_status);
        $this->assertSame('payment_required', $rows[0]->to_status);
        $this->assertTrue($rows[0]->created_at->equalTo($bare->fresh()->created_at), 'the baseline is dated at the store creation');
        $this->assertSame(1, StoreStatusLog::where('store_id', $deleted->id)->count());
    }
}
