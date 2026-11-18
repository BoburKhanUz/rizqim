<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosWaitlistEntry;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * M4 stage 1, branch isolation inside one store (docs/scale/M4_DESIGN.md 4.3: it stays in the application,
 * PermissionService::actingBranchId): A gets a second branch, and A's manager of the first branch calls every
 * manifest route whose records are branch-owned with the second branch's ids. Each call must be refused (a list
 * filtered by the branch may answer with the manager's own branch), show nothing of the second branch (its `ZZA2-`
 * names, uuids, ids) and change none of its rows. Routes that name no branch-owned record are not called.
 */
class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TenancySuite;

    private const MARK2 = 'ZZA2-';

    private const BRANCH2_IDS = 30_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_a_branch_manager_reaches_nothing_of_another_branch(): void
    {
        $this->buildTenancyWorld();
        $a = $this->world['A'];
        $this->moveSequences(self::BRANCH2_IDS);
        $second = $this->secondBranch($a);
        $last = (int) DB::selectOne('SELECT max(last_value) AS v FROM pg_sequences WHERE schemaname = current_schema() AND last_value BETWEEN ? AND ?', [self::BRANCH2_IDS, self::BRANCH2_IDS + 999_999])->v;
        $this->moveSequences(self::AFTER_IDS + 5_000_000);
        $this->assertGreaterThan(self::BRANCH2_IDS, $last, 'the second branch got no ids of its range');
        $this->prepareObservation();

        $uuids = [];
        foreach ($second as $value) {
            if ($value instanceof \Illuminate\Database\Eloquent\Model && isset($value->uuid)) {
                $uuids[] = strtolower((string) $value->uuid);
            }
        }
        $before = $this->branchSnapshot($last);
        $this->observeAlso = fn () => $this->branchSnapshot($last);
        $manifest = CrossTenantRouteTest::manifest();
        $routes = CrossTenantRouteTest::tenantRoutes();
        $other = array_replace($a, $second);

        $failures = [];
        $called = 0;
        foreach ($manifest['routes'] as $key => $entry) {
            if (!isset($routes[$key]) || in_array('auth:device', $routes[$key]->gatherMiddleware(), true)) {
                continue;
            }
            [$method, $uri] = explode(' ', $key, 2);
            $paramsOf = $entry['params'] ?? fn (array $s) => [];
            $bodyOf = $entry['body'] ?? fn (array $s) => [];
            try {
                [$params, $body] = [$paramsOf($other), $bodyOf($other)];
                if ($params == $paramsOf($a) && $body == $bodyOf($a)) {
                    continue; // names nothing branch-owned
                }
            } catch (\Throwable $e) {
                $failures[] = "{$key}: the manifest entry fails for the second branch: " . $e->getMessage();

                continue;
            }
            $called++;
            $url = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => rawurlencode((string) $params[$m[1]]), $uri);
            $call = $this->isolatedCall($method, $url, $a['manager_token'], $body, false);
            $answered = $call['status'] >= 200 && $call['status'] < 300;
            if (!in_array($call['status'], CrossTenantRouteTest::REFUSALS, true) && !($answered && (($entry['filter'] ?? false) || ($entry['soft'] ?? []) !== []))) {
                $failures[] = "{$key}: the first branch's manager with the second branch's ids: {$call['status']}: " . Str::limit($call['content'], 300);
            }
            $content = strtolower($call['content']);
            $sent = strtolower($call['request']);
            $shown = array_filter($uuids, fn ($uuid) => str_contains($content, $uuid) && !str_contains($sent, $uuid));
            if (str_contains($content, strtolower(self::MARK2)) || $shown !== []) {
                $failures[] = "{$key}: shows the second branch (" . ($shown !== [] ? reset($shown) : self::MARK2) . ')';
            }
            if ($call['also'] !== $before) {
                $failures[] = "{$key}: changes the second branch in " . implode(', ', array_keys(array_diff_assoc($call['also'], $before))) . " ({$call['status']})";
            }
        }

        $this->assertSame($before, $this->branchSnapshot($last), 'the second branch changed outside the isolated calls');
        $this->assertGreaterThan(20, $called, 'too few routes name a branch-owned record');
        $this->assertSame([], $failures, count($failures) . " finding(s) in {$called} routes:\n" . implode("\n", $failures));
    }

    /**
     * The second branch of A, with what the manifest names that belongs to a branch.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function secondBranch(array $a): array
    {
        $p = self::MARK2;
        $store = $a['store'];
        $branch = $this->makeBranch($store, $p . 'Filial');
        $branch->forceFill(['pos_settings' => $a['branch']->fresh()->pos_settings])->save();
        $at = ['store_id' => $store->id, 'branch_id' => $branch->id];
        $hall = Hall::create($at + ['name' => $p . 'Zal', 'sort_order' => 1]);
        $place = fn (string $name, string $kind) => DiningTable::create($at + ['hall_id' => $hall->id, 'name' => $p . $name, 'kind' => $kind, 'seats' => 4]);
        $order = Order::create($at + [
            'user_id' => $this->customer->id, 'status' => OrderStatus::ACCEPTED, 'order_type' => 'dine_in', 'channel' => 'pos_hall',
            'payment_method' => 'cash', 'total_price' => 18000, 'commission_amount' => 0, 'version' => 1,
        ]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $a['variant']->id, 'product_name' => $p . 'Taom', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 18000, 'total_price' => 18000]);
        $online = Order::create($at + [
            'user_id' => $this->customer->id, 'status' => OrderStatus::NEW, 'order_type' => 'pickup', 'payment_method' => 'cash',
            'total_price' => 18000, 'commission_amount' => 0,
        ]);
        $station = KitchenStation::create($at + ['name' => $p . 'Sex', 'kind' => 'kitchen']);

        return [
            'branch' => $branch,
            'hall' => $hall,
            'banquet_hall' => $hall,
            'table' => $place('Stol 1', 'table'),
            'table2' => $place('Stol 2', 'table'),
            'table4' => $place('Stol 4', 'table'),
            'free_table' => $place('Stol 3', 'table'),
            'room' => $place('Xona', 'room'),
            'order' => $order,
            'order2' => $order,
            'item' => $item,
            'held_item' => $item,
            'weight_item' => $item,
            'online_order' => $online,
            'delivery_order' => $online,
            'station' => $station,
            'station2' => $station,
            'printer' => Printer::create($at + ['name' => $p . 'Printer', 'purpose' => 'receipt', 'connection' => 'lan', 'address' => '192.168.2.50']),
            'waitlist' => PosWaitlistEntry::create($at + ['business_date' => '2026-06-10', 'guest_name' => $p . 'Mehmon', 'party_size' => 2]),
            'booking' => Booking::create($at + ['starts_at' => '2026-06-20 14:00:00', 'ends_at' => '2026-06-20 18:00:00', 'contact_name' => $p . 'Bron', 'status' => 'confirmed']),
        ];
    }

    /**
     * @return array<string, string> table => md5 of the second branch's rows
     */
    private function branchSnapshot(int $last): array
    {
        $snapshot = [];
        foreach (DB::select("SELECT table_name FROM information_schema.columns WHERE table_schema = current_schema() AND column_name = 'id' AND data_type IN ('bigint', 'integer')") as $row) {
            $hash = DB::selectOne("SELECT md5(coalesce(string_agg(x::text, '|' ORDER BY x::text), '')) AS h FROM \"{$row->table_name}\" x WHERE id BETWEEN ? AND ?", [self::BRANCH2_IDS, $last])->h;
            $snapshot[$row->table_name] = $hash;
        }
        ksort($snapshot);

        return $snapshot;
    }
}
