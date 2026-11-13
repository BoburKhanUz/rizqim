<?php

namespace Tests\Feature\PosLan;

use App\Enums\DeviceType;
use App\Enums\KitchenItemStatus;
use App\Enums\UserRole;
use App\Models\BranchLanSettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5): the branch's hub sends what it took over the LAN with `origin_device`; the
 * server applies it as the origin terminal's (the staff member signed in there, the order's device) only from the
 * hub, for a terminal of the same branch, never money; spoofed relays land in "Sinxronlash xatolari".
 */
class PosLanRelayTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    /** @var array{device: \App\Models\Device, token: string} */
    private array $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        // The till paired first is the hub; the waiter signed in on their phone before the outage
        $this->phone = $this->pairTerminal($this->owner, $this->branch);
        $this->pinSession($this->phone, $this->waiter);
    }

    /**
     * An operation the waiter did on their phone, relayed by a hub.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function relayed(string $type, array $payload, ?string $origin = null, ?\App\Models\User $staff = null, int $minutesAgo = 10): array
    {
        return $this->op($type, $payload, $staff ?? $this->waiter, $minutesAgo) + ['origin_device' => $origin ?? (string) $this->phone['device']->uuid];
    }

    /**
     * @return array<string, mixed>
     */
    private function openPayload(?string $order = null, bool $room = true): array
    {
        return [
            'order' => $order ?? (string) Str::uuid(),
            'local_number' => 'K1-' . random_int(100, 999),
            'channel' => 'pos_hall',
            'table_id' => $room ? $this->room->id : null,
            'guests_count' => 3,
            'items' => [$this->line($this->shashlik, '2')],
            'send' => true,
            'menu_taken_at' => now()->subHour()->toJSON(),
        ];
    }

    public function test_the_hub_relays_a_waiters_order_and_it_is_the_phones(): void
    {
        $payload = $this->openPayload();
        $results = $this->sync([$this->relayed('order.open', $payload)])->assertOk()->json('data.results');
        $this->assertSame('applied', $results[0]['status'], (string) json_encode($results[0]));

        $order = Order::where('external_id', $payload['order'])->sole();
        $this->assertSame((int) $this->phone['device']->id, (int) $order->device_id);
        $this->assertSame($this->waiter->id, (int) $order->waiter_id);
        $this->assertSame($this->waiter->id, (int) $order->user_id);

        $row = PosSyncOperation::where('op_id', $results[0]['op_id'])->sole();
        $this->assertSame((int) $this->till['device']->id, (int) $row->device_id);
        $this->assertSame((int) $this->phone['device']->id, (int) $row->origin_device_id);
        $this->assertSame($this->waiter->id, (int) $row->staff_id);

        // The same relay again: applied once
        $again = $this->sync([array_merge($this->relayed('order.open', $payload), ['op_id' => $row->op_id, 'seq' => $row->seq])]);
        $this->assertTrue($again->json('data.results.0.duplicate'));
        $this->assertSame(1, Order::where('external_id', $payload['order'])->count());
    }

    public function test_a_till_that_is_not_the_hub_cannot_relay(): void
    {
        $other = $this->pairTerminal($this->owner, $this->branch);
        $payload = $this->openPayload();

        $result = $this->sync([$this->relayed('order.open', $payload)], [], $other['token'])->assertOk()->json('data.results.0');
        $this->assertSame('rejected', $result['status']);
        $this->assertSame('relay_not_hub', $result['reason']);
        $this->assertSame((string) $this->phone['device']->uuid, $result['refs']['origin_device']);
        $this->assertFalse(Order::where('external_id', $payload['order'])->exists());

        // The phone itself is not the hub either (and a waiter's phone never pretends to be someone else's)
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $result = $this->sync([$this->relayed('order.open', $this->openPayload(), (string) $kds['device']->uuid)], [], $this->phone['token'])->json('data.results.0');
        $this->assertSame('relay_not_hub', $result['reason']);
    }

    public function test_an_unknown_or_another_branchs_or_a_revoked_origin_is_rejected(): void
    {
        $unknown = $this->sync([$this->relayed('order.open', $this->openPayload(), (string) Str::uuid())])->json('data.results.0');
        $this->assertSame(['rejected', 'origin_unknown'], [$unknown['status'], $unknown['reason']]);

        $elsewhere = $this->pairTerminal($this->owner, $this->otherBranch);
        $theirs = $this->makeStaff(UserRole::WAITER, $this->otherBranch);
        $this->pinSession($elsewhere, $theirs);
        $wrong = $this->sync([$this->relayed('order.open', $this->openPayload(), (string) $elsewhere['device']->uuid, $theirs)])->json('data.results.0');
        $this->assertSame(['rejected', 'origin_wrong_branch'], [$wrong['status'], $wrong['reason']]);

        // Another store's terminal looks unknown (its existence is not told)
        $this->setUpLifecycleStoreOther();
        $foreign = $this->sync([$this->relayed('order.open', $this->openPayload(), (string) $this->foreignTerminal['device']->uuid)])->json('data.results.0');
        $this->assertSame('origin_unknown', $foreign['reason']);

        $this->phone['device']->forceFill(['revoked_at' => now()])->save();
        $revoked = $this->sync([$this->relayed('order.open', $this->openPayload())])->json('data.results.0');
        $this->assertSame(['rejected', 'origin_revoked'], [$revoked['status'], $revoked['reason']]);
        $this->assertSame(0, Order::where('channel', 'pos_hall')->count());
    }

    public function test_the_staff_member_must_have_signed_in_on_the_origin_and_hold_the_permission(): void
    {
        // The cashier signed in on the hub, not on the phone
        $result = $this->sync([$this->relayed('order.open', $this->openPayload(), null, $this->cashier)])->json('data.results.0');
        $this->assertSame(['rejected', 'staff_not_signed_in'], [$result['status'], $result['reason']]);

        // A cook signed in on the KDS may bump, not open orders
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->pinSession($kds, $cook);
        $result = $this->sync([$this->relayed('order.open', $this->openPayload(), (string) $kds['device']->uuid, $cook)])->json('data.results.0');
        $this->assertSame(['rejected', 'permission_denied'], [$result['status'], $result['reason']]);
    }

    public function test_money_a_bill_and_closing_are_never_relayed(): void
    {
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();
        foreach (['bill.create' => ['order' => $sale['order'], 'bill' => (string) Str::uuid()], 'order.close' => ['order' => $sale['order']], 'bill.pay' => [
            'order' => $sale['order'], 'bill' => (string) Str::uuid(), 'tenders' => [['tender' => 'cash', 'amount' => '41000']],
        ]] as $type => $payload) {
            $result = $this->sync([$this->relayed($type, $payload)])->json('data.results.0');
            $this->assertSame(['rejected', 'relay_not_allowed'], [$result['status'], $result['reason']], $type);
        }
        $this->assertSame(0, \App\Models\Payment::count());
    }

    public function test_the_kitchen_bump_and_the_bill_request_come_through_the_hub(): void
    {
        $payload = $this->openPayload();
        $line = $payload['items'][0]['line_id'];
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->pinSession($kds, $cook);

        $results = $this->sync([
            $this->relayed('order.open', $payload, null, null, 30),
            $this->relayed('kitchen.bump', ['order' => $payload['order'], 'line' => $line], (string) $kds['device']->uuid, $cook, 20),
            $this->relayed('bill.request', ['order' => $payload['order']], null, null, 15),
            // The same bump again changes nothing
            $this->relayed('kitchen.bump', ['order' => $payload['order'], 'line' => $line], (string) $kds['device']->uuid, $cook, 14),
        ])->assertOk()->json('data.results');
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($results, 'status'), (string) json_encode($results));
        $this->assertFalse($results[3]['refs']['changed']);

        $order = Order::where('external_id', $payload['order'])->sole();
        $item = OrderItem::where('order_id', $order->id)->where('client_line_id', $line)->sole();
        $this->assertSame(KitchenItemStatus::READY, $item->kitchen_status);
        $this->assertNotNull($order->bill_requested_at);
        $this->assertSame($this->waiter->id, (int) $order->bill_requested_by);
        $this->assertSame((int) $kds['device']->id, (int) PosSyncOperation::where('type', 'kitchen.bump')->firstOrFail()->origin_device_id);

        // Recalled from the KDS: cooking again
        $recall = $this->sync([$this->relayed('kitchen.recall', ['order' => $payload['order'], 'line' => $line], (string) $kds['device']->uuid, $cook, 5)])->json('data.results.0');
        $this->assertSame('applied', $recall['status']);
        $this->assertSame(KitchenItemStatus::SENT, $item->refresh()->kitchen_status);
    }

    public function test_the_previous_hub_is_taken_for_what_happened_before_the_hub_moved(): void
    {
        // The till got the hub's secret, then the owner moved the hub to another till, which took it
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.lan.role', 'hub');
        $next = $this->pairTerminal($this->owner, $this->branch);
        $nextToken = $this->pinSession($next, $this->manager, self::MANAGER_PIN);
        BranchLanSettings::where('branch_id', $this->branch->id)->update(['hub_device_id' => $next['device']->id]);
        Carbon::setTestNow(now()->addMinutes(5));
        $this->tillAs('GET', '/pos/bootstrap', [], $nextToken)->assertOk()->assertJsonPath('data.lan.role', 'hub');

        // Done on the phone before the move (15 minutes ago): still the old hub's to send
        $before = $this->sync([$this->relayed('order.open', $this->openPayload(null, false), null, null, 15)])->json('data.results.0');
        $this->assertSame('applied', $before['status'], (string) json_encode($before));
        // After the move: only the new hub
        Carbon::setTestNow(now()->addMinutes(10));
        $after = $this->sync([$this->relayed('order.open', $this->openPayload(null, false), null, null, 1)])->json('data.results.0');
        $this->assertSame('relay_not_hub', $after['reason']);
        $this->assertSame('applied', $this->sync([$this->relayed('order.open', $this->openPayload(null, false), null, null, 1)], [], $next['token'])->json('data.results.0.status'));
    }

    public function test_the_sync_errors_show_the_origin_device(): void
    {
        $this->sync([$this->relayed('order.round', ['order' => (string) Str::uuid(), 'items' => [$this->line($this->tea, '1')]])])->assertOk();

        $this->withBearer($this->manager->createToken('web')->plainTextToken);
        $error = $this->getJson('/api/v1/pos/sync/errors')->assertOk()->json('data.errors.0');
        $this->assertSame('order_not_found', $error['reason']);
        $this->assertSame((string) $this->phone['device']->uuid, $error['origin_device']['uuid']);
        $this->assertSame((string) $this->till['device']->uuid, $error['device']['uuid']);
    }

    /** @var array{device: \App\Models\Device, token: string} */
    private array $foreignTerminal;

    private function setUpLifecycleStoreOther(): void
    {
        $store = $this->makeStore('Boshqa do\'kon');
        $branch = $this->makeBranch($store, 'Boshqa filial');
        $owner = $this->makeStaff(UserRole::OWNER, null, $store);
        $this->foreignTerminal = $this->pairTerminal($owner, $branch);
    }
}
