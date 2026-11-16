<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Domain\Realtime\RevisionMap;
use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * Wave M2 guard: the tables each polled endpoint reads (its body), and that a write to each of them moves the token the
 * conditional GET of that endpoint is keyed by. An endpoint that starts reading a table that is not listed here fails
 * until the table is listed and classified in RevisionMap (a NONE table may only be one of AUTH_TABLES).
 *
 * Every table an endpoint reads must be tracked: a write to it then moves the branch or customer token, which is what
 * the endpoint's ETag is built from (M2-2). Adding a table to a list without classifying it is not possible: the
 * second assertion checks the classification.
 */
class PolledEndpointTablesTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    /** Read by authentication and request plumbing only, never part of a body */
    private const AUTH_TABLES = ['personal_access_tokens', 'devices', 'users', 'idempotency_keys', 'cache', 'cache_locks', 'sessions'];

    /**
     * Endpoint => the tables its body may be built from (beyond AUTH_TABLES). Keep in step with the code: a new
     * table read here is a new input of the polled payload.
     *
     * @var array<string, list<string>>
     */
    private const ENDPOINT_TABLES = [
        // (GET /pos/waitlist needs the hostess module, off in the fast-food fixture: its list is checked for tracking only)
        'GET /pos/floor' => ['branches', 'stores', 'halls', 'dining_tables', 'orders', 'order_items', 'order_bills', 'bookings', 'booking_tables', 'pos_waitlist_entries', 'pos_shifts', 'room_tariffs', 'room_tariff_calendars', 'service_fee_settings', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /pos/orders' => ['branches', 'stores', 'orders', 'order_items', 'order_bills', 'dining_tables', 'halls', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /pos/orders/{uuid}' => ['service_fee_settings', 'branch_packaging_rules', 'branches', 'stores', 'orders', 'order_items', 'order_bills', 'order_bill_items', 'payments', 'dining_tables', 'halls', 'product_variants', 'products', 'kitchen_stations', 'order_charge_waivers', 'order_packaging_removals', 'pos_tips', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'bookings', 'banquet_events', 'customer_accounts', 'fiscal_receipts', 'pos_refunds'],
        'GET /pos/queue' => ['branches', 'stores', 'orders', 'order_items', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'branch_takeaway_settings'],
        'GET /pos/display/state' => ['branches', 'stores', 'orders', 'order_items', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'branch_takeaway_settings', 'pos_qr_payments'],
        'GET /pos/display/queue' => ['branches', 'stores', 'orders', 'order_items', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'branch_takeaway_settings'],
        'GET /pos/sync/status' => ['branches', 'stores', 'pos_sync_operations', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /staff/kitchen/tickets' => ['order_status_histories', 'branches', 'stores', 'orders', 'order_items', 'kitchen_stations', 'kitchen_station_routes', 'product_variants', 'products', 'dining_tables', 'halls', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'role_permission_overrides', 'branch_brands', 'brands'],
        'GET /staff/orders' => ['order_status_histories', 'product_variants', 'products', 'branches', 'stores', 'orders', 'order_items', 'order_assignments', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'role_permission_overrides', 'dining_tables', 'halls'],
        'GET /pos/orders/{uuid}/bills' => ['branches', 'stores', 'orders', 'order_items', 'order_bills', 'order_bill_items', 'payments', 'fiscal_receipts', 'pos_refunds', 'pos_tips', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /pos/waitlist' => ['branches', 'stores', 'pos_waitlist_entries', 'dining_tables', 'halls', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /pos/bill-print-requests' => ['branches', 'stores', 'pos_bill_print_requests', 'orders', 'order_bills', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
        'GET /staff/orders/{uuid}' => ['order_status_histories', 'product_variants', 'products', 'branches', 'stores', 'orders', 'order_items', 'order_assignments', 'store_subscriptions', 'tariff_plans', 'features', 'settings', 'role_permission_overrides', 'dining_tables', 'halls', 'users'],
        'GET /orders' => ['orders', 'order_items', 'stores', 'branches', 'store_ratings', 'complaints', 'order_refund_requests'],
        'GET /notifications' => ['notification_logs', 'orders', 'stores'],
        // M2-2: the KDS ingredient stop panel (polled every 60 s)
        'GET /owner/ingredients' => ['ingredients', 'branches', 'stores', 'store_subscriptions', 'tariff_plans', 'features', 'settings'],
    ];

    private Order $order;

    private string $displayToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
        $this->displayToken = $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY)['token'];
        $uuid = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->order = Order::where('uuid', $uuid)->firstOrFail();
        $online = $this->makeOrder(OrderStatus::NEW); // a Mini App order of the customer

        // A paid takeaway (bill, payment, fiscal receipt, cash), a waitlist entry, a stop-list entry, a notification
        $paid = $this->openTakeaway()->assertCreated();
        $bill = (string) $this->tillAs('POST', "/pos/orders/{$paid->json('data.uuid')}/bills", ['version' => $paid->json('data.version'), 'mode' => 'single'])
            ->assertSuccessful()->json('data.bills.0.uuid');
        $this->tillAs('POST', "/pos/bills/{$bill}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36000']]])->assertSuccessful();
        $this->bumpAll((string) $paid->json('data.uuid'));
        DB::table('stop_list_entries')->insert([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'variant',
            'subject_id' => $this->tea->id, 'reason' => 'tugadi', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Models\NotificationLog::query()->forceCreate(['user_id' => $this->customer->id, 'order_id' => $online->id, 'type' => 'status', 'status' => 'sent']);
    }

    /** @return list<string> the tables the request's SELECTs read */
    private function tablesRead(string $endpoint, callable $request): array
    {
        $tables = [];
        DB::listen(function (QueryExecuted $query) use (&$tables) {
            if (!preg_match('/^\s*(select|with)\b/i', $query->sql)) {
                return;
            }
            if (preg_match_all('/\b(?:from|join)\s+"?([a-z_][a-z0-9_]*)"?/i', $query->sql, $m)) {
                foreach ($m[1] as $table) {
                    $tables[$table] = true;
                }
            }
        });
        $response = $request();
        $this->assertSame(200, $response->status(), "{$endpoint}: " . $response->getContent());
        $this->app['events']->forget(QueryExecuted::class);

        return array_keys(array_filter($tables, fn ($v, $table) => array_key_exists($table, RevisionMap::TABLES), ARRAY_FILTER_USE_BOTH));
    }

    /** @return array<string, callable> */
    private function requests(): array
    {
        $customer = fn (string $uri) => function () use ($uri) {
            $this->withBearer(null);

            return $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1' . $uri);
        };
        $staff = fn ($user, string $uri) => function () use ($user, $uri) {
            $this->withBearer(null);

            return $this->actingAs($user, 'sanctum')->getJson('/api/v1' . $uri);
        };

        return [
            'GET /pos/floor' => fn () => $this->tillAs('GET', '/pos/floor'),
            'GET /pos/orders' => fn () => $this->tillAs('GET', '/pos/orders'),
            'GET /pos/orders/{uuid}' => fn () => $this->tillAs('GET', "/pos/orders/{$this->order->uuid}"),
            'GET /pos/queue' => fn () => $this->tillAs('GET', '/pos/queue'),
            'GET /pos/display/state' => fn () => $this->withBearer($this->displayToken)->getJson('/api/v1/pos/display/state'),
            'GET /pos/display/queue' => fn () => $this->withBearer($this->displayToken)->getJson('/api/v1/pos/display/queue'),
            'GET /pos/sync/status' => fn () => $this->withBearer($this->till['token'])->getJson('/api/v1/pos/sync/status'),
            'GET /staff/kitchen/tickets' => $staff($this->cook, '/staff/kitchen/tickets'),
            'GET /staff/orders' => $staff($this->manager, '/staff/orders'),
            'GET /pos/orders/{uuid}/bills' => fn () => $this->tillAs('GET', "/pos/orders/{$this->order->uuid}/bills"),
            'GET /pos/bill-print-requests' => fn () => $this->withBearer($this->till['token'])->getJson('/api/v1/pos/bill-print-requests'),
            'GET /staff/orders/{uuid}' => $staff($this->manager, "/staff/orders/{$this->order->uuid}"),
            'GET /orders' => $customer('/orders'),
            'GET /notifications' => $customer('/notifications'),
            // Last: it switches the branch to a format with the ingredient stop-list
            'GET /owner/ingredients' => function () use ($staff) {
                $this->useProfile($this->branch, 'dark_kitchen');

                return $staff($this->cook, '/owner/ingredients')();
            },
        ];
    }

    public function test_each_polled_endpoint_reads_only_listed_and_tracked_tables(): void
    {
        $problems = [];
        foreach ($this->requests() as $endpoint => $request) {
            $declared = self::ENDPOINT_TABLES[$endpoint];
            foreach ($this->tablesRead($endpoint, $request) as $table) {
                if (in_array($table, self::AUTH_TABLES, true)) {
                    continue;
                }
                if (!in_array($table, $declared, true)) {
                    $problems[] = "{$endpoint} reads {$table}, which is not listed for it";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_every_listed_table_is_tracked_so_its_writes_move_a_token(): void
    {
        foreach (self::ENDPOINT_TABLES as $endpoint => $tables) {
            foreach ($tables as $table) {
                $this->assertTrue(RevisionMap::tracks($table), "{$endpoint} shows {$table}, but RevisionMap classifies it NONE: no write to it would refresh the screen");
            }
        }
    }

    public function test_a_write_to_the_rows_a_polled_screen_shows_moves_its_scope_token(): void
    {
        // Every tracked table that the flow above filled: a no-op update of one row must move the branch token (or, for
        // a customer-only table, the customer's). This is the end-to-end proof that the rule columns and VIA chains of
        // RevisionMap resolve real rows.
        $revisions = app(ChangeRevision::class);
        $checked = [];
        foreach (array_keys(RevisionMap::TABLES) as $table) {
            if (!RevisionMap::tracks($table) || !\Illuminate\Support\Facades\Schema::hasColumn($table, 'id')) {
                continue;
            }
            $row = DB::table($table)->orderByDesc('id')->first();
            if ($row === null) {
                continue;
            }
            $rules = array_column(RevisionMap::rules($table) ?? [], 0);
            $userOnly = array_diff($rules, [RevisionMap::USER]) === [];

            if ($userOnly) {
                $column = RevisionMap::rules($table)[0][1];
                $userId = (int) ($row->{$column} ?? 0);
                if ($userId === 0) {
                    continue;
                }
                $before = $revisions->user($userId);
                DB::table($table)->where('id', $row->id)->update(['id' => DB::raw('id')]);
                $this->assertNotSame($before, $revisions->user($userId), "a write to {$table} did not move the user token");
            } else {
                $before = [$revisions->branch($this->branch->id), $revisions->branch($this->otherBranch->id)];
                DB::table($table)->where('id', $row->id)->update(['id' => DB::raw('id')]);
                $after = [$revisions->branch($this->branch->id), $revisions->branch($this->otherBranch->id)];
                $this->assertNotSame($before, $after, "a write to {$table} (row {$row->id}) did not move a branch token of the store");
            }
            $checked[] = $table;
        }

        $this->assertGreaterThanOrEqual(25, count($checked), 'the flow filled too few tables: ' . implode(', ', $checked));
    }
}
