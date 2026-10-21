<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-33 (review A, unverified items, confirmed): a staff PIN session on a terminal paired to a branch works that
 * branch only on every endpoint it may reach, not just on the staff order screens: the older /orders/{uuid}/status,
 * /orders/{uuid}/assign-courier and GET /orders, the reports with their CSV and the Mini App analytics. A PIN session
 * whose terminal is gone (revoked, deleted) fails closed: it acts on no branch at all. And a delivery order is handed
 * over (`delivered`) by its courier or a dispatcher, not by a cashier or waiter.
 */
class TerminalSessionBranchScopeTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $storeWideManager;
    /** @var array{device: Device, token: string} */
    private array $terminal;
    private string $session;
    private Order $here;
    private Order $elsewhere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->storeWideManager = $this->makeStaff(UserRole::MANAGER);
        $this->terminal = $this->pairTerminal($this->owner, $this->branch, DeviceType::POS);
        $this->session = $this->pinSession($this->terminal, $this->storeWideManager);

        $this->here = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'delivery', 'total_price' => 10000]);
        $this->elsewhere = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'delivery', 'branch_id' => $this->otherBranch->id, 'total_price' => 20000]);
    }

    private function viaTerminal(string $method, string $uri, array $data = [])
    {
        return $this->bearerJson($method, $uri, $this->session, $data);
    }

    public function test_the_legacy_order_endpoints_keep_to_the_terminals_branch(): void
    {
        $this->viaTerminal('POST', "/api/v1/orders/{$this->elsewhere->uuid}/status", ['status' => 'accepted'])->assertNotFound();
        $this->viaTerminal('POST', "/api/v1/orders/{$this->elsewhere->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertNotFound();
        $this->assertSame(OrderStatus::NEW, $this->elsewhere->fresh()->status);

        $this->viaTerminal('POST', "/api/v1/orders/{$this->here->uuid}/status", ['status' => 'accepted'])->assertOk();
        $this->assertSame(OrderStatus::ACCEPTED, $this->here->fresh()->status);

        $this->assertSame([$this->here->uuid], array_column($this->viaTerminal('GET', '/api/v1/orders?scope=active')->assertOk()->json('data'), 'uuid'));
        $this->assertSame([$this->here->uuid], array_column($this->viaTerminal('GET', '/api/v1/orders')->assertOk()->json('data'), 'uuid'));

        // Couriers to pick from: the terminal's branch and the store-wide ones
        $otherCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);
        $ids = array_column($this->viaTerminal('GET', '/api/v1/couriers')->assertOk()->json('data'), 'id');
        $this->assertContains($this->courier->id, $ids);
        $this->assertNotContains($otherCourier->id, $ids);

        // The same manager signed in normally still works the whole store
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->storeWideManager, 'sanctum')->postJson("/api/v1/orders/{$this->elsewhere->uuid}/status", ['status' => 'accepted'])->assertOk();
    }

    public function test_reports_csv_and_analytics_keep_to_the_terminals_branch(): void
    {
        $today = now('Asia/Tashkent')->toDateString();

        $this->viaTerminal('GET', "/api/v1/owner/reports/sales?from={$today}&to={$today}")->assertOk()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.summary.orders_count', 1);
        $this->viaTerminal('GET', "/api/v1/owner/reports/sales?from={$today}&to={$today}&branch_id={$this->otherBranch->id}")->assertForbidden();

        $csv = $this->viaTerminal('GET', "/api/v1/owner/reports/export?report=orders&from={$today}&to={$today}")->assertOk()->streamedContent();
        $this->assertStringContainsString((string) $this->here->short_code, $csv);
        $this->assertStringNotContainsString((string) $this->elsewhere->short_code, $csv);

        $this->viaTerminal('GET', '/api/v1/owner/analytics?period=today')->assertOk()->assertJsonPath('data.summary.orders_count', 1);
    }

    public function test_a_pin_session_whose_terminal_is_gone_acts_nowhere(): void
    {
        // A revoked terminal's tokens are refused at sign-in already (AccessServiceProvider)
        Device::whereKey($this->terminal['device']->id)->update(['revoked_at' => now()]);
        $this->viaTerminal('GET', '/api/v1/staff/orders')->assertUnauthorized();
        Device::whereKey($this->terminal['device']->id)->update(['revoked_at' => null]);

        // A PIN session token that lost its terminal (no device on it, e.g. a row cleaned up by hand): the scope
        // itself fails closed instead of treating it as a store-wide sign-in
        \Laravel\Sanctum\PersonalAccessToken::where('name', 'staff-session')->update(['device_id' => null]);

        $this->viaTerminal('GET', '/api/v1/staff/orders')->assertForbidden();
        $this->viaTerminal('GET', "/api/v1/staff/orders/{$this->here->uuid}")->assertForbidden();
        $this->viaTerminal('POST', "/api/v1/orders/{$this->here->uuid}/status", ['status' => 'accepted'])->assertNotFound();
        $this->viaTerminal('GET', '/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->viaTerminal('GET', '/api/v1/owner/analytics?period=today')->assertForbidden();
        $this->viaTerminal('POST', '/api/v1/reports/export', ['timeframe' => 'today'])->assertForbidden();
        $this->assertSame(OrderStatus::NEW, $this->here->fresh()->status);
    }

    public function test_a_delivery_order_is_delivered_by_its_courier_or_a_dispatcher_only(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $order = $this->orderWithItems(OrderStatus::ON_THE_WAY, ['order_type' => 'delivery', 'courier_id' => $this->courier->id]);
        $move = fn (User $actor, Order $order) => $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => 'delivered']);

        $move($cashier, $order)->assertForbidden();
        $move($waiter, $order)->assertForbidden();
        $this->assertSame(OrderStatus::ON_THE_WAY, $order->fresh()->status);

        // The branch manager (orders.assign_courier) stands in for the courier
        $move($this->manager, $order)->assertOk()->assertJsonPath('data.status', 'delivered');

        // The courier's own button
        $other = $this->orderWithItems(OrderStatus::ON_THE_WAY, ['order_type' => 'delivery', 'courier_id' => $this->courier->id]);
        $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$other->uuid}/complete")->assertOk();
        $this->assertSame(OrderStatus::DELIVERED, $other->fresh()->status);

        // A pickup order is still handed over at the counter by a cashier
        $pickup = $this->orderWithItems(OrderStatus::READY, ['order_type' => 'pickup']);
        $move($cashier, $pickup)->assertOk()->assertJsonPath('data.status', 'delivered');
    }
}
