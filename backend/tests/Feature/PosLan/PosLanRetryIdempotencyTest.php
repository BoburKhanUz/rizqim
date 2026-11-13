<?php

namespace Tests\Feature\PosLan;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * Review P6 hardware/LAN (C1): a waiter's request reached the server online (Idempotency-Key K), its answer was lost as
 * the internet went, the app switched to the LAN and the same button sent the same request to the hub (op_id = K). When
 * the hub's queue reaches the server the request is applied once: the relayed operation's op_id is the origin device's
 * own request id, so the server treats it as its Idempotency-Key (as `idempotency_key` for the till's own retries).
 */
class PosLanRetryIdempotencyTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    /** @var array{device: \App\Models\Device, token: string} */
    private array $phone;

    private string $phoneToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        $this->phone = $this->pairTerminal($this->owner, $this->branch);
        $this->phoneToken = $this->pinSession($this->phone, $this->waiter);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function relayed(string $type, array $payload, string $opId): array
    {
        $op = $this->op($type, $payload, $this->waiter, 1);
        $op['op_id'] = $opId;
        $op['origin_device'] = (string) $this->phone['device']->uuid;

        return $op;
    }

    /** @return array{0: string, 1: int} the order's uuid and version */
    private function openOnline(): array
    {
        $this->withBearer($this->phoneToken)->withHeader('Idempotency-Key', (string) Str::uuid());
        $opened = $this->json('POST', '/api/v1/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => '1']], 'send' => true,
        ])->assertSuccessful();

        return [(string) $opened->json('data.uuid'), (int) $opened->json('data.version')];
    }

    public function test_a_round_retried_over_the_lan_after_a_lost_online_answer_is_applied_once(): void
    {
        [$uuid, $version] = $this->openOnline();
        // A round of 3 teas online with key K: the server applies it, the answer is lost
        $key = (string) Str::uuid();
        $this->withBearer($this->phoneToken)->withHeader('Idempotency-Key', $key);
        $this->json('POST', "/api/v1/pos/orders/{$uuid}/rounds", [
            'version' => $version, 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => '3']], 'send' => true,
        ])->assertSuccessful();
        $order = Order::where('uuid', $uuid)->sole();
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->count());

        // The same request through the hub (op_id = K) as a hub queued it before this fix: no `idempotency_key`
        $result = $this->sync([$this->relayed('order.round', [
            'order' => $uuid,
            'base_version' => $version,
            'items' => [$this->line($this->tea, '3')],
            'send' => true,
            'view' => ['staff_name' => $this->waiter->name],
        ], $key)])->assertOk()->json('data.results.0');

        $this->assertSame('applied', $result['status'], (string) json_encode($result));
        $this->assertTrue($result['refs']['replayed'] ?? false, (string) json_encode($result));
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->count(), 'round applied twice');
    }

    public function test_a_room_opened_online_and_opened_again_over_the_lan_with_the_same_key_is_one_order(): void
    {
        // The waiter opened room 1 online (key K), the answer was lost; the same button went to the hub: the hub
        // opened it under the op's id (op_id = K, order = K)
        $key = (string) Str::uuid();
        $this->withBearer($this->phoneToken)->withHeader('Idempotency-Key', $key);
        $online = $this->json('POST', '/api/v1/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => '1']], 'send' => true,
        ])->assertSuccessful();
        $uuid = (string) $online->json('data.uuid');

        $open = $this->relayed('order.open', [
            'order' => $key,
            'local_number' => 'K1-501',
            'channel' => 'pos_hall',
            'table_id' => $this->room->id,
            'guests_count' => 2,
            'items' => [$this->line($this->shashlik, '1')],
            'send' => true,
            'waiter_id' => $this->waiter->id,
            'menu_taken_at' => now()->subHour()->toJSON(),
        ], $key);
        // ... and a round on it through the hub, by the hub's id of the order
        $round = $this->relayed('order.round', [
            'order' => $key,
            'items' => [$this->line($this->tea, '2')],
            'send' => true,
        ], (string) Str::uuid());
        $round['seq'] = $open['seq'] + 1;
        $results = $this->sync([$open, $round])->assertOk()->json('data.results');

        $this->assertSame('applied', $results[0]['status'], (string) json_encode($results[0]));
        $this->assertSame($uuid, $results[0]['refs']['order_uuid'], 'a second order was opened: ' . json_encode($results[0]));
        $this->assertSame([], $results[0]['refs']['flags'] ?? [], (string) json_encode($results[0]));
        $this->assertSame(1, Order::where('store_id', $this->store->id)->where('table_id', $this->room->id)->count());
        $this->assertSame('applied', $results[1]['status'], (string) json_encode($results[1]));
        $order = Order::where('uuid', $uuid)->sole();
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->count());
    }

    public function test_a_relayed_op_whose_id_is_no_online_request_is_applied_normally(): void
    {
        [$uuid] = $this->openOnline();
        $order = Order::where('uuid', $uuid)->sole();
        $result = $this->sync([$this->relayed('order.round', [
            'order' => $uuid,
            'items' => [$this->line($this->tea, '1')],
            'send' => true,
        ], (string) Str::uuid())])->assertOk()->json('data.results.0');

        $this->assertSame('applied', $result['status'], (string) json_encode($result));
        $this->assertArrayNotHasKey('replayed', $result['refs']);
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->count());
    }
}
