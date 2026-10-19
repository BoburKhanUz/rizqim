<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * GET /api/v1/orders with ?scope=active (non-terminal, capped at 200) and ?scope=history (terminal, keyset
 * pagination by created_at desc, id desc). Without a scope the endpoint answers exactly as before.
 */
class OrderListingScopesTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const TERMINAL = ['delivered', 'canceled', 'rejected', 'refunded'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    // --- Role scoping -------------------------------------------------------------------------------------------

    public function test_every_role_sees_the_same_orders_per_scope_as_without_one(): void
    {
        $otherCustomer = $this->makeCustomer();
        $otherCourier = $this->makeStaff(UserRole::COURIER, $this->branch);
        $otherStore = $this->makeStore('Other Store');
        $otherStoreBranch = $this->makeBranch($otherStore, 'Foreign Branch');
        $storeWideManager = $this->makeStaff(UserRole::MANAGER);

        $o = [
            'branchNew' => $this->makeOrder(OrderStatus::NEW),
            'branchDelivered' => $this->makeOrder(OrderStatus::DELIVERED),
            'branchCourierActive' => $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id]),
            'branchCourierDone' => $this->makeOrder(OrderStatus::DELIVERED, ['courier_id' => $this->courier->id]),
            'otherCourierActive' => $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $otherCourier->id]),
            'otherBranchPreparing' => $this->makeOrder(OrderStatus::PREPARING, ['branch_id' => $this->otherBranch->id]),
            'otherBranchCanceled' => $this->makeOrder(OrderStatus::CANCELED, ['branch_id' => $this->otherBranch->id, 'user_id' => $otherCustomer->id]),
            'otherBranchCourierRefunded' => $this->makeOrder(OrderStatus::REFUNDED, ['branch_id' => $this->otherBranch->id, 'courier_id' => $this->courier->id]),
            'otherCustomerRejected' => $this->makeOrder(OrderStatus::REJECTED, ['user_id' => $otherCustomer->id]),
            'foreignActive' => $this->makeOrder(OrderStatus::ACCEPTED, ['store_id' => $otherStore->id, 'branch_id' => $otherStoreBranch->id]),
            'foreignDone' => $this->makeOrder(OrderStatus::DELIVERED, ['store_id' => $otherStore->id, 'branch_id' => $otherStoreBranch->id]),
        ];
        $active = ['branchNew', 'branchCourierActive', 'otherCourierActive', 'otherBranchPreparing', 'foreignActive'];

        $visible = [
            'customer' => [$this->customer, ['branchNew', 'branchDelivered', 'branchCourierActive', 'branchCourierDone', 'otherCourierActive', 'otherBranchPreparing', 'otherBranchCourierRefunded', 'foreignActive', 'foreignDone']],
            'owner' => [$this->owner, ['branchNew', 'branchDelivered', 'branchCourierActive', 'branchCourierDone', 'otherCourierActive', 'otherBranchPreparing', 'otherBranchCanceled', 'otherBranchCourierRefunded', 'otherCustomerRejected']],
            'branch manager' => [$this->manager, ['branchNew', 'branchDelivered', 'branchCourierActive', 'branchCourierDone', 'otherCourierActive', 'otherCustomerRejected']],
            'store-wide manager' => [$storeWideManager, ['branchNew', 'branchDelivered', 'branchCourierActive', 'branchCourierDone', 'otherCourierActive', 'otherBranchPreparing', 'otherBranchCanceled', 'otherBranchCourierRefunded', 'otherCustomerRejected']],
            'courier' => [$this->courier, ['branchCourierActive', 'branchCourierDone', 'otherBranchCourierRefunded']],
        ];

        foreach ($visible as $label => [$user, $keys]) {
            $uuids = fn (array $names) => collect($names)->map(fn ($name) => $o[$name]->uuid)->sort()->values()->all();
            $expectedActive = $uuids(array_intersect($keys, $active));
            $expectedHistory = $uuids(array_diff($keys, $active));

            $legacy = $this->list($user)->json('data');
            $this->assertSame($uuids($keys), collect($legacy)->pluck('uuid')->sort()->values()->all(), "{$label}: no scope");

            $activeItems = $this->list($user, ['scope' => 'active'])->assertJsonPath('meta.truncated', false)->json('data');
            $this->assertSame($expectedActive, collect($activeItems)->pluck('uuid')->sort()->values()->all(), "{$label}: active");

            $historyItems = $this->list($user, ['scope' => 'history'])->assertJsonPath('meta.next_cursor', null)->json('data');
            $this->assertSame($expectedHistory, collect($historyItems)->pluck('uuid')->sort()->values()->all(), "{$label}: history");

            // Same item shape (and content) in every scope
            $byUuid = collect($legacy)->keyBy('uuid');
            foreach (array_merge($activeItems, $historyItems) as $item) {
                $this->assertSame($byUuid[$item['uuid']], $item, "{$label}: item shape of {$item['uuid']}");
            }
        }
    }

    public function test_without_a_scope_the_response_is_unchanged(): void
    {
        $older = $this->makeOrder(OrderStatus::DELIVERED, ['created_at' => now()->subHour()]);
        $newer = $this->makeOrder(OrderStatus::NEW);

        $response = $this->list($this->owner)->assertOk();

        $this->assertSame(['success', 'message', 'data'], array_keys($response->json()));
        $this->assertSame('', $response->json('message'));
        $this->assertSame([$newer->uuid, $older->uuid], collect($response->json('data'))->pluck('uuid')->all());
        // An empty scope parameter is the same as none
        $this->assertSame($response->json(), $this->list($this->owner, ['scope' => ''])->json());
    }

    // --- scope=active -------------------------------------------------------------------------------------------

    public function test_active_orders_are_newest_first_with_same_second_ties_by_id(): void
    {
        $at = Carbon::parse('2026-10-19 10:00:00');
        $old = $this->makeOrder(OrderStatus::NEW, ['created_at' => $at->copy()->subMinute()]);
        $tieA = $this->makeOrder(OrderStatus::PREPARING, ['created_at' => $at]);
        $tieB = $this->makeOrder(OrderStatus::READY, ['created_at' => $at]);
        $this->makeOrder(OrderStatus::DELIVERED, ['created_at' => $at->copy()->addMinute()]);

        $response = $this->list($this->owner, ['scope' => 'active'])->assertOk();

        $this->assertSame(['success', 'message', 'data', 'meta'], array_keys($response->json()));
        $this->assertTrue($response->json('success'));
        $this->assertSame([$tieB->uuid, $tieA->uuid, $old->uuid], collect($response->json('data'))->pluck('uuid')->all());
        $this->assertSame(['truncated' => false], $response->json('meta'));
    }

    public function test_active_list_is_capped_at_200_newest_with_a_truncated_flag(): void
    {
        $ids = $this->insertOrders(201, fn (int $i) => ['status' => 'new', 'created_at' => Carbon::parse('2026-10-01')->addMinutes($i)]);
        $this->insertOrders(3, fn (int $i) => ['status' => 'delivered', 'created_at' => Carbon::parse('2026-10-10')]);

        $response = $this->list($this->owner, ['scope' => 'active'])->assertOk();

        $this->assertCount(200, $response->json('data'));
        $this->assertTrue($response->json('meta.truncated'));
        // The oldest one is what was left out
        $listedIds = Order::whereIn('uuid', collect($response->json('data'))->pluck('uuid'))->pluck('id')->all();
        $this->assertNotContains($ids[0], $listedIds);
        $this->assertSame(Order::whereIn('id', array_slice($ids, 1))->pluck('uuid')->sort()->values()->all(), collect($response->json('data'))->pluck('uuid')->sort()->values()->all());

        // Exactly 200: everything, not truncated
        Order::whereKey($ids[0])->forceDelete();
        $response = $this->list($this->owner, ['scope' => 'active'])->assertOk();
        $this->assertCount(200, $response->json('data'));
        $this->assertFalse($response->json('meta.truncated'));
    }

    // --- scope=history ------------------------------------------------------------------------------------------

    public function test_history_cursor_walks_every_terminal_order_once_including_same_second_ties(): void
    {
        $base = Carbon::parse('2026-10-01 12:00:00');
        // 5 orders per second over 9 seconds: page boundaries fall inside groups of equal created_at
        $this->insertOrders(45, fn (int $i) => [
            'status' => self::TERMINAL[$i % 4],
            'created_at' => $base->copy()->addSeconds(intdiv($i, 5)),
        ]);
        // Active orders interleaved with them are not history
        $this->insertOrders(6, fn (int $i) => ['status' => 'preparing', 'created_at' => $base->copy()->addSeconds($i)]);

        $expected = Order::whereIn('status', self::TERMINAL)->orderByDesc('created_at')->orderByDesc('id')->pluck('uuid')->all();
        $this->assertCount(45, $expected);

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $response = $this->list($this->owner, array_filter(['scope' => 'history', 'per_page' => 7, 'cursor' => $cursor]))->assertOk();
            $this->assertSame(['success', 'message', 'data', 'meta'], array_keys($response->json()));
            $this->assertSame(7, $response->json('meta.per_page'));
            $this->assertLessThanOrEqual(7, count($response->json('data')));
            $seen = array_merge($seen, collect($response->json('data'))->pluck('uuid')->all());
            $cursor = $response->json('meta.next_cursor');
            $pages++;
        } while ($cursor !== null && $pages < 20);

        $this->assertSame(7, $pages); // 6 full pages and 3 left over
        $this->assertSame($expected, $seen, 'in order, without duplicates or gaps');
    }

    public function test_history_cursor_is_not_shifted_by_orders_created_meanwhile(): void
    {
        $this->insertOrders(5, fn (int $i) => ['status' => 'delivered', 'created_at' => Carbon::parse('2026-10-01')->addMinutes($i)]);

        $first = $this->list($this->owner, ['scope' => 'history', 'per_page' => 2]);
        $this->makeOrder(OrderStatus::DELIVERED);
        $rest = $this->list($this->owner, ['scope' => 'history', 'per_page' => 10, 'cursor' => $first->json('meta.next_cursor')]);

        $expected = Order::whereDate('created_at', '2026-10-01')->orderByDesc('created_at')->pluck('uuid')->all();
        $this->assertSame($expected, array_merge(
            collect($first->json('data'))->pluck('uuid')->all(),
            collect($rest->json('data'))->pluck('uuid')->all(),
        ));
        $this->assertNull($rest->json('meta.next_cursor'));
    }

    public function test_history_page_size_defaults_to_20_and_is_capped_at_50(): void
    {
        $this->insertOrders(55, fn (int $i) => ['status' => 'canceled', 'created_at' => Carbon::parse('2026-10-01')->addMinutes($i)]);

        $default = $this->list($this->owner, ['scope' => 'history'])->assertOk();
        $this->assertCount(20, $default->json('data'));
        $this->assertSame(20, $default->json('meta.per_page'));
        $this->assertIsString($default->json('meta.next_cursor'));

        $capped = $this->list($this->owner, ['scope' => 'history', 'per_page' => 500])->assertOk();
        $this->assertCount(50, $capped->json('data'));
        $this->assertSame(50, $capped->json('meta.per_page'));

        $last = $this->list($this->owner, ['scope' => 'history', 'per_page' => 50, 'cursor' => $capped->json('meta.next_cursor')])->assertOk();
        $this->assertCount(5, $last->json('data'));
        $this->assertNull($last->json('meta.next_cursor'));

        // Exactly one full page: no cursor to an empty page
        $exact = $this->list($this->owner, ['scope' => 'history', 'per_page' => 55])->assertOk();
        $this->assertSame(50, $exact->json('meta.per_page'));
        $five = $this->list($this->owner, ['scope' => 'history', 'per_page' => 5, 'cursor' => $exact->json('meta.next_cursor')]);
        $this->assertCount(5, $five->json('data'));
        $this->assertNull($five->json('meta.next_cursor'));
    }

    // --- 422s -------------------------------------------------------------------------------------------------

    public function test_invalid_scope_cursor_or_page_size_is_a_422_in_uzbek(): void
    {
        $this->makeOrder(OrderStatus::DELIVERED);
        $encode = fn (mixed $payload) => rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        $cases = [
            'scope' => [['scope' => 'all'], ['scope' => ['active']], ['scope' => 'ACTIVE']],
            'cursor' => [
                ['scope' => 'history', 'cursor' => 'not a cursor!'],
                ['scope' => 'history', 'cursor' => 'Zm9v'], // "foo"
                ['scope' => 'history', 'cursor' => $encode(['t' => '2026-13-45 10:00:00', 'i' => 1])],
                ['scope' => 'history', 'cursor' => $encode(['t' => "2026-10-01 10:00:00'; --", 'i' => 1])],
                ['scope' => 'history', 'cursor' => $encode(['t' => '2026-10-01 10:00:00', 'i' => '1'])],
                ['scope' => 'history', 'cursor' => $encode(['t' => '2026-10-01 10:00:00', 'i' => 0])],
                ['scope' => 'history', 'cursor' => str_repeat('a', 500)],
                ['scope' => 'history', 'cursor' => ['x']],
            ],
            'per_page' => [['scope' => 'history', 'per_page' => 0], ['scope' => 'history', 'per_page' => 'abc'], ['scope' => 'history', 'per_page' => -5], ['scope' => 'history', 'per_page' => '2.5']],
        ];

        foreach ($cases as $field => $queries) {
            foreach ($queries as $query) {
                $response = $this->list($this->owner, $query)->assertStatus(422);
                $this->assertFalse($response->json('success'), json_encode($query));
                $this->assertNotEmpty($response->json("errors.{$field}"), json_encode($query));
                $this->assertMatchesRegularExpression("/noto'g'ri|bo'lishi kerak/i", $response->json('message'));
            }
        }

        // A well-formed cursor (also one with microseconds) is accepted
        $this->list($this->owner, ['scope' => 'history', 'cursor' => $encode(['t' => '2026-10-01 10:00:00', 'i' => 5])])->assertOk();
        $this->list($this->owner, ['scope' => 'history', 'cursor' => $encode(['t' => '2026-10-01 10:00:00.123456', 'i' => 5])])->assertOk();
    }

    public function test_guests_cannot_list(): void
    {
        $this->getJson('/api/v1/orders?scope=active')->assertUnauthorized();
        $this->getJson('/api/v1/orders?scope=history')->assertUnauthorized();
    }

    // --- Query count ------------------------------------------------------------------------------------------

    public function test_listing_runs_the_same_number_of_queries_however_many_orders_there_are(): void
    {
        $storeWideManager = $this->makeStaff(UserRole::MANAGER);
        $users = ['owner' => $this->owner, 'branch manager' => $this->manager, 'store-wide manager' => $storeWideManager, 'courier' => $this->courier, 'customer' => $this->customer];
        $scopes = [[], ['scope' => 'active'], ['scope' => 'history']];

        $this->makeRichOrders(2);
        $few = $this->countQueries($users, $scopes);

        $this->makeRichOrders(12);
        $many = $this->countQueries($users, $scopes);

        $this->assertSame($few, $many);
        // Orders, items, variants, products, categories, customers, couriers, managers, branches, status histories
        $this->assertLessThanOrEqual(10, max($many));
    }

    // --- Indexes ----------------------------------------------------------------------------------------------

    public function test_listing_indexes_exist(): void
    {
        $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename IN ('orders', 'order_items')"))->pluck('indexname');

        foreach ([
            'orders_store_id_status_created_at_index',
            'orders_branch_id_status_created_at_index',
            'orders_courier_id_status_index',
            'orders_store_id_created_at_id_index',
            'orders_user_id_created_at_id_index',
            'order_items_order_id_index',
        ] as $name) {
            $this->assertContains($name, $indexes);
        }
    }

    // --- Helpers ----------------------------------------------------------------------------------------------

    private function list(User $user, array $query = []): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->getJson('/api/v1/orders' . ($query ? '?' . http_build_query($query) : ''));
    }

    /**
     * Bulk-inserted orders for the store's main branch and the test customer, in insertion (id) order.
     *
     * @param  callable(int): array<string, mixed>  $attributes
     * @return list<int>
     */
    private function insertOrders(int $count, callable $attributes): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = $attributes($i) + ['status' => 'new', 'created_at' => now()];
            $rows[] = array_merge([
                'uuid' => (string) Str::uuid(),
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'user_id' => $this->customer->id,
                'order_type' => 'delivery',
                'payment_method' => 'cash',
                'total_price' => 50000,
                'shipping_address' => json_encode(['address' => 'Amir Temur 1']),
                'updated_at' => $row['created_at'],
            ], $row);
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        return DB::table('orders')->whereIn('uuid', array_column($rows, 'uuid'))->orderBy('id')->pluck('id')->all();
    }

    /**
     * Orders with every relation the listing loads: items, courier, manager, status history; half active, half not.
     */
    private function makeRichOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order = $this->makeOrder($i % 2 ? OrderStatus::ON_THE_WAY : OrderStatus::DELIVERED, [
                'courier_id' => $this->courier->id,
                'manager_id' => $this->manager->id,
            ]);
            foreach ([1, 2] as $quantity) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $this->variant->id,
                    'product_name' => 'Cheeseburger',
                    'quantity' => $quantity,
                    'unit_price' => 50000,
                    'total_price' => 50000 * $quantity,
                ]);
            }
            OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'new', 'user_id' => $this->customer->id]);
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<array<string, string>>  $scopes
     * @return array<string, int>
     */
    private function countQueries(array $users, array $scopes): array
    {
        $counts = [];
        foreach ($users as $label => $user) {
            foreach ($scopes as $query) {
                $this->actingAs($user, 'sanctum');
                $listed = 0;
                DB::flushQueryLog();
                DB::enableQueryLog();
                $listed = count($this->list($user, $query)->assertOk()->json('data'));
                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();

                $this->assertGreaterThan(0, $listed, "{$label} " . json_encode($query) . ' lists something');
                $counts["{$label} " . json_encode($query)] = $queries;
            }
        }

        return $counts;
    }
}
