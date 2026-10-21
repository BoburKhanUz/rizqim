<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P2-11: GET /staff/orders and GET /staff/orders/{uuid}: every store role sees its branch's orders (the owner every
 * branch), never another branch's or store's; a PIN session on a branch terminal sees that branch; contact details
 * only for staff who run orders; filters and cursor pagination.
 */
class StaffOrderApiTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $cook;
    private User $cashier;
    private User $waiter;
    private Order $orderA;
    private Order $orderB;
    private Order $foreignOrder;
    private Store $foreignStore;
    private Branch $foreignBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);

        $this->orderA = $this->orderWithItems(OrderStatus::ACCEPTED);
        $this->orderB = $this->orderWithItems(OrderStatus::ACCEPTED, ['branch_id' => $this->otherBranch->id]);

        $this->foreignStore = $this->makeStore('Foreign Kebab');
        $this->foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Main');
        $this->foreignOrder = $this->makeOrder(OrderStatus::ACCEPTED, ['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id]);
    }

    /**
     * @return list<string>
     */
    private function listed(User|string $who, array $query = []): array
    {
        $response = is_string($who)
            ? $this->bearerJson('GET', '/api/v1/staff/orders?' . http_build_query($query), $who)
            : $this->actingAs($who, 'sanctum')->getJson('/api/v1/staff/orders?' . http_build_query($query));

        $uuids = $response->assertOk()->json('data.*.uuid');
        sort($uuids);

        return $uuids;
    }

    /**
     * @param  list<Order>  $orders
     * @return list<string>
     */
    private function uuids(array $orders): array
    {
        $uuids = array_map(fn (Order $order) => $order->uuid, $orders);
        sort($uuids);

        return $uuids;
    }

    public function test_branch_staff_see_their_branch_and_the_owner_every_branch(): void
    {
        foreach ([$this->cook, $this->cashier, $this->waiter, $this->manager] as $staff) {
            $this->assertSame($this->uuids([$this->orderA]), $this->listed($staff), "{$staff->role->value} of branch A");
            $this->actingAs($staff, 'sanctum')->getJson("/api/v1/staff/orders/{$this->orderA->uuid}")->assertOk();
            $this->actingAs($staff, 'sanctum')->getJson("/api/v1/staff/orders/{$this->orderB->uuid}")->assertNotFound();
            $this->actingAs($staff, 'sanctum')->getJson("/api/v1/staff/orders/{$this->foreignOrder->uuid}")->assertNotFound();
        }

        $this->assertSame($this->uuids([$this->orderA, $this->orderB]), $this->listed($this->owner));
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/staff/orders/{$this->foreignOrder->uuid}")->assertNotFound();

        // Staff without a branch work store-wide
        $storeCook = $this->makeStaff(UserRole::COOK);
        $this->assertSame($this->uuids([$this->orderA, $this->orderB]), $this->listed($storeCook));

        // Another store's owner and cook see only their own store
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $this->foreignStore);
        $this->assertSame($this->uuids([$this->foreignOrder]), $this->listed($foreignOwner));
        $foreignCook = $this->makeStaff(UserRole::COOK, $this->foreignBranch, $this->foreignStore);
        $this->assertSame($this->uuids([$this->foreignOrder]), $this->listed($foreignCook));
        $this->actingAs($foreignCook, 'sanctum')->getJson("/api/v1/staff/orders/{$this->orderA->uuid}")->assertNotFound();
    }

    public function test_couriers_customers_and_storeless_accounts_are_refused(): void
    {
        foreach ([$this->courier, $this->customer] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/staff/orders')->assertForbidden();
            $this->actingAs($user, 'sanctum')->getJson("/api/v1/staff/orders/{$this->orderA->uuid}")->assertForbidden();
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertForbidden();
        }

        // Even a courier the owner granted orders.view keeps GET /orders only
        app(PermissionService::class)->setOverride($this->store, UserRole::COURIER, \App\Enums\Permission::ORDERS_VIEW, true);
        $this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/staff/orders')->assertForbidden();

        $this->bearerJson('GET', '/api/v1/staff/orders', null)->assertUnauthorized();

        $storeless = $this->makeStaff(UserRole::COOK, null, null, ['store_id' => null]);
        $this->actingAs($storeless, 'sanctum')->getJson('/api/v1/staff/orders')->assertForbidden();
    }

    public function test_a_store_override_taking_orders_view_away_closes_the_list(): void
    {
        app(PermissionService::class)->setOverride($this->store, UserRole::COOK, \App\Enums\Permission::ORDERS_VIEW, false);

        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/orders')->assertForbidden();
        $this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/staff/orders')->assertOk();
    }

    public function test_get_orders_keeps_its_meaning(): void
    {
        // A cook's GET /orders is still their own orders as a customer (none), never the branch's
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_the_detail_has_items_modifiers_weight_notes_and_times(): void
    {
        $data = $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/staff/orders/{$this->orderA->uuid}")
            ->assertOk()->json('data');

        $this->assertSame($this->orderA->uuid, $data['uuid']);
        $this->assertSame('accepted', $data['status']);
        $this->assertSame('pickup', $data['type']);
        $this->assertSame($this->branch->id, $data['branch_id']);
        $this->assertSame('Main Branch', $data['branch_name']);
        $this->assertSame(2, $data['items_total']);
        $this->assertSame(0, $data['items_ready']);

        [$burger, $osh] = $data['items'];
        $this->assertSame(2, $burger['quantity']);
        $this->assertSame('piece', $burger['unit']);
        $this->assertSame('2', $burger['quantity_label']);
        $this->assertSame([['name' => 'Achchiq sous', 'group_name' => 'Sous', 'quantity' => 1, 'price_delta' => '3000.00']], $burger['modifiers']);
        $this->assertSame('106000.00', $burger['total_price']);
        $this->assertSame('pending', $burger['kitchen_status']);
        $this->assertNull($burger['ready_at']);
        $this->assertSame(1.5, $osh['quantity']);
        $this->assertSame('kg', $osh['unit']);
        $this->assertSame('1.5 kg', $osh['quantity_label']);

        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['accepted_at']);
        $this->assertNull($data['ready_at']);
        $this->assertNull($data['closed_at']);
        $this->assertSame(['new', 'accepted'], array_column($data['status_history'], 'status'));

        // The cashier runs orders: contact details and the full note
        $this->assertSame($this->customer->phone, $data['customer']['phone']);
        $this->assertSame($this->customer->name, $data['customer']['name']);
        $this->assertStringContainsString('+998901234567', $data['notes']);
    }

    public function test_a_cook_gets_no_contact_details_or_address(): void
    {
        $delivery = $this->orderWithItems(OrderStatus::ACCEPTED, ['order_type' => 'delivery']);

        $cashierView = $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/staff/orders/{$delivery->uuid}")->json('data');
        $this->assertSame('Amir Temur 1', $cashierView['delivery']['address']);

        foreach (["/api/v1/staff/orders/{$delivery->uuid}", '/api/v1/staff/orders', '/api/v1/staff/kitchen/tickets'] as $uri) {
            $json = json_encode($this->actingAs($this->cook, 'sanctum')->getJson($uri)->assertOk()->json());

            $this->assertStringNotContainsString($this->customer->phone, $json, $uri);
            $this->assertStringNotContainsString($this->customer->name, $json, $uri);
            $this->assertStringNotContainsString('Amir Temur', $json, $uri);
            $this->assertStringNotContainsString('901234567', $json, $uri); // the phone typed into the note is masked
            $this->assertStringContainsString('Piyozsiz', $json, $uri);
        }

        $cookView = $this->actingAs($this->cook, 'sanctum')->getJson("/api/v1/staff/orders/{$delivery->uuid}")->json('data');
        $this->assertNull($cookView['customer']);
        $this->assertNull($cookView['delivery']);
        $this->assertNull($cookView['courier']);
    }

    public function test_filters_by_status_type_date_code_and_branch(): void
    {
        $delivered = $this->orderWithItems(OrderStatus::DELIVERED, ['order_type' => 'delivery']);
        $new = $this->orderWithItems(OrderStatus::NEW, ['short_code' => 'AQ1234']);
        $old = $this->orderWithItems(OrderStatus::ACCEPTED);
        $old->forceFill(['created_at' => Carbon::parse('2026-01-10 10:00:00', 'Asia/Tashkent')->utc()])->saveQuietly();

        $this->assertSame($this->uuids([$new]), $this->listed($this->manager, ['status' => 'new']));
        $this->assertSame($this->uuids([$this->orderA, $new, $old]), $this->listed($this->manager, ['scope' => 'active']));
        $this->assertSame($this->uuids([$delivered]), $this->listed($this->manager, ['scope' => 'history']));
        $this->assertSame([], $this->listed($this->manager, ['scope' => 'history', 'status' => 'new']));
        $this->assertSame($this->uuids([$this->orderA, $new]), $this->listed($this->manager, ['status' => 'new,accepted', 'from' => now('Asia/Tashkent')->toDateString()]));
        $this->assertSame($this->uuids([$delivered]), $this->listed($this->manager, ['type' => 'delivery']));
        $this->assertSame($this->uuids([$old]), $this->listed($this->manager, ['date' => '2026-01-10']));
        $this->assertSame($this->uuids([$new]), $this->listed($this->manager, ['q' => 'aq12']));
        $this->assertSame($this->uuids([$this->orderB]), $this->listed($this->owner, ['branch_id' => $this->otherBranch->id]));

        // A branch the caller does not work in: 403; another store's: 404
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/v1/staff/orders?branch_id={$this->otherBranch->id}")->assertForbidden();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/staff/orders?branch_id={$this->foreignBranch->id}")->assertNotFound();

        // Bad input is a 422, never a database error
        foreach ([
            ['status' => 'cooking'], ['type' => 'drone'], ['scope' => 'all'], ['date' => '2026-13-45'], ['from' => '2026-01-10', 'to' => '2026-09-10'],
            ['from' => '2026-02-01', 'to' => '2026-01-01'], ['q' => "A1'--"], ['branch_id' => '99999999999999999999'], ['cursor' => 'not-a-cursor'], ['per_page' => 0],
        ] as $query) {
            $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/staff/orders?' . http_build_query($query))->assertUnprocessable();
        }
    }

    public function test_cursor_pagination_walks_every_order_once(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->orderWithItems(OrderStatus::NEW);
        }
        $expected = Order::where('branch_id', $this->branch->id)->pluck('uuid')->sort()->values()->all();

        $seen = [];
        $cursor = null;
        do {
            $response = $this->actingAs($this->manager, 'sanctum')
                ->getJson('/api/v1/staff/orders?' . http_build_query(array_filter(['per_page' => 2, 'cursor' => $cursor])))
                ->assertOk();
            $this->assertLessThanOrEqual(2, count($response->json('data')));
            $this->assertSame(2, $response->json('meta.per_page'));
            $seen = [...$seen, ...$response->json('data.*.uuid')];
            $cursor = $response->json('meta.next_cursor');
        } while ($cursor !== null);

        sort($seen);
        $this->assertSame($expected, $seen);
        $this->assertSame(100, $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/staff/orders?per_page=5000')->json('meta.per_page'));
    }

    public function test_a_pin_session_on_a_branch_terminal_sees_that_branch(): void
    {
        // A store-wide cook signed in on branch A's kitchen display: branch A only
        $storeCook = $this->makeStaff(UserRole::COOK);
        $kds = $this->pairTerminal($this->owner, $this->branch, \App\Enums\DeviceType::KDS);
        $session = $this->pinSession($kds, $storeCook);

        $this->assertSame($this->uuids([$this->orderA]), $this->listed($session));
        $this->bearerJson('GET', "/api/v1/staff/orders/{$this->orderA->uuid}", $session)->assertOk();
        $this->bearerJson('GET', "/api/v1/staff/orders/{$this->orderB->uuid}", $session)->assertNotFound();
        $this->bearerJson('GET', "/api/v1/staff/orders?branch_id={$this->otherBranch->id}", $session)->assertForbidden();
        $this->assertSame([$this->orderA->uuid], $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->json('data.*.uuid'));

        // The branch's own cashier on the till
        $till = $this->pairTerminal($this->owner, $this->branch);
        $cashierSession = $this->pinSession($till, $this->cashier, '90517');
        $this->assertSame($this->uuids([$this->orderA]), $this->listed($cashierSession));

        // The owner on a terminal without a branch: the whole store
        $storeTerminal = $this->pairTerminal($this->owner);
        $ownerSession = $this->pinSession($storeTerminal, $this->owner, '274916');
        $this->assertSame($this->uuids([$this->orderA, $this->orderB]), $this->listed($ownerSession));

        // The terminal's own token is not a user: no order API
        $this->bearerJson('GET', '/api/v1/staff/orders', $kds['token'])->assertUnauthorized();
    }
}
