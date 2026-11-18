<?php

namespace Tests\Feature\Tenancy;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\PosWaitlistEntry;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M4 stage 1: the proof that the cross-tenant suite can fail. Deliberately leaky routes are registered at runtime
 * under a tenant prefix with the real staff middleware, each leaking store B in one way, and each is put through the
 * suite's own check (TenancySuite::routeFailures with a manifest entry like routes.php's). Every kind must be flagged;
 * a correctly scoped twin must pass, so the check is not simply failing everything. A kind this test cannot see is a
 * kind the suite cannot see.
 */
class LeakDetectorTest extends TestCase
{
    use RefreshDatabase;
    use TenancySuite;

    private const PREFIX = 'api/v1/pos/zz-leak';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_every_kind_of_leak_is_flagged_and_a_scoped_route_is_not(): void
    {
        $this->buildTenancyWorld();
        $this->registerLeakyRoutes();
        $this->prepareObservation();
        $this->bBefore = $this->snapshotB();

        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes["{$method} {$route->uri()}"] = $route;
            }
        }

        $missed = [];
        $flagged = [];
        foreach ($this->kinds() as $kind => [$key, $entry]) {
            $failures = $this->routeFailures($key, $routes[$key], $entry);
            if ($kind === 'scoped') {
                $this->assertSame([], $failures, "the correctly scoped route is flagged:\n" . implode("\n", $failures));

                continue;
            }
            if ($failures === []) {
                $missed[] = $kind;
            } else {
                $flagged[$kind] = $failures[0];
            }
        }

        $this->assertSame($this->bBefore, $this->snapshotB(), 'B changed outside the isolated calls');
        $this->assertSame([], $missed, 'leak kinds the suite does not see: ' . implode(', ', $missed));
        $this->assertCount(18, $flagged);
    }

    /**
     * kind => [route key, manifest entry]
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    private function kinds(): array
    {
        $order = fn (array $s) => ['uuid' => $s['order']->uuid];
        $ok = ['owner' => 200, 'manager' => 200, 'cashier' => 200];
        $get = fn (string $path, array $entry = []) => ['GET ' . self::PREFIX . $path, $entry + ['params' => $order, 'ok' => $ok]];
        $post = fn (string $path, array $entry = []) => ['POST ' . self::PREFIX . $path, $entry + ['params' => $order, 'ok' => $ok]];

        return [
            'scoped' => $get('/scoped/{uuid}'),
            'i returns B\'s row' => $get('/i/{uuid}'),
            'i-b refusal carries B\'s numbers' => $get('/ib/{uuid}'),
            'ii updates B, then refuses' => $post('/ii/{uuid}'),
            'ii-b A\'s own call updates B' => $post('/iib/{uuid}'),
            'iii shows B\'s uuid only' => $get('/iii/{uuid}', ['params' => fn (array $s) => ['uuid' => $s['billed_order']->uuid]]),
            'iv-a A\'s row references B by foreign key' => $post('/iva/{uuid}', ['body' => fn (array $s) => ['variant_id' => $s['tea']->id]]),
            'iv-b A\'s row references B without a foreign key' => $post('/ivb/{uuid}', ['body' => fn (array $s) => ['station_id' => $s['station']->id]]),
            'iv-c A\'s row references B inside json' => $post('/ivc/{uuid}', ['body' => fn (array $s) => ['modifier_id' => $s['modifier']->id]]),
            'v deletes B\'s row' => ['DELETE ' . self::PREFIX . '/v/{uuid}', ['params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid], 'ok' => $ok]],
            'vi-a B in a nested relation' => $get('/via/{uuid}'),
            'vi-b B in a response header' => $get('/vib/{uuid}'),
            'vi-c a list total over A and B' => ['GET ' . self::PREFIX . '/vic', ['ok' => $ok]],
            'vii-a a sync job changes B' => $post('/viia/{uuid}'),
            'vii-b an after-commit callback changes B' => $post('/viib/{uuid}'),
            'vii-c B\'s cache key written' => $post('/viic/{uuid}'),
            'viii B\'s stores row updated' => $post('/viii/{uuid}'),
            'ix a broadcast on B\'s channel' => $post('/ix/{uuid}'),
            'x a notification to B\'s staff' => $post('/x/{uuid}'),
        ];
    }

    /**
     * The leaky routes: the real middleware of the till's order routes (Sanctum, staff with a store), no scope.
     */
    private function registerLeakyRoutes(): void
    {
        $middleware = ['api', 'auth:sanctum', \App\Http\Middleware\EnsureStaffHasStore::class];
        $mine = fn (Request $request, $model) => (int) $model->store_id === (int) $request->user()->store_id;
        $refuse = fn (array $data = []) => response()->json(['success' => false, 'message' => 'Topilmadi.', 'data' => $data], 404);
        $find = fn (string $uuid) => Order::where('uuid', $uuid)->firstOrFail();

        Route::middleware($middleware)->prefix(self::PREFIX)->group(function () use ($mine, $refuse, $find) {
            Route::get('scoped/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);

                return $mine($request, $order) ? response()->json(['data' => ['uuid' => $order->uuid, 'total' => $order->total_price]]) : $refuse();
            });
            // i: B's row, as if it were the caller's
            Route::get('i/{uuid}', fn (string $uuid) => response()->json(['data' => $find($uuid)->only(['id', 'uuid', 'total_price', 'table_id'])]));
            // i-b: refused, but the body names B's numbers
            Route::get('ib/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);

                return $mine($request, $order) ? response()->json(['data' => ['uuid' => $order->uuid]]) : $refuse(['id' => $order->id, 'total' => $order->total_price, 'table_id' => $order->table_id]);
            });
            // ii: B changed before the refusal
            Route::post('ii/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                $order->forceFill(['notes' => 'tegildi'])->save();

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
            // ii-b: B refused correctly, but A's own call updates every store's orders
            Route::post('iib/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    return $refuse();
                }
                Order::query()->where('status', $order->status)->update(['notes' => 'hammasi']);

                return response()->json(['data' => []]);
            });
            // iii: refused, but the body names another of B's records (its bill)
            Route::get('iii/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse(['bill' => OrderBill::where('order_id', $order->id)->value('uuid')]);
            });
            // iv: A's own line, pointing at whatever the body names; refused when it is not A's
            $line = function (Request $request, string $uuid, array $attributes, bool $foreign) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    return $refuse();
                }
                OrderItem::create($attributes + ['order_id' => $order->id, 'product_name' => 'Taom', 'quantity' => 1, 'unit_price' => 1000, 'total_price' => 1000]);

                return $foreign ? response()->json(['success' => false, 'message' => 'Noto\'g\'ri.'], 422) : response()->json(['data' => []], 200);
            };
            Route::post('iva/{uuid}', function (Request $request, string $uuid) use ($line) {
                $variant = \App\Models\ProductVariant::with('product')->findOrFail((int) $request->input('variant_id'));

                return $line($request, $uuid, ['product_variant_id' => $variant->id], (int) $variant->product->store_id !== (int) $request->user()->store_id);
            });
            Route::post('ivb/{uuid}', function (Request $request, string $uuid) use ($line) {
                $station = \App\Models\KitchenStation::findOrFail((int) $request->input('station_id'));

                return $line($request, $uuid, ['station_id' => $station->id], (int) $station->store_id !== (int) $request->user()->store_id);
            });
            Route::post('ivc/{uuid}', function (Request $request, string $uuid) use ($line) {
                $modifier = \App\Models\Modifier::with('group')->findOrFail((int) $request->input('modifier_id'));

                return $line($request, $uuid, ['modifiers' => [['id' => $modifier->id, 'name' => 'Sous', 'price_delta' => '0']]], (int) $modifier->group->store_id !== (int) $request->user()->store_id);
            });
            // v: B's row deleted before the refusal
            Route::delete('v/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse) {
                $entry = PosWaitlistEntry::where('uuid', $uuid)->firstOrFail();
                $entry->delete();

                return $mine($request, $entry) ? response()->json(['data' => []]) : $refuse();
            });
            // vi-a: A's own order with every store's tables nested
            Route::get('via/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);

                return $mine($request, $order) ? response()->json(['data' => ['uuid' => $order->uuid, 'hall' => ['tables' => DiningTable::query()->orderBy('id')->get(['id', 'seats'])]]]) : $refuse();
            });
            // vi-b: refused, B's store named in a header
            Route::get('vib/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse()->header('X-Store', (string) Store::whereKey($order->store_id)->value('uuid'));
            });
            // vi-c: A's orders, but the total counts every store's
            Route::get('vic', fn (Request $request) => response()->json([
                'data' => Order::where('store_id', $request->user()->store_id)->pluck('uuid'),
                'meta' => ['total' => Order::count()],
            ]));
            // vii-a: refused, but a job run at once changes B
            Route::post('viia/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if ($mine($request, $order)) {
                    return response()->json(['data' => []]);
                }
                dispatch_sync(new LeakyJob((int) $order->id));

                return $refuse();
            });
            // vii-b: refused, but an after-commit callback changes B
            Route::post('viib/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    DB::afterCommit(fn () => Order::whereKey($order->id)->update(['notes' => 'keyin']));
                }

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
            // vii-c: refused, but B's branch cache key written
            Route::post('viic/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    Cache::put("pos-branch.{$order->branch_id}.floor", 'eski', 60);
                }

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
            // viii: refused, but B's store row changed
            Route::post('viii/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    Store::whereKey($order->store_id)->update(['is_open' => false]);
                }

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
            // ix: refused, but B's branch hears about it
            Route::post('ix/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    event(new LeakyBroadcast((int) $order->branch_id));
                }

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
            // x: refused, but B's owner is notified
            Route::post('x/{uuid}', function (Request $request, string $uuid) use ($mine, $refuse, $find) {
                $order = $find($uuid);
                if (!$mine($request, $order)) {
                    \App\Models\User::where('store_id', $order->store_id)->where('role', 'owner')->firstOrFail()->notify(new LeakyNotification());
                }

                return $mine($request, $order) ? response()->json(['data' => []]) : $refuse();
            });
        });
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}

/**
 * vii-a: a job the leaky route runs at once (dispatch_sync).
 */
class LeakyJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public function __construct(private readonly int $orderId) {}

    public function handle(): void
    {
        Order::whereKey($this->orderId)->update(['notes' => 'navbatdan']);
    }
}

/**
 * ix: a broadcast on a branch's till channel.
 */
class LeakyBroadcast implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow
{
    public function __construct(private readonly int $branchId) {}

    /**
     * @return list<\Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new \Illuminate\Broadcasting\PrivateChannel("pos-branch.{$this->branchId}")];
    }
}

/**
 * x: a mail notification.
 */
class LeakyNotification extends \Illuminate\Notifications\Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage())->line('Buyurtma');
    }
}
