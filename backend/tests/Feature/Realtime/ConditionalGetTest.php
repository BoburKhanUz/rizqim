<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * Wave M2 (M2-2): conditional GETs of the polled endpoints (App\Http\Middleware\ConditionalGet). For each endpoint:
 * (1) an unchanged repeat with the ETag is a 304 that reads no main table, (2) every kind of write that changes the
 * body makes the next poll a 200 with the new body. Plus the guards: a revoked token, a lost permission, another
 * actor, an expired, forged or weak tag, the switch, and a dead revision store never get a 304 they should not.
 */
class ConditionalGetTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    /**
     * Tables a 304 may read: authentication (tokens, the user, a PIN session's terminal) and the permission and
     * module gates the route runs before the conditional check.
     */
    private const AUTH_TABLES = ['personal_access_tokens', 'users', 'devices', 'role_permission_overrides', 'cache', 'cache_locks'];

    /** The module gate (pos.module:...) of /pos/queue and /owner/ingredients also reads the branch, store and plan. */
    private const GATE_TABLES = ['branches', 'stores', 'store_subscriptions', 'tariff_plans', 'features'];

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        // kafe: hall service, queue numbers and calling, the customer display and a KDS
        $this->setUpPosQueue('kafe');
        $this->table = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => 'Stol 1', 'kind' => 'table', 'seats' => 4]);
    }

    // --- helpers ---------------------------------------------------------------------------------------------------

    private function poll(?string $token, string $uri, ?string $etag = null): TestResponse
    {
        $this->withBearer($token);
        if ($etag !== null) {
            $this->withHeader('If-None-Match', $etag);
        }

        return $this->getJson('/api/v1' . $uri);
    }

    /**
     * A 200 with an ETag and Cache-Control: private, no-cache; returns the ETag.
     */
    private function fresh(?string $token, string $uri, ?string $etag = null): string
    {
        $response = $this->poll($token, $uri, $etag)->assertOk();
        $tag = $response->headers->get('ETag');
        $this->assertIsString($tag, "{$uri}: a 200 carries an ETag");
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        return $tag;
    }

    /**
     * The repeat is a 304 with no body that read no main table.
     *
     * @param  list<string>  $alsoAllowed
     */
    private function notModified(?string $token, string $uri, string $etag, array $alsoAllowed = []): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->poll($token, $uri, $etag);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(304);
        $this->assertSame('', (string) $response->getContent());
        $this->assertSame(preg_replace('#^W/#', '', $etag), $response->headers->get('ETag'));

        $allowed = [...self::AUTH_TABLES, ...$alsoAllowed];
        foreach ($queries as $query) {
            preg_match_all('/\b(?:from|join|update|into)\s+"?([a-z_]+)"?/i', $query['query'], $m);
            foreach ($m[1] as $table) {
                $this->assertContains($table, $allowed, "{$uri}: a 304 must not read {$table}: {$query['query']}");
            }
        }
    }

    /**
     * After a write: a 200 with a new ETag (and the caller checks the new body).
     */
    private function changed(?string $token, string $uri, string $etag): TestResponse
    {
        $response = $this->poll($token, $uri, $etag);
        $response->assertOk();
        $this->assertNotSame($etag, $response->headers->get('ETag'), "{$uri}: a changed body gets a new ETag");

        return $response;
    }

    private function openHallOrder(): string
    {
        return (string) $this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->table->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]], 'send' => true,
        ])->assertSuccessful()->json('data.uuid');
    }

    private function cookToken(): string
    {
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);

        return $this->pinSession($kds, $this->cook, '7303');
    }

    // --- POS: floor, orders, order, bills, queue --------------------------------------------------------------------

    public function test_floor_is_304_until_an_order_a_round_or_a_table_changes(): void
    {
        $first = $this->poll($this->cashierToken, '/pos/floor')->assertOk();
        $tag = (string) $first->headers->get('ETag');
        // One scope: its token is named, as the broadcast events' `rev`
        $this->assertSame(app(ChangeRevision::class)->branch($this->branch->id), $first->headers->get('X-Revision'));
        $this->assertSame('branch:' . $this->branch->id, $first->headers->get('X-Revision-Scope'));
        $this->notModified($this->cashierToken, '/pos/floor', $tag);

        // A new order at the table
        $uuid = $this->openHallOrder();
        $response = $this->changed($this->cashierToken, '/pos/floor', $tag);
        $this->assertContains($uuid, collect($response->json('data.halls'))->flatMap(fn ($hall) => collect($hall['tables'])->flatMap(fn ($table) => collect($table['orders'])->pluck('uuid')))->all());
        $tag = (string) $response->headers->get('ETag');
        $this->notModified($this->cashierToken, '/pos/floor', $tag);

        // A new round
        $version = (int) Order::where('uuid', $uuid)->value('version');
        $this->tillAs('POST', "/pos/orders/{$uuid}/rounds", ['version' => $version, 'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 1]]])->assertSuccessful();
        $tag = (string) $this->changed($this->cashierToken, '/pos/floor', $tag)->headers->get('ETag');

        // The owner renames the table
        $this->withBearer(null);
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/dining-tables/{$this->table->id}", ['name' => 'Deraza yonida'])->assertOk();
        $response = $this->changed($this->cashierToken, '/pos/floor', $tag);
        $this->assertContains('Deraza yonida', collect($response->json('data.halls'))->flatMap(fn ($hall) => collect($hall['tables'])->pluck('name'))->all());
    }

    public function test_the_tills_order_list_order_and_bills_follow_their_writes(): void
    {
        $listTag = $this->fresh($this->cashierToken, '/pos/orders');
        $this->notModified($this->cashierToken, '/pos/orders', $listTag);

        $uuid = $this->openHallOrder();
        $response = $this->changed($this->cashierToken, '/pos/orders', $listTag);
        $this->assertContains($uuid, collect($response->json('data'))->pluck('uuid')->all());
        $listTag = (string) $response->headers->get('ETag');

        $orderTag = $this->fresh($this->cashierToken, "/pos/orders/{$uuid}");
        $this->notModified($this->cashierToken, "/pos/orders/{$uuid}", $orderTag);
        $billsTag = $this->fresh($this->cashierToken, "/pos/orders/{$uuid}/bills");
        $this->notModified($this->cashierToken, "/pos/orders/{$uuid}/bills", $billsTag);

        // Guests change: the order and the list
        $version = (int) Order::where('uuid', $uuid)->value('version');
        $this->tillAs('POST', "/pos/orders/{$uuid}/guests", ['version' => $version, 'guests_count' => 3])->assertSuccessful();
        $this->assertSame(3, $this->changed($this->cashierToken, "/pos/orders/{$uuid}", $orderTag)->json('data.guests_count'));
        $this->changed($this->cashierToken, '/pos/orders', $listTag);

        // A bill: the bills read
        $version = (int) Order::where('uuid', $uuid)->value('version');
        $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $version, 'mode' => 'single'])->assertSuccessful();
        $this->assertCount(1, $this->changed($this->cashierToken, "/pos/orders/{$uuid}/bills", $billsTag)->json('data.bills'));
    }

    public function test_the_pickup_board_and_the_display_board_follow_the_numbers(): void
    {
        $display = $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY);
        $tag = $this->fresh($this->cashierToken, '/pos/queue');
        $this->notModified($this->cashierToken, '/pos/queue', $tag, self::GATE_TABLES);
        $displayTag = $this->fresh($display['token'], '/pos/display/queue');
        $this->notModified($display['token'], '/pos/display/queue', $displayTag);

        $uuid = (string) $this->openTakeaway()->assertSuccessful()->json('data.uuid');
        $response = $this->changed($this->cashierToken, '/pos/queue', $tag);
        $this->assertCount(1, $response->json('data.preparing'));
        $tag = (string) $response->headers->get('ETag');
        $this->assertCount(1, $this->changed($display['token'], '/pos/display/queue', $displayTag)->json('data.preparing'));

        $this->bumpAll($uuid);
        $this->assertCount(1, $this->changed($this->cashierToken, '/pos/queue', $tag)->json('data.ready'));
    }

    public function test_the_display_state_follows_the_published_cart_and_its_own_device(): void
    {
        $display = $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY);
        $tag = $this->fresh($display['token'], '/pos/display/state');
        $this->notModified($display['token'], '/pos/display/state', $tag);

        // The till publishes its cart (DisplayFeed keeps it in the cache, not in a table): the display must see it
        $this->withBearer($this->till['token'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/pos/display/cart', [
                'state' => 'cart', 'seq' => 1, 'session' => 'run-1',
                'lines' => [['name' => 'Choy', 'qty' => '1', 'price' => '5000', 'total' => '5000']],
                'subtotal' => '5000', 'discount' => '0', 'total' => '5000',
            ])->assertOk()->assertJsonPath('data.stored', true);
        $response = $this->changed($display['token'], '/pos/display/state', $tag);
        $this->assertSame('5000', $response->json('data.cart.total'));
        $tag = (string) $response->headers->get('ETag');

        // The owner renames the display device
        $display['device']->forceFill(['name' => 'Kassa ekrani'])->save();
        $this->assertSame('Kassa ekrani', $this->changed($display['token'], '/pos/display/state', $tag)->json('data.device.name'));
    }

    // --- kitchen ---------------------------------------------------------------------------------------------------

    public function test_kitchen_tickets_follow_sends_and_bumps(): void
    {
        $cook = $this->cookToken();
        $tag = $this->fresh($cook, '/staff/kitchen/tickets?include_ready=1');
        $this->notModified($cook, '/staff/kitchen/tickets?include_ready=1', $tag);

        $uuid = $this->openHallOrder();
        $response = $this->changed($cook, '/staff/kitchen/tickets?include_ready=1', $tag);
        $this->assertContains($uuid, collect($response->json('data'))->pluck('uuid')->all());
        $tag = (string) $response->headers->get('ETag');
        $this->notModified($cook, '/staff/kitchen/tickets?include_ready=1', $tag);

        $item = Order::where('uuid', $uuid)->firstOrFail()->items()->whereNotNull('sent_at')->firstOrFail();
        $this->withBearer($cook)->postJson("/api/v1/staff/orders/{$uuid}/items/{$item->id}/ready")->assertOk();
        $line = collect($this->changed($cook, '/staff/kitchen/tickets?include_ready=1', $tag)->json('data'))->firstWhere('uuid', $uuid)['items'][0];
        $this->assertSame('ready', $line['kitchen_status']);
    }

    public function test_a_pickup_for_later_reaches_the_kitchen_at_its_time_without_a_write(): void
    {
        $cook = $this->cookToken();
        // An online pickup whose kitchen time is in 5 minutes: not on the board yet
        $order = $this->orderWithItems(OrderStatus::ACCEPTED, ['pickup_at' => now()->addMinutes(20), 'kitchen_fire_at' => now()->addMinutes(5)]);
        $tag = $this->fresh($cook, '/staff/kitchen/tickets');
        $this->assertNotContains($order->uuid, collect($this->poll($cook, '/staff/kitchen/tickets')->json('data'))->pluck('uuid')->all());
        $this->notModified($cook, '/staff/kitchen/tickets', $tag);

        // Its kitchen time passes (no scheduler run, no write): the tag no longer validates
        Carbon::setTestNow(now()->addMinutes(5)->addSecond());
        $response = $this->changed($cook, '/staff/kitchen/tickets', $tag);
        $this->assertContains($order->uuid, collect($response->json('data'))->pluck('uuid')->all());
    }

    // --- staff board (LiveOrders) ----------------------------------------------------------------------------------

    public function test_the_staff_board_and_detail_follow_status_changes_store_wide_and_per_branch(): void
    {
        $order = $this->orderWithItems(OrderStatus::NEW);

        foreach (["/staff/orders?scope=active", "/staff/orders?scope=active&branch_id={$this->branch->id}"] as $uri) {
            $this->withBearer(null);
            $token = $this->owner->createToken('web')->plainTextToken;
            $tag = $this->fresh($token, $uri);
            $this->notModified($token, $uri, $tag);
            $detail = $this->fresh($token, "/staff/orders/{$order->uuid}");
            $this->notModified($token, "/staff/orders/{$order->uuid}", $detail);

            $next = $order->refresh()->status === OrderStatus::NEW ? 'accepted' : 'preparing';
            $this->withBearer($token)->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => $next])->assertOk();
            $this->assertSame($next, collect($this->changed($token, $uri, $tag)->json('data'))->firstWhere('uuid', $order->uuid)['status']);
            $this->assertSame($next, $this->changed($token, "/staff/orders/{$order->uuid}", $detail)->json('data.status'));
        }

        // An order of the other branch changes the store-wide list, not the branch's
        $token = $this->owner->createToken('web2')->plainTextToken;
        $wide = $this->fresh($token, '/staff/orders?scope=active');
        $this->assertNull($this->poll($token, '/staff/orders?scope=active')->headers->get('X-Revision'), 'a store-wide list names no single revision');
        $branchOnly = $this->fresh($token, "/staff/orders?scope=active&branch_id={$this->branch->id}");
        $this->orderWithItems(OrderStatus::NEW, ['branch_id' => $this->otherBranch->id]);
        $this->changed($token, '/staff/orders?scope=active', $wide);
        $this->notModified($token, "/staff/orders?scope=active&branch_id={$this->branch->id}", $branchOnly);
    }

    // --- customer feeds ----------------------------------------------------------------------------------------------

    public function test_the_customers_orders_and_notifications_follow_their_writes(): void
    {
        $token = $this->customer->createToken('miniapp')->plainTextToken;
        $order = $this->orderWithItems(OrderStatus::NEW);

        $tag = $this->fresh($token, '/orders?scope=active');
        $this->notModified($token, '/orders?scope=active', $tag);
        $notes = $this->fresh($token, '/notifications');
        $this->notModified($token, '/notifications', $notes);

        // The store accepts the order (a staff write): the customer's list changes
        $this->withBearer(null);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => 'accepted'])->assertOk();
        $this->assertSame('accepted', collect($this->changed($token, '/orders?scope=active', $tag)->json('data'))->firstWhere('uuid', $order->uuid)['status']);

        // A notification arrives, then all are marked read (a mass update)
        NotificationLog::create(['user_id' => $this->customer->id, 'order_id' => $order->id, 'type' => 'order_accepted', 'channel' => 'telegram', 'status' => 'sent', 'is_read' => false, 'sent_at' => now()]);
        $response = $this->changed($token, '/notifications', $notes);
        $this->assertSame(1, $response->json('data.unread_count'));
        $notes = (string) $response->headers->get('ETag');
        $this->withBearer($token)->postJson('/api/v1/notifications/read')->assertOk();
        $this->assertSame(0, $this->changed($token, '/notifications', $notes)->json('data.unread_count'));
    }

    // --- KDS ingredients -------------------------------------------------------------------------------------------

    public function test_the_kitchen_ingredient_list_follows_a_stop(): void
    {
        $this->useProfile($this->branch, 'dark_kitchen');
        $cook = $this->cookToken();
        $this->withBearer(null);
        $id = (int) $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/ingredients', ['name' => 'Pomidor'])->assertSuccessful()->json('data.id');

        $tag = $this->fresh($cook, '/owner/ingredients');
        $this->notModified($cook, '/owner/ingredients', $tag, self::GATE_TABLES);

        $this->withBearer(null);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$id}/stop", ['branch_id' => $this->branch->id])->assertSuccessful();
        $this->changed($cook, '/owner/ingredients', $tag);
    }

    // --- guards ----------------------------------------------------------------------------------------------------

    public function test_a_revoked_token_or_a_lost_permission_never_gets_a_304(): void
    {
        $waiter = $this->pinSession($this->pairTerminal($this->owner, $this->branch), $this->waiter, '7304');
        $tag = $this->fresh($waiter, '/pos/floor');

        // Another actor (the cashier on the same till) with the waiter's tag: a full read
        $this->assertNotSame($tag, $this->fresh($this->cashierToken, '/pos/floor', $tag));

        // The store takes orders.view away from waiters: 403, not 304
        app(PermissionService::class)->setOverride($this->store, UserRole::WAITER, Permission::ORDERS_VIEW, false, $this->owner);
        $this->poll($waiter, '/pos/floor', $tag)->assertForbidden();
        app(PermissionService::class)->setOverride($this->store, UserRole::WAITER, Permission::ORDERS_VIEW, null, $this->owner);
        // Given back: the permission change moved the branch token too (an extra full read, then 304 again)
        $tag = $this->fresh($waiter, '/pos/floor', $tag);
        $this->notModified($waiter, '/pos/floor', $tag);

        // The token is revoked: 401, not 304
        PersonalAccessToken::findToken($waiter)?->delete();
        $this->poll($waiter, '/pos/floor', $tag)->assertUnauthorized();
    }

    public function test_an_expired_forged_or_weak_tag(): void
    {
        $tag = $this->fresh($this->cashierToken, '/pos/floor');

        // nginx's gzip turns the tag weak: still the same tag; a list of tags matches on any of them
        $this->notModified($this->cashierToken, '/pos/floor', 'W/' . $tag);
        $this->poll($this->cashierToken, '/pos/floor', '"x", ' . $tag)->assertStatus(304);

        // A forged MAC, another query string, garbage: full reads
        $this->fresh($this->cashierToken, '/pos/floor', substr($tag, 0, -3) . 'AA"');
        $this->fresh($this->cashierToken, "/pos/floor?branch_id={$this->branch->id}", $tag);
        $this->fresh($this->cashierToken, '/pos/floor', '*');

        // The route's max-age (120 s) bounds what changes with the clock alone (open_minutes, overdue, reserved soon)
        Carbon::setTestNow(now()->addSeconds(119));
        $this->notModified($this->cashierToken, '/pos/floor', $tag);
        Carbon::setTestNow(now()->addSeconds(2));
        $this->fresh($this->cashierToken, '/pos/floor', $tag);
    }

    public function test_switched_off_or_with_the_revision_store_down_every_poll_is_a_full_read(): void
    {
        $tag = $this->fresh($this->cashierToken, '/pos/floor');

        config(['conditional_get.enabled' => false]);
        $response = $this->poll($this->cashierToken, '/pos/floor', $tag)->assertOk();
        $this->assertNull($response->headers->get('ETag'));
        config(['conditional_get.enabled' => true]);

        // Every token read is a fresh random value when the store is unreachable: a tag never validates
        config(['realtime.revision_store' => 'missing-store']);
        $this->app->forgetInstance(ChangeRevision::class);
        $this->fresh($this->cashierToken, '/pos/floor', $tag);
        $again = $this->fresh($this->cashierToken, '/pos/floor');
        $this->fresh($this->cashierToken, '/pos/floor', $again);
    }

    public function test_a_304_is_no_more_expensive_for_the_rate_limiter_and_the_body_is_unchanged(): void
    {
        $plain = $this->poll($this->cashierToken, '/pos/floor')->assertOk();
        $tag = (string) $plain->headers->get('ETag');

        config(['conditional_get.enabled' => false]);
        $before = $this->poll($this->cashierToken, '/pos/floor')->assertOk();
        config(['conditional_get.enabled' => true]);
        // The same body with or without the conditional layer (generated_at aside: the clock is frozen)
        $this->assertSame($before->json('data'), $plain->json('data'));

        $limit = (int) $plain->headers->get('X-RateLimit-Remaining');
        $notModified = $this->poll($this->cashierToken, '/pos/floor', $tag)->assertStatus(304);
        $this->assertSame($limit - 2, (int) $notModified->headers->get('X-RateLimit-Remaining'));
    }
}
