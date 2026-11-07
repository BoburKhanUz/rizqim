<?php

namespace Tests\Feature\PosLan;

use App\Domain\Pos\Lan\BranchLan;
use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Events\Broadcast\BillPrintRequestedBroadcast;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Services\Auth\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * P6 stage B (docs/POS_SPEC.md §5.5): the apps say which app pairs (`app`), so the branch's default LAN hub is a
 * cashier till, never a waiter's phone; and a bill request the hub printed itself over the LAN (`printed_on_lan`) is
 * not printed again by the server when the hub's queue reaches it.
 */
class PosLanHubAppTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * @return array{device: Device, token: string}
     */
    private function pairApp(?Branch $branch, ?string $app, DeviceType $type = DeviceType::POS): array
    {
        $service = app(DevicePairingService::class);
        $code = $service->createCode($this->owner->store, $branch, $type, 'Qurilma', $this->owner)['code'];

        return $service->pair($code, ['platform' => 'android', 'app' => $app]);
    }

    private function code(DeviceType $type): string
    {
        return app(DevicePairingService::class)->createCode($this->owner->store, $this->branch, $type, 'Qurilma', $this->owner)['code'];
    }

    public function test_the_app_is_recorded_at_pairing_when_it_fits_the_terminal_type(): void
    {
        $this->withBearer(null);
        $uuid = $this->postJson('/api/v1/devices/pair', ['code' => $this->code(DeviceType::POS), 'app' => 'waiter'])->assertCreated()->json('data.device.uuid');
        $this->assertSame(DeviceApp::WAITER, Device::where('uuid', $uuid)->sole()->app);

        $this->withBearer(null);
        $uuid = $this->postJson('/api/v1/devices/pair', ['code' => $this->code(DeviceType::DISPLAY), 'app' => 'customer_display'])->assertCreated()->json('data.device.uuid');
        $this->assertSame(DeviceApp::CUSTOMER_DISPLAY, Device::where('uuid', $uuid)->sole()->app);

        // A kitchen app on a till's code: paired as before, without an app
        $this->withBearer(null);
        $uuid = $this->postJson('/api/v1/devices/pair', ['code' => $this->code(DeviceType::POS), 'app' => 'kds'])->assertCreated()->json('data.device.uuid');
        $this->assertNull(Device::where('uuid', $uuid)->sole()->app);

        // An older app sends none
        $this->withBearer(null);
        $uuid = $this->postJson('/api/v1/devices/pair', ['code' => $this->code(DeviceType::KDS)])->assertCreated()->json('data.device.uuid');
        $this->assertNull(Device::where('uuid', $uuid)->sole()->app);

        // An unknown app is refused (the code stays usable)
        $kept = $this->code(DeviceType::POS);
        $this->withBearer(null);
        $this->postJson('/api/v1/devices/pair', ['code' => $kept, 'app' => 'kassa'])->assertUnprocessable()->assertJsonValidationErrors('app');
        $this->withBearer(null);
        $uuid = $this->postJson('/api/v1/devices/pair', ['code' => $kept, 'app' => 'cashier'])->assertCreated()->json('data.device.uuid');
        $this->assertSame(DeviceApp::CASHIER, Device::where('uuid', $uuid)->sole()->app);
    }

    public function test_the_default_hub_is_a_cashier_till_never_a_waiters_phone(): void
    {
        $lan = app(BranchLan::class);
        $branch = $this->otherBranch;

        // Only a waiter's phone: no hub at all
        $phone = $this->pairApp($branch, 'waiter');
        $this->assertNull($lan->hubOf($branch));
        $this->assertSame([], $lan->candidates($branch)->pluck('uuid')->all());

        // A till paired before the app was recorded: the hub while it is the only one
        $legacy = $this->pairApp($branch, null);
        $this->assertSame((string) $legacy['device']->uuid, (string) $lan->hubOf($branch)?->uuid);

        // A cashier till: the default hub (its app is known), the phone still never a candidate
        $till = $this->pairApp($branch, 'cashier');
        $this->assertSame((string) $till['device']->uuid, (string) $lan->hubOf($branch)?->uuid);
        $this->assertSame(
            [(string) $legacy['device']->uuid, (string) $till['device']->uuid],
            $lan->candidates($branch)->map(fn (Device $device) => (string) $device->uuid)->all(),
        );

        // The owner sees the app of each candidate and cannot pick the phone
        $this->withBearer($this->owner->createToken('web')->plainTextToken);
        $view = $this->getJson('/api/v1/owner/pos/lan?branch_id=' . $branch->id)->assertOk()->json('data');
        $this->assertSame((string) $till['device']->uuid, $view['hub']['device']);
        $this->assertFalse($view['hub']['chosen']);
        $this->assertSame([null, 'cashier'], array_column($view['candidates'], 'app'));
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $branch->id, 'hub_device_id' => (string) $phone['device']->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('hub_device_id');
        // The owner's choice still wins
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $branch->id, 'hub_device_id' => (string) $legacy['device']->uuid])
            ->assertOk()->assertJsonPath('data.hub.device', (string) $legacy['device']->uuid)->assertJsonPath('data.hub.chosen', true);

        // The phone's own bootstrap names the chosen hub and makes it a client
        $this->withBearer($this->pinSession($phone, $this->makeStaff(UserRole::WAITER, $branch)));
        $this->getJson('/api/v1/pos/bootstrap')->assertOk()
            ->assertJsonPath('data.lan.role', 'client')
            ->assertJsonPath('data.lan.hub.device', (string) $legacy['device']->uuid);
    }

    public function test_a_bill_request_the_hub_printed_over_the_lan_is_not_printed_again(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $phone = $this->pairApp($this->branch, 'waiter');
        $this->pinSession($phone, $this->waiter);
        $origin = (string) $phone['device']->uuid;

        $open = function (): array {
            $order = (string) Str::uuid();

            return [$order, [
                'order' => $order,
                'local_number' => 'K1-' . random_int(100, 999),
                'channel' => 'pos_hall',
                'table_id' => null,
                'guests_count' => 2,
                'items' => [$this->line($this->shashlik, '2')],
                'send' => true,
                'menu_taken_at' => now()->subHour()->toJSON(),
            ]];
        };
        [$printed, $first] = $open();
        [$asked, $second] = $open();

        $results = $this->sync([
            $this->op('order.open', $first, $this->waiter, 30) + ['origin_device' => $origin],
            // The hub was offline and printed the pre-check itself
            $this->op('bill.request', ['order' => $printed, 'printed_on_lan' => true], $this->waiter, 20) + ['origin_device' => $origin],
            $this->op('order.open', $second, $this->waiter, 15) + ['origin_device' => $origin],
            // Relayed while the hub was online: the server prints as usual
            $this->op('bill.request', ['order' => $asked], $this->waiter, 1) + ['origin_device' => $origin],
        ])->assertOk()->json('data.results');
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($results, 'status'), (string) json_encode($results));

        $printedOrder = Order::where('external_id', $printed)->sole();
        $askedOrder = Order::where('external_id', $asked)->sole();
        $this->assertNotNull($printedOrder->bill_requested_at);
        $this->assertSame(0, OrderBill::where('order_id', $printedOrder->id)->count(), 'no bill is made for a pre-check the hub printed');
        $this->assertSame(1, OrderBill::where('order_id', $askedOrder->id)->count());
        Event::assertDispatchedTimes(BillPrintRequestedBroadcast::class, 1);
        Event::assertDispatched(
            BillPrintRequestedBroadcast::class,
            fn (BillPrintRequestedBroadcast $event) => $event->broadcastWith()['order']['uuid'] === (string) $askedOrder->uuid,
        );
    }
}
