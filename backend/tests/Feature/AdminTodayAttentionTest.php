<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Device;
use App\Models\FiscalReceipt;
use App\Models\PayoutRequest;
use App\Models\StoreTopupRequest;
use App\Services\Admin\Attention\AttentionCollector;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Attention\AttentionSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4-13: "Diqqat talab qiladi": the Bugun section's sources (settlement failures, pending payouts, stuck top-ups,
 * overdue invoices, terminals offline in business hours, failed fiscal receipts), each behind its permission, and
 * the collector: groups critical first, counts beyond the limit, a failing source left out.
 */
class AdminTodayAttentionTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use OrderLifecycleFixtures;
    use AdminTodayFixtures;

    private const MY_KINDS = ['settlement_failed', 'payout_pending', 'topup_pending', 'invoice_overdue', 'device_offline', 'fiscal_failed'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->at('2026-11-10 14:30');
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    private function attentionGroups(PlatformRole $role = PlatformRole::SUPERADMIN, int $limit = 5): \Illuminate\Support\Collection
    {
        $member = $this->makeMember($role);
        $groups = app(AttentionCollector::class)->collect($member, $limit);

        return collect($groups)->whereIn('kind', self::MY_KINDS)->keyBy('kind');
    }

    private function terminal(array $attributes = []): Device
    {
        return Device::forceCreate(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'type' => 'pos',
            'name' => 'Kassa 1',
            'last_seen_at' => now()->subMinutes(20),
        ], $attributes));
    }

    private function fiscal(string $status, array $attributes = []): FiscalReceipt
    {
        return FiscalReceipt::forceCreate(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'type' => 'sale',
            'payment_form' => 'cash',
            'status' => $status,
            'driver' => 'sandbox',
            'idempotency_key' => (string) Str::uuid(),
            'total' => '50000.00',
            'lines' => [],
            'payments' => [],
            'error' => "Soliq serveri rad etdi: MXIK noto'g'ri\n#0 stack trace",
        ], $attributes));
    }

    public function test_nothing_to_show_is_an_empty_list(): void
    {
        $this->assertSame([], $this->attentionGroups()->all());

        $token = $this->signIn($this->makeMember());
        $groups = collect($this->adminJson('GET', '/today/attention', $token)->assertOk()->json('data.groups'));
        $this->assertSame([], $groups->whereIn('kind', self::MY_KINDS)->values()->all());
    }

    public function test_settlement_failures_link_the_order_and_turn_critical_when_retries_ran_out(): void
    {
        $retrying = $this->makeOrder(OrderStatus::DELIVERED, ['settlement_failed_at' => now()->subMinutes(30), 'settlement_error' => 'Lock wait timeout', 'settlement_attempts' => 2]);
        $gaveUp = $this->makeOrder(OrderStatus::DELIVERED, ['settlement_failed_at' => now()->subHours(5), 'settlement_error' => "Store not found\nmore", 'settlement_attempts' => 12]);
        $this->makeOrder(OrderStatus::DELIVERED);

        $group = $this->attentionGroups()['settlement_failed'];
        $this->assertSame(2, $group['count']);
        $this->assertSame('critical', $group['severity']);
        $this->assertSame('Hisob-kitob xatolari', $group['label']);
        $this->assertSame('/orders/' . $gaveUp->uuid, $group['items'][0]['href']);
        $this->assertSame('critical', $group['items'][0]['severity']);
        $this->assertStringContainsString('Avtomatik urinishlar tugadi', $group['items'][0]['subtitle']);
        $this->assertStringContainsString('Store not found', $group['items'][0]['subtitle']);
        $this->assertStringNotContainsString('more', $group['items'][0]['subtitle']);
        $this->assertSame('warning', $group['items'][1]['severity']);
        $this->assertSame('/orders/' . $retrying->uuid, $group['items'][1]['href']);
        $this->assertSame($this->store->id, $group['items'][1]['store_id']);
        $this->assertStringEndsWith('+05:00', $group['items'][1]['since']);
    }

    public function test_pending_payouts_and_stuck_top_ups(): void
    {
        $old = PayoutRequest::forceCreate(['store_id' => $this->store->id, 'amount' => '1500000.00', 'card_number' => '8600123412341234', 'status' => 'pending', 'created_at' => now()->subDay()]);
        PayoutRequest::forceCreate(['store_id' => $this->store->id, 'amount' => '200000.00', 'card_number' => '8600123412341234', 'status' => 'pending']);
        PayoutRequest::forceCreate(['store_id' => $this->store->id, 'amount' => '300000.00', 'card_number' => '8600123412341234', 'status' => 'completed']);

        $stuck = StoreTopupRequest::forceCreate(['store_id' => $this->store->id, 'amount' => 500000, 'provider' => 'payme', 'status' => 'pending', 'created_at' => now()->subMinutes(40)]);
        StoreTopupRequest::forceCreate(['store_id' => $this->store->id, 'amount' => 1000, 'provider' => 'click', 'status' => 'pending', 'created_at' => now()->subMinutes(10)]);
        StoreTopupRequest::forceCreate(['store_id' => $this->store->id, 'amount' => 1000, 'provider' => 'click', 'status' => 'pending', 'created_at' => now()->subDays(4)]);
        StoreTopupRequest::forceCreate(['store_id' => $this->store->id, 'amount' => 1000, 'provider' => 'click', 'status' => 'paid', 'created_at' => now()->subHour()]);

        $groups = $this->attentionGroups(limit: 1);

        $payouts = $groups['payout_pending'];
        $this->assertSame(2, $payouts['count']);
        $this->assertCount(1, $payouts['items']);
        $this->assertSame('/finance?payout=' . $old->id, $payouts['items'][0]['href']);
        $this->assertStringContainsString('1 500 000 so\'m', $payouts['items'][0]['title']);
        // The card number is not shown on Bugun
        $this->assertStringNotContainsString('8600', json_encode($payouts));

        $topups = $groups['topup_pending'];
        $this->assertSame(1, $topups['count']);
        $this->assertSame('info', $topups['severity']);
        $this->assertSame('/finance?topup=' . $stuck->uuid, $topups['items'][0]['href']);
        $this->assertStringContainsString('Payme', $topups['items'][0]['subtitle']);
    }

    public function test_overdue_invoices_by_the_tashkent_date(): void
    {
        // Due yesterday: overdue; due today: not yet; paid and void: never
        $overdue = $this->invoicePaidAt(null, '249000.00', SubscriptionInvoiceStatus::ISSUED, ['due_date' => '2026-11-05', 'number' => 'OB-202610-000001']);
        $this->invoicePaidAt(null, '149000.00', SubscriptionInvoiceStatus::ISSUED, ['due_date' => '2026-11-10']);
        $this->invoicePaidAt('2026-11-09 10:00', '149000.00', SubscriptionInvoiceStatus::PAID, ['due_date' => '2026-11-01']);
        $this->invoicePaidAt(null, '149000.00', SubscriptionInvoiceStatus::VOID, ['due_date' => '2026-11-01']);

        $group = $this->attentionGroups()['invoice_overdue'];
        $this->assertSame(1, $group['count']);
        $this->assertSame('/pos?invoice=' . $overdue->uuid, $group['items'][0]['href']);
        $this->assertStringContainsString('OB-202610-000001', $group['items'][0]['title']);
        $this->assertStringContainsString("05.11.2026 edi, 5 kun o'tdi", $group['items'][0]['subtitle']);
        $this->assertSame('2026-11-06T00:00:00+05:00', $group['items'][0]['since']);

        // Just after Tashkent midnight (19:00 UTC) the invoice due on the 10th is overdue too
        $this->at('2026-11-11 00:01');
        $this->assertSame(2, $this->attentionGroups()['invoice_overdue']['count']);
    }

    public function test_terminals_offline_while_their_branch_is_open(): void
    {
        $this->branch->forceFill(['open_time' => '09:00:00', 'close_time' => '22:00:00'])->save();
        $offline = $this->terminal(['name' => 'Kassa zal']);
        $kds = $this->terminal(['type' => 'kds', 'name' => 'Oshxona', 'last_seen_at' => now()->subHour()]);
        $this->terminal(['name' => 'Online', 'last_seen_at' => now()->subMinutes(3)]);
        $this->terminal(['name' => 'Put away', 'last_seen_at' => now()->subDays(4)]);
        $this->terminal(['name' => 'Revoked'])->forceFill(['revoked_at' => now()])->save();
        $this->terminal(['name' => 'Phone', 'type' => 'partner_mobile']);
        // A terminal without a branch follows the store's hours (round the clock in the fixture)
        $storeLevel = $this->terminal(['name' => 'No branch', 'branch_id' => null, 'last_seen_at' => now()->subMinutes(15)]);
        // A suspended store's terminals and a branch closed for the night are not a problem
        $closedBranch = $this->otherBranch;
        $closedBranch->forceFill(['open_time' => '06:00:00', 'close_time' => '10:00:00'])->save();
        $this->terminal(['name' => 'Morning only', 'branch_id' => $closedBranch->id]);
        $suspended = $this->makeStore('Suspended');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->terminal(['name' => 'Suspended till', 'store_id' => $suspended->id, 'branch_id' => null]);

        $group = $this->attentionGroups()['device_offline'];
        $this->assertSame(3, $group['count']);
        $this->assertSame('Ish vaqtida oflayn kassalar', $group['label']);
        $this->assertSame(['/pos?device=' . $kds->uuid, '/pos?device=' . $offline->uuid, '/pos?device=' . $storeLevel->uuid], array_column($group['items'], 'href'));
        $this->assertStringContainsString('Oshxona ekrani (KDS)', $group['items'][0]['subtitle']);
        $this->assertStringContainsString('oxirgi aloqa 10.11 13:30', $group['items'][0]['subtitle']);

        // After closing time only the round-the-clock store-level terminal is still expected online
        $this->at('2026-11-10 23:30');
        $late = $this->attentionGroups()['device_offline'];
        $this->assertSame(['/pos?device=' . $storeLevel->uuid], array_column($late['items'], 'href'));

        // The owner closed the store for today: nothing
        $this->store->forceFill(['is_open' => false])->save();
        $this->assertArrayNotHasKey('device_offline', $this->attentionGroups()->all());
    }

    public function test_failed_fiscal_receipts_of_the_last_week(): void
    {
        $recent = $this->fiscal('failed', ['created_at' => now()->subDay()]);
        $this->fiscal('failed', ['created_at' => now()->subDays(8)]);
        $this->fiscal('pending');
        $this->fiscal('issued');

        $group = $this->attentionGroups()['fiscal_failed'];
        $this->assertSame(1, $group['count']);
        $this->assertSame('critical', $group['severity']);
        $this->assertSame('/pos?fiscal=' . $recent->uuid, $group['items'][0]['href']);
        $this->assertStringContainsString("MXIK noto'g'ri", $group['items'][0]['subtitle']);
        $this->assertStringNotContainsString('stack trace', $group['items'][0]['subtitle']);
    }

    public function test_each_member_sees_only_the_sources_of_their_permissions(): void
    {
        $this->makeOrder(OrderStatus::DELIVERED, ['settlement_failed_at' => now(), 'settlement_attempts' => 12]);
        PayoutRequest::forceCreate(['store_id' => $this->store->id, 'amount' => '200000.00', 'card_number' => '8600123412341234', 'status' => 'pending']);
        $this->invoicePaidAt(null, '249000.00', SubscriptionInvoiceStatus::ISSUED, ['due_date' => '2026-11-01']);
        $this->terminal();
        $this->fiscal('failed');

        $all = ['device_offline', 'fiscal_failed', 'invoice_overdue', 'payout_pending', 'settlement_failed'];
        $this->assertEqualsCanonicalizing($all, $this->attentionGroups(PlatformRole::SUPERADMIN)->keys()->all());
        $this->assertEqualsCanonicalizing($all, $this->attentionGroups(PlatformRole::VIEWER)->keys()->all());
        // Operator: devices.view, no finance.view
        $this->assertEqualsCanonicalizing(['device_offline', 'fiscal_failed'], $this->attentionGroups(PlatformRole::OPERATOR)->keys()->all());

        // Through the API too, and the order of groups: critical first
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $groups = collect($this->adminJson('GET', '/today/attention', $token)->assertOk()->json('data.groups'))->whereIn('kind', self::MY_KINDS)->values();
        $severities = $groups->pluck('severity')->all();
        $this->assertSame(['critical', 'critical', 'warning', 'warning', 'warning'], $severities);
        $this->assertEqualsCanonicalizing(['fiscal_failed', 'settlement_failed'], $groups->take(2)->pluck('kind')->all());

        app(\App\Services\Admin\AdminPermissionService::class)->setOverride(PlatformRole::OPERATOR, AdminPermission::FINANCE_VIEW, true);
        $this->assertContains('payout_pending', $this->attentionGroups(PlatformRole::OPERATOR)->keys()->all());
    }

    public function test_a_failing_source_is_reported_and_left_out_and_unnamed_groups_get_a_label(): void
    {
        Exceptions::fake();
        $this->fiscal('failed');

        $this->app->instance('test.attention.broken', new class implements AttentionSource {
            public function permission(): AdminPermission
            {
                return AdminPermission::TODAY_VIEW;
            }

            public function collect(int $limit): array
            {
                throw new \RuntimeException('source down');
            }
        });
        $this->app->instance('test.attention.plain', new class implements AttentionSource {
            public function permission(): AdminPermission
            {
                return AdminPermission::TODAY_VIEW;
            }

            public function collect(int $limit): array
            {
                return ['count' => 7, 'items' => [
                    new AttentionItem('order_unaccepted', 'info', 'B', null, '/orders/b', null),
                    new AttentionItem('order_unaccepted', 'critical', 'A', null, '/orders/a', null),
                ]];
            }
        });
        $this->app->tag(['test.attention.broken', 'test.attention.plain'], AttentionSource::TAG);

        $groups = collect(app(AttentionCollector::class)->collect($this->makeMember(), 5))->keyBy('kind');

        $this->assertArrayHasKey('fiscal_failed', $groups->all());
        $plain = $groups['order_unaccepted'];
        $this->assertSame("Qabul qilinmagan buyurtmalar", $plain['label']);
        $this->assertSame(7, $plain['count']);
        $this->assertSame('critical', $plain['severity']);
        $this->assertSame(['A', 'B'], array_column($plain['items'], 'title'));
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'source down');
    }
}
