<?php

namespace Tests\Feature\PosDisplay;

use App\Enums\DeviceType;
use App\Events\Broadcast\DisplayCartBroadcast;
use App\Models\Device;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\TerminalCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * P6-14 (A3): the customer display. A `display` terminal is paired with a code like a kassa or a KDS but is free (never
 * counted toward the plan's devices), has no staff, reads the pickup board and the cart a till publishes with its own
 * terminal token, and joins the branch channel. Gated by the customer_display and queue_calling modules.
 */
class PosDisplayTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    /** @var array{device: Device, token: string} */
    private array $display;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
        $this->display = $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY);
    }

    private function as(string $token, string $method, string $uri, array $data = [])
    {
        return $this->withBearer($token)->json($method, '/api/v1' . $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function cart(int $seq = 1, array $override = []): array
    {
        return array_replace([
            'state' => 'cart',
            'seq' => $seq,
            'session' => 'run-1',
            'lines' => [
                ['name' => 'Lag\'mon', 'qty' => '2', 'unit' => null, 'price' => '32000', 'total' => '64000', 'modifiers' => ['Achchiq'], 'note' => null],
                ['name' => 'Choy', 'qty' => '1', 'price' => '5000', 'total' => '5000'],
            ],
            'subtotal' => '69000',
            'discount' => '0',
            'total' => '69000',
            'queue_number' => 17,
        ], $override);
    }

    public function test_the_owner_pairs_a_display_for_a_branch_with_an_optional_till(): void
    {
        $code = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', [
            'type' => 'display', 'branch_id' => $this->branch->id, 'name' => 'Ikkinchi ekran', 'till_uuid' => $this->till['device']->uuid,
        ])->assertCreated()->assertJsonPath('data.type', 'display')->json('data.code');

        $paired = $this->withBearer(null)->postJson('/api/v1/devices/pair', ['code' => $code])->assertCreated()
            ->assertJsonPath('data.device.type', 'display')->assertJsonPath('data.device.name', 'Ikkinchi ekran')->json('data');
        $device = Device::where('uuid', $paired['device']['uuid'])->firstOrFail();
        $this->assertSame($this->till['device']->id, $device->display_for_device_id);

        $this->as($paired['token'], 'GET', '/pos/display/state')->assertOk()
            ->assertJsonPath('data.till_uuid', $this->till['device']->uuid)
            ->assertJsonPath('data.branch.id', $this->branch->id)
            ->assertJsonPath('data.modules.customer_display', true)
            ->assertJsonPath('data.modules.queue_calling', true)
            ->assertJsonPath('data.cart', null);

        // A display needs a branch, a till of that branch, and a format that has a display or a queue
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', ['type' => 'display'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'branch_required');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', [
            'type' => 'display', 'branch_id' => $this->branch->id, 'till_uuid' => $this->display['device']->uuid,
        ])->assertStatus(422)->assertJsonPath('data.reason', 'till_not_found');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', [
            'type' => 'display', 'branch_id' => $this->branch->id, 'till_uuid' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonPath('data.reason', 'till_not_found');

        $this->useProfile($this->branch, 'choyxona');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', ['type' => 'display', 'branch_id' => $this->branch->id])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled');
    }

    public function test_a_display_is_free_and_never_counted(): void
    {
        $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY);

        $counter = app(TerminalCounter::class);
        $this->assertSame(2, $counter->activeNow($this->store->id, [DeviceType::DISPLAY]), 'two displays exist');
        // Default: only the billed terminals (the two tills of the fixture)
        $this->assertSame(2, $counter->activeNow($this->store->id));
        $this->assertSame(2, $counter->maxConcurrent($this->store->id, now()->subDay(), now()->addDay()));

        $devices = app(Entitlements::class)->devices($this->store);
        $this->assertSame(['included' => null, 'unlimited' => true, 'extra_price' => '0.00', 'can_add' => true], $devices['display']);
        $this->assertArrayNotHasKey('active', $devices['display']);
        app(Entitlements::class)->assertCanPair($this->store, DeviceType::DISPLAY);
        $this->assertNotContains(DeviceType::DISPLAY->value, DeviceType::personalValues());
    }

    public function test_a_display_has_no_staff_and_no_pin_pad(): void
    {
        $token = $this->display['token'];
        $uuid = $this->display['device']->uuid;

        $this->as($token, 'GET', '/terminal')->assertOk()->assertJsonPath('data.device.type', 'display');
        $this->as($token, 'GET', '/terminal/staff')->assertForbidden()->assertJsonPath('data.reason', 'display_has_no_staff');
        $this->as($token, 'POST', "/devices/{$uuid}/staff-session", ['user_id' => $this->cashier->id, 'pin' => '7301'])
            ->assertForbidden()->assertJsonPath('data.reason', 'display_has_no_staff');
    }

    public function test_the_till_publishes_the_cart_and_the_display_reads_it_back(): void
    {
        Event::fake([DisplayCartBroadcast::class]);
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertOk()->assertJsonPath('data.cart', null);

        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(1))->assertOk()->assertJsonPath('data.stored', true);
        Event::assertDispatched(DisplayCartBroadcast::class, function (DisplayCartBroadcast $event) {
            $payload = $event->broadcastWith();

            // A hint only: ids, a sequence and the state; no names or prices
            return $event->broadcastAs() === 'display.cart'
                && $event->broadcastOn()[0]->name === "private-pos-branch.{$this->branch->id}"
                && $payload === ['terminal' => $this->till['device']->uuid, 'seq' => 1, 'state' => 'cart', 'branch_id' => $this->branch->id, 'store_id' => $this->store->id];
        });

        $state = $this->as($this->display['token'], 'GET', '/pos/display/state')->assertOk()->json('data.cart');
        $this->assertSame('cart', $state['state']);
        $this->assertSame($this->till['device']->uuid, $state['terminal']);
        $this->assertSame('69000', $state['total']);
        $this->assertSame(17, $state['queue_number']);
        $this->assertSame('Lag\'mon', $state['lines'][0]['name']);
        $this->assertSame(['Achchiq'], $state['lines'][0]['modifiers']);
        $this->assertSame([], $state['lines'][1]['modifiers']);

        // The payment screen with a QR, then "Rahmat", then the next customer
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(2, [
            'state' => 'payment',
            'payment' => ['method' => 'qr', 'qr' => ['payload' => 'https://pay.example/uz/abc', 'label' => 'Click', 'provider' => 'click']],
        ]))->assertOk();
        $payment = $this->as($this->display['token'], 'GET', '/pos/display/state')->json('data.cart');
        $this->assertSame('payment', $payment['state']);
        $this->assertSame('https://pay.example/uz/abc', $payment['payment']['qr']['payload']);

        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(3, ['state' => 'paid', 'payment' => ['method' => 'cash', 'tendered' => '100000', 'change' => '31000']]))->assertOk();
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.state', 'paid')->assertJsonPath('data.cart.payment.change', '31000');

        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(4, ['state' => 'idle', 'lines' => [], 'subtotal' => '0', 'total' => '0', 'queue_number' => null]))->assertOk();
        $idle = $this->as($this->display['token'], 'GET', '/pos/display/state')->json('data.cart');
        $this->assertSame('idle', $idle['state']);
        $this->assertSame([], $idle['lines']);
    }

    public function test_a_late_older_publish_is_dropped_but_a_restarted_till_is_not(): void
    {
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(5))->assertOk()->assertJsonPath('data.stored', true);
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(3, ['total' => '1']))->assertOk()->assertJsonPath('data.stored', false);
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.seq', 5)->assertJsonPath('data.cart.total', '69000');

        // The till's app restarted: a new session starts again from 1
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(1, ['session' => 'run-2', 'total' => '2000']))->assertOk()->assertJsonPath('data.stored', true);
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.total', '2000');
    }

    public function test_a_display_follows_its_own_till_or_the_latest_one(): void
    {
        $follower = $this->pairFollower($this->till['device']);
        $this->as($this->till2['token'], 'POST', '/pos/display/cart', $this->cart(1, ['total' => '11000', 'queue_number' => 2]))->assertOk();

        // The free display shows the branch's latest cart, the follower waits for its own till
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.queue_number', 2);
        $this->as($follower['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart', null);

        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart(1, ['queue_number' => 3]))->assertOk();
        $this->as($follower['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.queue_number', 3);
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertJsonPath('data.cart.queue_number', 3);
    }

    public function test_only_a_till_publishes_and_staff_tokens_do_not_reach_the_display_routes(): void
    {
        $this->as($this->display['token'], 'POST', '/pos/display/cart', $this->cart())->assertForbidden()->assertJsonPath('data.reason', 'till_required');
        $this->as($this->cashierToken, 'GET', '/pos/display/state')->assertUnauthorized();
        $this->as($this->cashierToken, 'POST', '/pos/display/cart', $this->cart())->assertUnauthorized();
        $this->withBearer(null)->getJson('/api/v1/pos/display/state')->assertUnauthorized();
    }

    public function test_the_cart_is_validated(): void
    {
        $token = $this->till['token'];
        $this->as($token, 'POST', '/pos/display/cart', $this->cart(1, ['state' => 'weird']))->assertStatus(422);
        $this->as($token, 'POST', '/pos/display/cart', $this->cart(1, ['total' => '12,5']))->assertStatus(422);
        $this->as($token, 'POST', '/pos/display/cart', $this->cart(1, ['total' => '-5']))->assertStatus(422);
        $this->as($token, 'POST', '/pos/display/cart', $this->cart(1, ['payment' => ['qr' => ['label' => 'no payload']]]))->assertStatus(422);
        $this->as($token, 'POST', '/pos/display/cart', $this->cart(1, ['lines' => [['name' => str_repeat('x', 200), 'qty' => '1', 'price' => '1', 'total' => '1']]]))->assertStatus(422);
        $this->as($token, 'POST', '/pos/display/cart', array_diff_key($this->cart(), ['lines' => 1]))->assertStatus(422);
    }

    public function test_the_cart_needs_the_customer_display_module_and_the_board_the_queue_module(): void
    {
        // Osh markazi: numbers and calling, no customer display unless the owner switches it on
        $this->useProfile($this->branch, 'osh_markazi');
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart())->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'customer_display');
        $this->as($this->display['token'], 'GET', '/pos/display/queue')->assertOk();
        $this->as($this->display['token'], 'GET', '/pos/display/state')->assertOk()
            ->assertJsonPath('data.modules.customer_display', false)->assertJsonPath('data.modules.queue_calling', true)->assertJsonPath('data.cart', null);

        // Stolovaya: a customer display, no queue
        $this->useProfile($this->branch, 'stolovaya');
        $this->as($this->till['token'], 'POST', '/pos/display/cart', $this->cart())->assertOk();
        $this->as($this->display['token'], 'GET', '/pos/display/queue')->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'queue_calling');
    }

    public function test_the_display_reads_the_pickup_board_with_its_terminal_token(): void
    {
        $uuid = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');

        $this->as($this->display['token'], 'GET', '/pos/display/queue')->assertOk()
            ->assertJsonPath('data.business_date', '2026-10-22')
            ->assertJsonPath('data.preparing.0.order_uuid', $uuid)
            ->assertJsonPath('data.preparing.0.number', 1)
            ->assertJsonCount(0, 'data.ready');

        $this->bumpAll($uuid);
        $this->as($this->display['token'], 'GET', '/pos/display/queue')->assertOk()
            ->assertJsonCount(0, 'data.preparing')->assertJsonPath('data.ready.0.number', 1);
    }

    public function test_a_display_without_a_branch_is_asked_to_be_paired_again(): void
    {
        $storeWide = $this->pairTerminal($this->owner, null, DeviceType::DISPLAY);
        $this->as($storeWide['token'], 'GET', '/pos/display/state')->assertStatus(422)->assertJsonPath('data.reason', 'branch_required');
        $this->as($storeWide['token'], 'GET', '/pos/display/queue')->assertStatus(422)->assertJsonPath('data.reason', 'branch_required');
    }

    public function test_a_display_joins_only_its_own_branch_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->withBearer($this->display['token'])->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-pos-branch.{$this->branch->id}"])->assertOk();
        $this->withBearer($this->display['token'])->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-pos-branch.{$this->otherBranch->id}"])->assertForbidden();
    }

    /**
     * @return array{device: Device, token: string}
     */
    private function pairFollower(Device $till): array
    {
        $service = app(\App\Services\Auth\DevicePairingService::class);
        $code = $service->createCode($this->owner->store, $this->branch, DeviceType::DISPLAY, 'Kassa 1 ekrani', $this->owner, $till)['code'];

        return $service->pair($code, ['platform' => 'android']);
    }
}
