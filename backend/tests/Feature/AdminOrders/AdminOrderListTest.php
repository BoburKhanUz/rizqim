<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use App\Services\Admin\Orders\AdminOrderQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * P4-14: GET /admin/orders (cross-store list, filters, counts, server-side SLA) and GET /admin/orders/{uuid} (the card).
 */
class AdminOrderListTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        Cache::flush();
    }

    /**
     * P4-33 (ported from the old superadmin panel's order tabs): every status lands in exactly one stage group, and
     * `active` is every status outside done and canceled.
     */
    public function test_every_status_lands_in_one_stage_group(): void
    {
        $stages = ['new', 'kitchen', 'en_route', 'done', 'canceled'];
        foreach (OrderStatus::cases() as $status) {
            $in = array_values(array_filter($stages, fn (string $group) => in_array($status->value, AdminOrderQuery::GROUPS[$group], true)));
            $this->assertCount(1, $in, $status->value);
            $this->assertSame($in[0], AdminOrderQuery::groupOf($status->value));
            $this->assertSame(!in_array($in[0], ['done', 'canceled'], true), in_array($status->value, AdminOrderQuery::GROUPS['active'], true), $status->value);
        }
    }

    public function test_viewer_lists_orders_of_every_store_but_not_pos_orders(): void
    {
        $other = $this->makeStore('Ikkinchi Osh');
        $mine = $this->orderWithHistory(2);
        $theirs = $this->orderWithHistory(3, [], [], $other);
        $pos = $this->orderWithHistory(1, [], ['channel' => 'pos_hall']);

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $response = $this->adminJson('GET', '/orders', $token)->assertOk();

        $uuids = collect($response->json('data.data'))->pluck('uuid')->all();
        $this->assertSame([(string) $mine->uuid, (string) $theirs->uuid], $uuids);
        $this->assertNotContains((string) $pos->uuid, $uuids);
        $this->assertSame($other->name, $response->json('data.data.1.store.name'));
        $this->assertSame(2, $response->json('data.counts.active'));
        $this->assertSame(['accept' => 5, 'prepare' => 30, 'deliver' => 45], $response->json('data.sla_limits'));
        $this->assertArrayNotHasKey('customer', $response->json('data.data.0'));

        $this->adminJson('GET', '/orders/' . $pos->uuid, $token)->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_a_member_without_orders_view_is_refused(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::SALES));

        $this->adminJson('GET', '/orders', $token)->assertForbidden();
        $this->adminJson('GET', '/orders/store-quality', $token)->assertForbidden();
        $this->adminJson('GET', '/refund-requests', $token)->assertForbidden();
    }

    public function test_status_groups_channel_store_region_and_counts(): void
    {
        // Done and canceled count only today's orders: midday keeps the orders made 90 minutes ago on the same local day
        $this->travelTo('2026-11-03 07:00:00'); // UTC: 12:00 in Tashkent
        $region = $this->region('Samarqand');
        $this->branch->forceFill(['region_id' => $region->id])->save();
        $other = $this->makeStore('Boshqa');

        $new = $this->orderWithHistory(1);
        $kitchen = $this->orderWithHistory(10, ['accepted' => 9, 'preparing' => 8]);
        $enRoute = $this->orderWithHistory(40, ['accepted' => 39, 'preparing' => 38, 'ready' => 20, 'on_the_way' => 10], ['courier_id' => $this->courier->id]);
        $done = $this->orderWithHistory(90, ['accepted' => 89, 'preparing' => 80, 'ready' => 60, 'on_the_way' => 50, 'delivered' => 30]);
        $canceled = $this->orderWithHistory(5, ['canceled' => 4], ['cancel_reason' => 'other']);
        $mobile = $this->orderWithHistory(2, [], ['channel' => 'mobile'], $other);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $list = fn (string $query) => collect($this->adminJson('GET', '/orders?' . $query, $token)->assertOk()->json('data.data'))->pluck('uuid')->all();

        $this->assertEqualsCanonicalizing([(string) $new->uuid, (string) $mobile->uuid], $list('status=new'));
        $this->assertSame([(string) $kitchen->uuid], $list('status=kitchen'));
        $this->assertSame([(string) $enRoute->uuid], $list('status=en_route'));
        $this->assertSame([(string) $done->uuid], $list('status=done'));
        $this->assertSame([(string) $canceled->uuid], $list('status=canceled'));
        $this->assertSame([(string) $mobile->uuid], $list('channel=mobile'));
        $this->assertSame([(string) $mobile->uuid], $list('store_id=' . $other->id));
        $this->assertNotContains((string) $mobile->uuid, $list('region_id=' . $region->id));
        $this->assertContains((string) $new->uuid, $list('region_id=' . $region->id));

        $counts = $this->adminJson('GET', '/orders', $token)->json('data.counts');
        $this->assertSame(['active' => 4, 'new' => 2, 'kitchen' => 1, 'en_route' => 1, 'done' => 1, 'canceled' => 1], $counts);
        // Counts follow the other filters, not the group
        $this->assertSame(1, $this->adminJson('GET', '/orders?status=done&store_id=' . $other->id, $token)->json('data.counts.new'));

        $this->adminJson('GET', '/orders?status=everything', $token)->assertStatus(422);
        $this->adminJson('GET', '/orders?channel=pos_hall', $token)->assertStatus(422);
    }

    public function test_search_by_code_uuid_prefix_and_customer_phone(): void
    {
        $order = $this->orderWithHistory(1);
        $order->forceFill(['short_code' => 'ORD-ABC123'])->saveQuietly();
        $this->customer->forceFill(['phone' => '+998935551122'])->save();
        $otherCustomer = $this->makeCustomer(0, ['phone' => '+998901234567']);
        $other = $this->orderWithHistory(1, [], ['user_id' => $otherCustomer->id]);

        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $list = fn (string $q) => collect($this->adminJson('GET', '/orders?q=' . urlencode($q), $token)->assertOk()->json('data.data'))->pluck('uuid')->all();

        $this->assertSame([(string) $order->uuid], $list('#ord-abc'));
        $this->assertSame([(string) $other->uuid], $list(substr((string) $other->uuid, 0, 28)));
        // Without admin.pii.view a part of a phone finds nothing (no guessing a masked number digit by digit) ...
        $this->assertSame([], $list('5551122'));
        $this->assertSame([], $list('99893555112'));
        $this->assertSame(0, $this->adminJson('GET', '/orders?q=5551122', $token)->json('data.counts.new'));
        // ... the whole number does, however it is typed
        $this->assertSame([(string) $order->uuid], $list('+998 93 555-11-22'));
        $this->assertSame([(string) $order->uuid], $list('935551122'));

        // With it, any 4+ digits, as before
        $pii = $this->signIn($this->makeMember());
        $this->assertSame([(string) $order->uuid], collect($this->adminJson('GET', '/orders?q=5551122', $pii)->assertOk()->json('data.data'))->pluck('uuid')->all());
        $this->assertSame(1, $this->adminJson('GET', '/orders?q=5551122', $pii)->json('data.counts.new'));
    }

    public function test_from_and_to_are_tashkent_days(): void
    {
        $this->travelTo('2026-11-03 12:00:00'); // UTC: 17:00 in Tashkent
        // 2026-11-02 19:30 UTC is 2026-11-03 00:30 in Tashkent
        $lateEvening = $this->makeOrder(OrderStatus::DELIVERED);
        $lateEvening->forceFill(['created_at' => '2026-11-02 19:30:00'])->saveQuietly();
        // 2026-11-02 18:30 UTC is 23:30 on the 2nd in Tashkent
        $dayBefore = $this->makeOrder(OrderStatus::DELIVERED);
        $dayBefore->forceFill(['created_at' => '2026-11-02 18:30:00'])->saveQuietly();

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $uuids = fn (string $query) => collect($this->adminJson('GET', '/orders?' . $query, $token)->json('data.data'))->pluck('uuid')->all();

        $this->assertSame([(string) $lateEvening->uuid], $uuids('from=2026-11-03&to=2026-11-03'));
        $this->assertSame([(string) $dayBefore->uuid], $uuids('from=2026-11-02&to=2026-11-02'));
        // Done counts only today (Tashkent) without dates
        $this->assertSame(1, $this->adminJson('GET', '/orders', $token)->json('data.counts.done'));
        $this->assertSame(2, $this->adminJson('GET', '/orders?from=2026-11-01', $token)->json('data.counts.done'));
    }

    public function test_cursor_pages(): void
    {
        foreach (range(1, 5) as $minutes) {
            $this->orderWithHistory($minutes);
        }
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $first = $this->adminJson('GET', '/orders?per_page=2', $token)->assertOk();
        $this->assertCount(2, $first->json('data.data'));
        $cursor = $first->json('data.next_cursor');
        $this->assertNotNull($cursor);
        $second = $this->adminJson('GET', '/orders?per_page=2&cursor=' . urlencode($cursor), $token)->assertOk();
        $this->assertCount(2, $second->json('data.data'));
        $this->assertEmpty(array_intersect(
            collect($first->json('data.data'))->pluck('uuid')->all(),
            collect($second->json('data.data'))->pluck('uuid')->all(),
        ));
    }

    public function test_sla_state_per_stage_with_edges(): void
    {
        $this->travelTo(now()->startOfMinute());
        $okNew = $this->orderWithHistory(3);
        $warningNew = $this->orderWithHistory(4); // 4 of 5 minutes = 80%: warning
        $lateNew = $this->orderWithHistory(7);    // 2 minutes past the limit
        $unpaidOnline = $this->orderWithHistory(20, [], ['payment_method' => 'click', 'payment_status' => 'pending']);
        $warningPrep = $this->orderWithHistory(40, ['accepted' => 24]);   // 24 of 30
        $latePrep = $this->orderWithHistory(60, ['accepted' => 42, 'preparing' => 40]);
        $ready = $this->orderWithHistory(60, ['accepted' => 59, 'preparing' => 58, 'ready' => 1]);
        $lateDelivery = $this->orderWithHistory(120, ['accepted' => 119, 'preparing' => 110, 'ready' => 100, 'on_the_way' => 50], ['courier_id' => $this->courier->id]);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $this->travelTo(now()); // signIn moved the clock 30 s: SLA rounding below counts whole minutes
        $rows = collect($this->adminJson('GET', '/orders?per_page=50', $token)->assertOk()->json('data.data'))->keyBy('uuid');
        $sla = fn ($order) => $rows[(string) $order->uuid]['sla'];

        $this->assertSame('ok', $sla($okNew)['state']);
        $this->assertSame('accept', $sla($okNew)['stage']);
        $this->assertSame('warning', $sla($warningNew)['state']);
        $this->assertSame('late', $sla($lateNew)['state']);
        $this->assertSame(2, $sla($lateNew)['late_by_minutes']);
        $this->assertNull($sla($unpaidOnline));
        $this->assertSame(['prepare', 'warning'], [$sla($warningPrep)['stage'], $sla($warningPrep)['state']]);
        $this->assertSame(['prepare', 'late', 12], [$sla($latePrep)['stage'], $sla($latePrep)['state'], $sla($latePrep)['late_by_minutes']]);
        $this->assertNull($sla($ready));
        $this->assertSame(['deliver', 'late', 5], [$sla($lateDelivery)['stage'], $sla($lateDelivery)['state'], $sla($lateDelivery)['late_by_minutes']]);

        $late = collect($this->adminJson('GET', '/orders?sla=late', $token)->json('data.data'))->pluck('uuid')->all();
        $this->assertEqualsCanonicalizing([(string) $lateNew->uuid, (string) $latePrep->uuid, (string) $lateDelivery->uuid], $late);
        $warning = collect($this->adminJson('GET', '/orders?sla=warning', $token)->json('data.data'))->pluck('uuid')->all();
        $this->assertEqualsCanonicalizing([(string) $warningNew->uuid, (string) $warningPrep->uuid], $warning);
    }

    public function test_card_masks_phones_without_pii_view_and_audits_a_reveal(): void
    {
        $this->customer->forceFill(['phone' => '+998935556677'])->save();
        $order = $this->orderWithHistory(50, ['accepted' => 49, 'preparing' => 45, 'ready' => 20, 'on_the_way' => 10], ['courier_id' => $this->courier->id]);
        $this->history($order, OrderStatus::ON_THE_WAY, OrderStatus::ON_THE_WAY, now()->subMinute(), $this->manager->id);
        FiscalReceipt::forceCreate([
            'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'card', 'status' => 'issued', 'driver' => 'external',
            'provider' => 'payme', 'idempotency_key' => 'order-' . $order->id, 'subject_type' => 'order', 'subject_id' => $order->id,
            'total' => '50000.00', 'lines' => [], 'payments' => [], 'receipt_number' => '77', 'issued_at' => now(),
        ]);

        $operator = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $card = $this->adminJson('GET', '/orders/' . $order->uuid, $operator)->assertOk()->json('data');
        $this->assertSame('+998 93 *** ** 77', $card['customer']['phone']);
        $this->assertSame('Lifecycle Burger', $card['store']['name']);
        $this->assertSame('on_the_way', $card['status']);
        $this->assertSame('deliver', $card['sla']['stage']);
        $this->assertSame(['accept', 'prepare', 'deliver'], array_column($card['sla_timeline'], 'stage'));
        $this->assertTrue($card['sla_timeline'][2]['running']);
        $this->assertSame('77', $card['fiscal_receipts'][0]['receipt_number']);
        $this->assertSame($this->manager->name, collect($card['history'])->last()['actor']['name']);
        $this->assertTrue($card['actions']['can_intervene']);
        $this->assertSame(['delivered'], array_column($card['actions']['transitions'], 'status'));
        $this->assertFalse($card['actions']['can_approve_refund']);
        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());

        // The card's store, courier and branch are the detailed ones (phones, address), not the list row's
        $this->store->forceFill(['phone' => '+998712345566'])->save();
        $this->branch->forceFill(['address' => 'Chilonzor 7'])->save();
        $card = $this->adminJson('GET', '/orders/' . $order->uuid, $operator)->assertOk()->json('data');
        $this->assertSame('+998 71 *** ** 66', $card['store']['phone']);
        $this->assertSame((int) $this->courier->id, $card['courier']['id']);
        $this->assertNotNull($card['courier']['phone']);
        $this->assertStringContainsString('***', $card['courier']['phone']);
        $this->assertSame('Chilonzor 7', $card['branch']['address']);
        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());

        $superadmin = $this->signIn($this->makeMember());
        $full = $this->adminJson('GET', '/orders/' . $order->uuid, $superadmin)->assertOk()->json('data');
        $this->assertSame('+998 93 555 66 77', $full['customer']['phone']);
        $this->assertSame('+998 71 234 55 66', $full['store']['phone']);
        $this->assertStringNotContainsString('***', $full['courier']['phone']);
        // One entry for the request, naming exactly the phones the answer carries
        $this->assertSame(1, AuditLog::where('action', 'admin.pii.revealed')->count());
        $revealed = AuditLog::where('action', 'admin.pii.revealed')->sole()->payload_json['revealed'];
        $this->assertEqualsCanonicalizing(
            [['type' => 'user', 'id' => (int) $this->customer->id, 'field' => 'phone'], ['type' => 'user', 'id' => (int) $this->courier->id, 'field' => 'phone'], ['type' => 'store', 'id' => (int) $this->store->id, 'field' => 'phone']],
            $revealed,
        );

        $viewer = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $card = $this->adminJson('GET', '/orders/' . $order->uuid, $viewer)->assertOk()->json('data');
        $this->assertFalse($card['actions']['can_intervene']);
        $this->assertFalse($card['actions']['can_cancel']);
    }

    public function test_store_quality_over_the_last_30_days(): void
    {
        // Accepted by staff after 4 minutes, ready 20 minutes later
        $good = $this->orderWithHistory(100, ['accepted' => 96, 'preparing' => 95, 'ready' => 76, 'on_the_way' => 70, 'delivered' => 50]);
        \App\Models\OrderStatusHistory::where('order_id', $good->id)->where('status', 'accepted')->update(['user_id' => $this->manager->id]);
        $rejected = $this->orderWithHistory(60, ['rejected' => 50], ['cancel_reason' => 'out_of_stock']);
        // Neither counts: unpaid online timed out, and the customer's own cancel of a new order
        $this->orderWithHistory(60, ['canceled' => 30], ['payment_method' => 'click', 'cancel_reason' => 'payment_timeout']);
        $this->orderWithHistory(60, ['canceled' => 58], ['cancel_reason' => 'customer_request']);
        // Too old
        $old = $this->orderWithHistory(60);
        $old->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $report = $this->adminJson('GET', '/orders/store-quality?store_id=' . $this->store->id, $token)->assertOk()->json('data');

        $this->assertSame(30, $report['period']['days']);
        $row = $report['stores'][0];
        $this->assertSame(2, $row['orders']);
        $this->assertSame(1, $row['accepted']);
        $this->assertEquals(0.5, $row['acceptance_rate']);
        $this->assertEquals(4.0, $row['avg_accept_minutes']);
        $this->assertEquals(20.0, $row['avg_prep_minutes']);
        $this->assertEquals(0.5, $row['cancel_rate']);
        $this->assertNotNull($rejected);

        $all = $this->adminJson('GET', '/orders/store-quality', $token)->assertOk()->json('data.stores');
        $this->assertSame($this->store->id, $all[0]['store']['id']);
    }
}
