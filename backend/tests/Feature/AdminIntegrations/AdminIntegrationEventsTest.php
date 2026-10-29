<?php

namespace Tests\Feature\AdminIntegrations;

use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationEvent;
use App\Domain\Integrations\IntegrationEventStatus;
use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * P4-31 Integratsiyalar → Hodisalar jurnali (filters, cursor, bodies through AuditRedactor) and Qotib qolganlar (an
 * order push not confirmed for 2 minutes while the order is open), also in "Bugun → Diqqat talab qiladi".
 */
class AdminIntegrationEventsTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    private Store $store;

    private IntegrationConnection $connection;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Lazzat Oshxona', 'slug' => 'lazzat', 'commission_rate' => 10, 'status' => 'active']);
        $this->connection = IntegrationConnection::factory()->create(['store_id' => $this->store->id]);
        $this->customer = User::create(['name' => 'Mijoz', 'phone' => '+998901112233']);
    }

    private function order(OrderStatus $status = OrderStatus::NEW): Order
    {
        return Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'status' => $status,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 2500,
        ]);
    }

    public function test_the_journal_filters_pages_by_cursor_and_redacts_bodies(): void
    {
        $other = IntegrationConnection::factory()->create();
        $old = IntegrationEvent::factory()->create(['integration_connection_id' => $this->connection->id, 'type' => 'menu.sync', 'created_at' => now()->subDays(3)]);
        $failed = IntegrationEvent::factory()->create([
            'integration_connection_id' => $this->connection->id,
            'status' => IntegrationEventStatus::FAILED,
            'http_status' => 502,
            'attempts' => 3,
            'next_attempt_at' => now()->addMinutes(4),
            'error' => 'Timeout for +998901112233',
            'request_body' => ['eatsId' => 'R-1', 'customer' => ['name' => 'Mijoz', 'phone' => '+998901112233'], 'access_token' => 'tok_live_12345'],
            'response_body' => ['error' => 'Bad gateway'],
            'signature_valid' => true,
        ]);
        $inbound = IntegrationEvent::factory()->create(['integration_connection_id' => $other->id, 'direction' => 'inbound', 'type' => 'webhook.status']);
        IntegrationEvent::factory()->create(['integration_connection_id' => null, 'direction' => 'inbound', 'type' => 'webhook.status', 'status' => IntegrationEventStatus::DEAD]);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $response = $this->adminJson('GET', '/integrations/events', $token)->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(4, $rows);
        $this->assertSame($failed->uuid, $rows[2]['uuid']);
        $this->assertSame([502, 3, 'Xato', 'Timeout for +99890*****33'], [$rows[2]['http_status'], $rows[2]['attempts'], $rows[2]['status_label'], $rows[2]['error']]);
        $this->assertNull($rows[0]['connection']);
        $this->assertArrayNotHasKey('request_body', $rows[2]);
        $this->assertStringNotContainsString('tok_live', $response->getContent());

        $uuids = fn (string $query) => array_column($this->adminJson('GET', '/integrations/events' . $query, $token)->assertOk()->json('data.data'), 'uuid');
        $this->assertSame([$failed->uuid, $old->uuid], $uuids("?connection={$this->connection->uuid}"));
        $this->assertSame([$failed->uuid], $uuids('?status=failed'));
        $this->assertSame([$old->uuid], $uuids('?type=menu.sync'));
        $this->assertSame($inbound->uuid, $uuids('?direction=inbound')[1]);
        $this->assertSame([$failed->uuid, $old->uuid], $uuids("?store_id={$this->store->id}"));
        $this->assertSame([$old->uuid], $uuids('?to=' . now('Asia/Tashkent')->subDays(2)->toDateString()));
        $this->assertNotContains($old->uuid, $uuids('?from=' . now('Asia/Tashkent')->subDay()->toDateString()));
        $this->adminJson('GET', '/integrations/events?from=2026-10-10&to=2026-10-01', $token)->assertStatus(422);

        // Cursor pages
        $first = $this->adminJson('GET', '/integrations/events?per_page=3', $token)->assertOk()->json('data');
        $this->assertCount(3, $first['data']);
        $this->assertNotNull($first['next_cursor']);
        $second = $this->adminJson('GET', '/integrations/events?per_page=3&cursor=' . urlencode($first['next_cursor']), $token)->assertOk()->json('data');
        $this->assertSame([$old->uuid], array_column($second['data'], 'uuid'));
        $this->assertNull($second['next_cursor']);

        // The event with its bodies: secrets dropped, phones masked
        $detail = $this->adminJson('GET', "/integrations/events/{$failed->uuid}", $token)->assertOk()->json('data');
        $this->assertSame(['eatsId' => 'R-1', 'customer' => ['name' => 'Mijoz', 'phone' => '+99890*****33'], 'access_token' => '[redacted]'], $detail['request_body']);
        $this->assertSame(['error' => 'Bad gateway'], $detail['response_body']);
        $this->assertSame([true, $failed->idempotency_key], [$detail['signature_valid'], $detail['idempotency_key']]);
        $this->adminJson('GET', '/integrations/events/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound();

        $this->adminJson('GET', '/integrations/events', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
    }

    public function test_an_unconfirmed_push_of_an_open_order_is_stuck_after_two_minutes(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $this->adminJson('GET', '/integrations/stuck-orders', $token)->assertOk()->assertJsonPath('data.count', 0)->assertJsonPath('data.data', [])->assertJsonPath('data.stuck_after_minutes', 2);

        $push = fn (Order $order, IntegrationEventStatus $status, int $minutesAgo) => IntegrationEvent::factory()->create([
            'integration_connection_id' => $this->connection->id,
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'status' => $status,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);

        $stuck = $this->order();
        $stuckPush = $push($stuck, IntegrationEventStatus::SENT, 7);
        // Too fresh
        $push($this->order(), IntegrationEventStatus::PENDING, 1);
        // Confirmed by a later push
        $retried = $this->order();
        $push($retried, IntegrationEventStatus::FAILED, 9);
        $push($retried, IntegrationEventStatus::ACKNOWLEDGED, 6);
        // The order is over
        $push($this->order(OrderStatus::CANCELED), IntegrationEventStatus::DEAD, 30);

        $data = $this->adminJson('GET', '/integrations/stuck-orders', $token)->assertOk()->json('data');
        $this->assertSame(1, $data['count']);
        $this->assertSame([$stuckPush->uuid, $stuck->uuid, 'new', 'Yuborildi', 7], [$data['data'][0]['event_uuid'], $data['data'][0]['order']['uuid'], $data['data'][0]['order']['status'], $data['data'][0]['status_label'], $data['data'][0]['waiting_minutes']]);
        $this->assertSame('Alipos', $data['data'][0]['connection']['provider_label']);

        // The connection list and card count it; Bugun shows it to whoever sees integrations
        $this->adminJson('GET', '/integrations/connections', $token)->assertOk()->assertJsonPath('data.stuck_orders', 1);
        $this->adminJson('GET', "/integrations/connections/{$this->connection->uuid}", $token)->assertOk()->assertJsonPath('data.stuck_orders', 1);

        $groups = collect($this->adminJson('GET', '/today/attention', $token)->assertOk()->json('data.groups'))->keyBy('kind');
        $this->assertSame([1, 'critical', "Buyurtma {$stuck->short_code}", '/integrations?tab=stuck'], [
            $groups['integration_order_stuck']['count'], $groups['integration_order_stuck']['severity'],
            $groups['integration_order_stuck']['items'][0]['title'], $groups['integration_order_stuck']['items'][0]['href'],
        ]);

        // Support has no integrations.view: neither the list nor the attention row
        $support = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $this->adminJson('GET', '/integrations/stuck-orders', $support)->assertForbidden();
    }
}
