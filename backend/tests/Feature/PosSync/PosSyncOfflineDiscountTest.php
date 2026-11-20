<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\PosSyncOperationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Wave M5 S2 (docs/scale/M5_DESIGN.md §3, owner answer 8.1-1): a discount given at the till without internet
 * (`order.discount`): applied on sync through PosOrderService::discount with every online rule but the manager's
 * approval; audited twice (`pos.order.discount`, `pos.offline_discount`); refused → rejected `discount_not_allowed`
 * while the bill and the payment that follow are still taken (the order `underpaid` by the refused discount); marked
 * for the reports; the owner hears of them once per till and outage, not once per discount.
 */
class PosSyncOfflineDiscountTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
    }

    /**
     * @return list<string>
     */
    private function ownerAlerts(): array
    {
        return array_values(array_map(fn (array $sent) => $sent['text'], array_filter($this->telegram->sent, fn (array $sent) => $sent['chat'] === (string) $this->owner->telegram_id)));
    }

    private function limit(?string $percent): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], ['cashier_discount_max_percent' => $percent])])->save();
    }

    /**
     * A walk-in of 41 000 so'm opened offline (2 shashlik, 1 tea; a takeaway has no service charge).
     *
     * @return array<string, mixed>
     */
    private function open(string $order, ?User $staff = null): array
    {
        return $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true], $staff, 30);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function discount(string $order, string $kind = 'percent', string $value = '10', ?User $staff = null, array $extra = []): array
    {
        return $this->op('order.discount', $extra + ['order' => $order, 'kind' => $kind, 'value' => $value, 'reason' => 'Doimiy mijoz'], $staff, 25);
    }

    public function test_the_operation_type_is_never_relayed_and_is_not_money(): void
    {
        $type = PosSyncOperationType::from('order.discount');

        $this->assertFalse($type->relayable());
        $this->assertFalse($type->isMoney());
        $this->assertSame('Chegirma berildi', $type->label());
    }

    public function test_bootstrap_tells_the_till_it_may_discount_offline(): void
    {
        $this->assertTrue($this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline.discount'));
    }

    public function test_an_offline_discount_is_applied_audited_and_the_bill_matches_the_tills(): void
    {
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        // (a batch is applied in the till's seq order)
        $open = $this->open($order);
        $discount = $this->discount($order, 'percent', '10', null, ['expected_discount' => '4100.00']);

        $this->sync([
            $open,
            $discount,
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '36900.00'], null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36900', 'tendered_amount' => '40000']]], null, 19),
            $this->op('order.close', ['order' => $order], null, 19),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.1.reason', null)
            ->assertJsonPath('data.results.1.refs.discount', '4100.00')
            ->assertJsonPath('data.results.1.refs.discount_kind', 'percent')
            ->assertJsonPath('data.results.1.refs.discount_value', '10.00')
            ->assertJsonPath('data.results.1.refs.flags.0.flag', 'offline_discount')
            ->assertJsonPath('data.results.1.refs.flags.0.amount', '4100.00')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.refs.bill_total', '36900.00')
            ->assertJsonMissingPath('data.results.2.refs.flags')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.results.4.refs.closed', true);

        $stored = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame('4100.00', $stored->discount);
        $this->assertSame('percent', $stored->discount_kind);
        $this->assertSame('Doimiy mijoz', $stored->discount_reason);
        $this->assertSame($this->cashier->id, (int) $stored->discount_by);
        $this->assertSame('36900.00', OrderBill::where('order_id', $stored->id)->firstOrFail()->total);
        // The marker on the order: what a report or the order screen reads
        $flag = collect($stored->sync_flags)->firstWhere('flag', 'offline_discount');
        $this->assertEquals(['flag' => 'offline_discount', 'amount' => '4100.00', 'kind' => 'percent', 'value' => '10.00', 'staff_id' => $this->cashier->id, 'op_id' => $discount['op_id']], collect($flag)->except('at')->all());

        // Both audit rows: the rule's own and the "given without a server at hand" one
        $this->assertSame(1, AuditLog::where('action', 'pos.order.discount')->where('model_id', $stored->id)->count());
        $audit = AuditLog::where('action', 'pos.offline_discount')->firstOrFail();
        $this->assertSame($stored->id, (int) $audit->model_id);
        $this->assertSame($this->cashier->id, (int) $audit->user_id);
        $this->assertSame($this->till['device']->id, (int) $audit->device_id);
        $this->assertSame($discount['op_id'], $audit->payload_json['op_id']);
        $this->assertSame((string) $this->till['device']->uuid, $audit->payload_json['device']);
        $this->assertSame($discount['occurred_at'], $audit->payload_json['device_time']);
        $this->assertSame('percent', $audit->payload_json['kind']);
        $this->assertSame('10.00', $audit->payload_json['value']);
        $this->assertSame('4100.00', $audit->payload_json['expected_discount']);
        $this->assertSame('4100.00', $audit->payload_json['applied_discount']);
        $this->assertTrue($audit->payload_json['offline']);
    }

    public function test_the_same_operation_twice_is_applied_once(): void
    {
        $order = (string) Str::uuid();
        $open = $this->open($order);
        $discount = $this->discount($order);
        $this->sync([$open, $discount])->assertOk()->assertJsonPath('data.results.1.status', 'applied');
        $this->sync([$discount])->assertOk()->assertJsonPath('data.results.0.duplicate', true)->assertJsonPath('data.results.0.status', 'applied');

        $this->assertSame(1, AuditLog::where('action', 'pos.offline_discount')->count());
        $this->assertSame(1, AuditLog::where('action', 'pos.order.discount')->count());
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_a_percent_is_worked_out_again_when_a_round_follows(): void
    {
        $order = (string) Str::uuid();
        $this->sync([
            $this->open($order),
            $this->discount($order),
            $this->op('order.round', ['order' => $order, 'items' => [$this->line($this->shashlik, '1')], 'send' => true], null, 22),
        ])->assertOk()->assertJsonPath('data.results.2.status', 'applied');

        // 10 % of 59 000
        $this->assertSame('5900.00', Order::where('external_id', $order)->firstOrFail()->discount);
    }

    public function test_what_the_till_expected_and_the_server_applied_differ_is_only_a_note(): void
    {
        $order = (string) Str::uuid();
        $this->sync([$this->open($order), $this->discount($order, 'percent', '10', null, ['expected_discount' => '4000.00'])])->assertOk()
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.1.refs.discount', '4100.00')
            ->assertJsonPath('data.results.1.refs.flags.1.flag', 'discount_mismatch')
            ->assertJsonPath('data.results.1.refs.flags.1.device_discount', '4000.00')
            ->assertJsonPath('data.results.1.refs.flags.1.server_discount', '4100.00');

        $audit = AuditLog::where('action', 'pos.offline_discount')->firstOrFail();
        $this->assertSame('4000.00', $audit->payload_json['expected_discount']);
        $this->assertSame('4100.00', $audit->payload_json['applied_discount']);
    }

    public function test_a_refused_discount_is_rejected_and_the_bill_and_the_payment_are_still_taken(): void
    {
        // The owner lowered the cashier's limit during the outage
        $this->limit('5');
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();

        $this->sync([
            $this->open($order),
            $this->discount($order, 'percent', '10', null, ['expected_discount' => '4100.00']),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '36900.00'], null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36900']]], null, 19),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'approval_required')
            ->assertJsonPath('data.results.1.refs.expected_discount', '4100.00')
            // The bill at the full price: a note, the payment settles it
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.refs.bill_total', '41000.00')
            ->assertJsonPath('data.results.2.refs.flags.0.flag', 'amount_mismatch')
            // The money is never refused: recorded, the order short by exactly the refused discount
            ->assertJsonPath('data.results.3.status', 'conflict')
            ->assertJsonPath('data.results.3.reason', 'underpaid')
            ->assertJsonPath('data.results.3.refs.flags.0.amount', '4100.00');

        $stored = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame('0.00', $stored->discount);
        $this->assertNull($stored->discount_kind);
        $this->assertSame('36900.00', Payment::where('order_id', $stored->id)->where('status', 'paid')->firstOrFail()->amount);
        $this->assertNotNull(collect($stored->sync_flags)->firstWhere('flag', 'underpaid'));
        $this->assertNull(collect($stored->sync_flags)->firstWhere('flag', 'offline_discount'));
        $this->assertStringContainsString('Chegirma serverda qabul qilinmadi', (string) PosSyncOperation::where('type', 'order.discount')->firstOrFail()->error);
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_discount')->count());
        $this->assertSame(0, AuditLog::where('action', 'pos.order.discount')->count());
        $this->assertSame([], $this->ownerAlerts());
    }

    public function test_who_may_not_discount_online_may_not_offline(): void
    {
        $waiterOrder = (string) Str::uuid();
        $cookOrder = (string) Str::uuid();
        $this->pinSession($this->till, $this->waiter, '7391');
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->pinSession($this->till, $cook, '8264');

        $this->sync([
            $this->open($waiterOrder),
            // A waiter (orders.manage only): online this asks for a manager's PIN, offline there is none
            $this->discount($waiterOrder, 'percent', '5', $this->waiter),
            $this->open($cookOrder),
            // The permission is not there at all
            $this->discount($cookOrder, 'percent', '5', $cook),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'approval_required')
            ->assertJsonPath('data.results.3.status', 'rejected')
            ->assertJsonPath('data.results.3.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.3.refs.cause', 'permission_denied');

        $this->assertSame(0, Order::whereIn('external_id', [$waiterOrder, $cookOrder])->where('discount', '>', 0)->count());
    }

    public function test_a_discount_without_a_reason_or_after_the_payment_is_refused(): void
    {
        $order = (string) Str::uuid();
        $this->pinSession($this->till, $this->manager, '6157');
        $first = [
            $this->open($order),
            $this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => '10']),
            $this->op('order.discount', ['order' => $order, 'kind' => 'gift', 'value' => '10', 'reason' => 'x']),
            $this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => 'ten', 'reason' => 'x']),
        ];
        $paid = $this->walkInSale('K1-001');

        $this->sync([
            ...$first,
            $paid['ops'][0], $paid['ops'][1], $paid['ops'][2],
            // The manager's own rule online too: not once the bill is paid
            $this->discount($paid['order'], 'percent', '10', $this->manager),
        ])->assertOk()
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'invalid')
            ->assertJsonPath('data.results.2.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.3.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.3.refs.cause', 'value_invalid')
            ->assertJsonPath('data.results.6.status', 'applied')
            ->assertJsonPath('data.results.7.status', 'rejected')
            ->assertJsonPath('data.results.7.reason', 'discount_not_allowed');
    }

    public function test_a_hub_never_relays_a_discount(): void
    {
        $order = (string) Str::uuid();
        $this->sync([$this->open($order)])->assertOk();
        $other = $this->pairTerminal($this->owner, $this->branch);

        $this->sync([$this->discount($order) + ['origin_device' => (string) $other['device']->uuid]])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'relay_not_allowed');
    }

    public function test_taking_a_discount_off_offline_is_applied_and_not_reported_as_given(): void
    {
        $order = (string) Str::uuid();
        $this->sync([
            $this->open($order),
            $this->discount($order),
            $this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => '0', 'expected_discount' => '0.00'], null, 24),
        ])->assertOk()
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.refs.discount', '0.00')
            ->assertJsonMissingPath('data.results.2.refs.flags');

        $this->assertSame('0.00', Order::where('external_id', $order)->firstOrFail()->discount);
        $this->assertSame(2, AuditLog::where('action', 'pos.offline_discount')->count());
        // Given and taken back within the outage: nothing to tell the owner
        $this->assertSame([], $this->ownerAlerts());
    }

    public function test_the_owner_gets_one_summary_for_the_batch_and_no_alert_per_discount(): void
    {
        $manager = $this->manager;
        $this->pinSession($this->till, $manager, '6157');
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

        $this->sync([
            $this->open($a), $this->discount($a, 'percent', '10'),
            $this->open($b), $this->discount($b, 'amount', '2000'),
            $this->open($c), $this->discount($c, 'percent', '50', $manager),
        ])->assertOk()->assertJsonPath('data.results.5.status', 'applied');

        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Internetsiz berilgan chegirmalar: 3 ta', $alerts[0]);
        // 4 100 + 2 000 + 20 500
        $this->assertStringContainsString("jami 26 600 so'm", $alerts[0]);
        $this->assertStringContainsString("{$this->cashier->name} — 2 ta, 6 100 so'm", $alerts[0]);
        $this->assertStringContainsString("{$manager->name} — 1 ta, 20 500 so'm", $alerts[0]);
        $this->assertStringContainsString("Filial: {$this->branch->name}", $alerts[0]);
        $this->assertStringNotContainsString('Chegirma berildi', implode("\n", $alerts));

        $summary = AuditLog::where('action', 'pos.offline_discount_summary')->firstOrFail();
        $this->assertSame($this->till['device']->id, (int) $summary->device_id);
        $this->assertSame(3, $summary->payload_json['count']);
        $this->assertSame('26600.00', $summary->payload_json['amount']);

        // A later batch without a discount: nothing more
        $this->sync([$this->op('order.close', ['order' => $a])])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());

        // The next outage: its own message, only its own discounts
        $d = (string) Str::uuid();
        $this->sync([$this->open($d), $this->discount($d, 'amount', '1000')])->assertOk();
        $alerts = $this->ownerAlerts();
        $this->assertCount(2, $alerts);
        $this->assertStringContainsString("1 ta, jami 1 000 so'm", $alerts[1]);
    }

    public function test_a_queue_drained_in_several_batches_is_one_summary(): void
    {
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];

        // 4 operations waiting on the till, 2 in this batch: more to come
        $this->sync([$this->open($a), $this->discount($a)], ['queue' => ['size' => 4, 'oldest_at' => now()->subMinutes(30)->toJSON()]])->assertOk();
        $this->assertSame([], $this->ownerAlerts());

        $this->sync([$this->open($b), $this->discount($b)], ['queue' => ['size' => 2, 'oldest_at' => now()->subMinutes(20)->toJSON()]])->assertOk();
        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString("2 ta, jami 8 200 so'm", $alerts[0]);
    }

    public function test_a_drain_that_never_ends_still_tells_the_owner_after_half_an_hour(): void
    {
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];
        $this->sync([$this->open($a), $this->discount($a)], ['queue' => ['size' => 100]])->assertOk();
        $this->assertSame([], $this->ownerAlerts());

        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(31));
        $this->sync([$this->open($b)], ['queue' => ['size' => 100]])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_a_discount_given_online_still_alerts_at_once(): void
    {
        $order = (string) Str::uuid();
        $this->sync([$this->open($order), $this->discount($order)])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());

        $stored = Order::where('external_id', $order)->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$stored->uuid}/discount", ['version' => $stored->version, 'kind' => 'percent', 'value' => '10', 'reason' => 'Onlayn'])->assertOk();

        $alerts = $this->ownerAlerts();
        $this->assertCount(2, $alerts);
        $this->assertStringContainsString('Chegirma berildi', $alerts[1]);
    }

    public function test_the_shift_report_counts_offline_discounts_on_their_own_line(): void
    {
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $pay = fn (string $order, string $amount) => [
            $this->op('bill.create', ['order' => $order, 'bill' => $bill = (string) Str::uuid()], null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => $amount]]], null, 19),
        ];

        // a: discounted offline; b: no discount; c: discounted offline, then the cashier changed it online
        $this->sync([$this->open($a), $this->discount($a), ...$pay($a, '36900'), $this->open($b), ...$pay($b, '41000'), $this->open($c), $this->discount($c)])
            ->assertOk()->assertJsonPath('data.results.3.status', 'applied');
        $stored = Order::where('external_id', $c)->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$stored->uuid}/discount", ['version' => $stored->version, 'kind' => 'amount', 'value' => '1000', 'reason' => 'Onlayn'])->assertOk();
        $this->sync($pay($c, '40000'))->assertOk()->assertJsonPath('data.results.1.status', 'applied');

        $report = $this->tillAs('GET', "/pos/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 2, 'amount' => '5100.00', 'offline' => ['count' => 1, 'amount' => '4100.00']], $report['discounts']);
    }
}
