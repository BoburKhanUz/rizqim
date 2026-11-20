<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Sync\OfflineDiscounts;
use App\Domain\Pos\Sync\PosSyncService;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Wave M5 S2, the fixes of the two independent reviews and the owner's decision of 20.11.2026:
 *  - without internet EVERYONE (a manager too) gives at most the branch's cashier limit; no limit, no offline discount;
 *  - an `order.discount` needs a PIN sign-in on the till within 36 h before the server RECEIVED it (the operation's
 *    own time cannot stretch that), is refused for a PIN-less "continued" staff member, and a blocked user does
 *    nothing through sync but hand in money already taken;
 *  - the owner's summary does not depend on the cache, is sent by a scheduled command when the till never syncs
 *    again, is marked as told only after it was handed to the sender, and lists the refused discounts too;
 *  - the order's JSON says whether its discount is a promo code's (`discount_promo`).
 */
class PosSyncOfflineDiscountReviewTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function settings(array $settings): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], $settings)])->save();
        app()->forgetScopedInstances();
    }

    /**
     * A walk-in of 41 000 so'm opened offline.
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

    /**
     * @return list<array<string, mixed>>
     */
    private function pay(string $order, string $amount): array
    {
        $bill = (string) Str::uuid();

        return [
            $this->op('bill.create', ['order' => $order, 'bill' => $bill], null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => $amount]]], null, 19),
        ];
    }

    private function summaries(): int
    {
        return (int) $this->artisan('pos:offline-discount-summaries')->run();
    }

    // --- 1. the offline cap and the sign-in window -------------------------------------------------------------------

    public function test_offline_a_manager_gives_at_most_the_cashiers_limit(): void
    {
        $this->pinSession($this->till, $this->manager, '6157');
        [$a, $b, $c, $d] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

        $this->sync([
            $this->open($a), $this->discount($a, 'percent', '10.01', $this->manager),
            $this->open($b), $this->discount($b, 'percent', '10', $this->manager),
            // An amount by its share of the dishes, as typed: 4 100.40 of 41 000 is above 10 %
            $this->open($c), $this->discount($c, 'amount', '4100.40', $this->manager),
            $this->open($d), $this->discount($d, 'amount', '4100', $this->manager),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'offline_limit')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.results.3.refs.discount', '4100.00')
            ->assertJsonPath('data.results.5.status', 'rejected')
            ->assertJsonPath('data.results.5.refs.cause', 'offline_limit')
            ->assertJsonPath('data.results.7.status', 'applied');

        $this->assertSame('0.00', Order::where('external_id', $a)->firstOrFail()->discount);
        $this->assertSame((int) $this->manager->id, (int) Order::where('external_id', $b)->firstOrFail()->discount_by);
    }

    public function test_a_cashier_above_the_limit_offline_is_told_the_offline_limit(): void
    {
        $this->settings(['cashier_discount_max_percent' => '5']);
        $order = (string) Str::uuid();

        $this->sync([$this->open($order), $this->discount($order)])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'offline_limit');
    }

    public function test_without_a_configured_limit_nobody_discounts_offline_but_a_discount_is_still_taken_off(): void
    {
        $this->pinSession($this->till, $this->manager, '6157');
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        // c got its discount while the limit was there
        $this->sync([$this->open($c), $this->discount($c)])->assertOk()->assertJsonPath('data.results.1.status', 'applied');

        foreach ([null, '0'] as $limit) {
            $this->settings(['cashier_discount_max_percent' => $limit]);
            $this->sync([
                $this->open($a = (string) Str::uuid()), $this->discount($a, 'percent', '1', $this->manager),
                $this->open($b = (string) Str::uuid()), $this->discount($b, 'amount', '100'),
            ])->assertOk()
                ->assertJsonPath('data.results.1.status', 'rejected')
                ->assertJsonPath('data.results.1.refs.cause', 'offline_limit')
                ->assertJsonPath('data.results.3.status', 'rejected')
                ->assertJsonPath('data.results.3.refs.cause', 'offline_limit');
        }

        // Taking a discount off is no discount: orders.manage, as online
        $this->sync([$this->op('order.discount', ['order' => $c, 'kind' => 'percent', 'value' => '0'])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.discount', '0.00');
    }

    public function test_the_sign_in_window_of_a_discount_is_measured_at_the_servers_receipt(): void
    {
        $now = now();
        // The manager signed in on this till 80 h ago and left; a second cashier 37 h ago
        $second = $this->makeStaff(\App\Enums\UserRole::CASHIER, $this->branch);
        Carbon::setTestNow($now->copy()->subHours(80));
        $this->pinSession($this->till, $this->manager, '6157');
        Carbon::setTestNow($now->copy()->subHours(37));
        $this->pinSession($this->till, $second, '7391');
        Carbon::setTestNow($now);

        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];
        // Each operation says it happened within 36 h of its sign-in (the device's time is the till's own word)
        $openA = ['occurred_at' => $now->copy()->subHours(47)->subMinute()->toJSON()] + $this->open($a, $this->manager);
        $byManager = ['occurred_at' => $now->copy()->subHours(47)->toJSON()] + $this->discount($a, 'percent', '10', $this->manager);
        $openB = ['occurred_at' => $now->copy()->subHours(30)->toJSON()] + $this->open($b, $second);
        $byCashier = ['occurred_at' => $now->copy()->subHours(30)->toJSON()] + $this->discount($b, 'percent', '10', $second);

        $this->sync([$openA, $byManager, $openB, $byCashier])->assertOk()
            // Anything else is still measured at its own time (a long outage late in a session syncs)
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'staff_not_signed_in')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.3.status', 'rejected')
            ->assertJsonPath('data.results.3.refs.cause', 'staff_not_signed_in');

        $this->assertSame(0, Order::whereIn('external_id', [$a, $b])->where('discount', '>', 0)->count());
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_discount')->count());

        // A sign-in 35 h before the receipt: taken
        Carbon::setTestNow($now->copy()->subHours(35));
        $this->pinSession($this->till, $second, '7391');
        Carbon::setTestNow($now);
        $this->sync([['occurred_at' => $now->copy()->subHours(30)->toJSON()] + $this->discount($b, 'percent', '10', $second)])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied');
    }

    // --- 2. blocked users --------------------------------------------------------------------------------------------

    public function test_a_blocked_user_does_nothing_through_sync_but_their_money_is_still_taken(): void
    {
        $this->pinSession($this->till, $this->manager, '6157');
        [$a, $b, $paid] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $bill = (string) Str::uuid();
        // Before the block: an order of the manager's with its bill
        $this->sync([$this->open($a), $this->open($paid, $this->manager), $this->op('bill.create', ['order' => $paid, 'bill' => $bill], $this->manager, 20)])->assertOk()
            ->assertJsonPath('data.results.2.status', 'applied');

        $this->manager->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Firibgarlik'])->save();
        $this->manager->tokens()->delete();

        $this->sync([
            $this->discount($a, 'percent', '10', $this->manager, ['expected_discount' => '4100.00']),
            $this->open($b, $this->manager),
            $this->op('order.round', ['order' => $a, 'items' => [$this->line($this->tea, '1')], 'send' => true], $this->manager, 22),
            // Money they took before anybody at the till knew: never refused
            $this->op('bill.pay', ['order' => $paid, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']]], $this->manager, 19),
            $this->op('order.close', ['order' => $paid], $this->manager, 18),
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.0.refs.cause', 'staff_invalid')
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'staff_invalid')
            ->assertJsonPath('data.results.2.status', 'rejected')
            ->assertJsonPath('data.results.2.reason', 'staff_invalid')
            ->assertJsonPath('data.results.3.status', 'conflict')
            ->assertJsonPath('data.results.3.reason', 'staff_unverified')
            ->assertJsonPath('data.results.4.refs.closed', true);

        $this->assertSame('0.00', Order::where('external_id', $a)->firstOrFail()->discount);
        $this->assertNull(Order::where('external_id', $b)->first());
        $stored = Order::where('external_id', $paid)->firstOrFail();
        $this->assertSame('41000.00', Payment::where('order_id', $stored->id)->where('status', 'paid')->firstOrFail()->amount);
        $this->assertNotNull($stored->closed_at);
    }

    // --- 3. PIN-less continued staff ---------------------------------------------------------------------------------

    public function test_a_continued_staff_member_gives_no_discount_and_the_mark_is_audited(): void
    {
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];

        $this->sync([
            $this->open($a), $this->discount($a, 'percent', '10', null, ['staff_continued' => true]),
            $this->open($b), $this->discount($b, 'percent', '10', null, ['staff_continued' => false]),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.1.refs.cause', 'staff_continued')
            ->assertJsonPath('data.results.3.status', 'applied');

        $this->assertSame('0.00', Order::where('external_id', $a)->firstOrFail()->discount);
        $audits = AuditLog::where('action', 'pos.offline_discount')->get();
        $this->assertCount(1, $audits);
        $this->assertFalse($audits[0]->payload_json['staff_continued']);

        // Without the mark the audit says nothing about it
        $c = (string) Str::uuid();
        $this->sync([$this->open($c), $this->discount($c)])->assertOk()->assertJsonPath('data.results.1.status', 'applied');
        $this->assertArrayNotHasKey('staff_continued', AuditLog::where('action', 'pos.offline_discount')->orderByDesc('id')->firstOrFail()->payload_json);
    }

    // --- 4. the owner's summary --------------------------------------------------------------------------------------

    public function test_the_summary_survives_a_cache_flush_in_the_middle_of_a_drain(): void
    {
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];
        $this->sync([$this->open($a), $this->discount($a)], ['queue' => ['size' => 3]])->assertOk();
        $this->assertSame([], $this->ownerAlerts());

        // A deploy or a Redis restart
        Cache::flush();
        $this->sync([$this->open($b)], ['queue' => ['size' => 1]])->assertOk();

        $this->assertCount(1, $this->ownerAlerts());
        $this->assertStringContainsString("1 ta, jami 4 100 so'm", $this->ownerAlerts()[0]);
    }

    public function test_a_till_that_never_syncs_again_is_reported_by_the_scheduled_command(): void
    {
        $order = (string) Str::uuid();
        $this->sync([$this->open($order), $this->discount($order)], ['queue' => ['size' => 50]])->assertOk()
            ->assertJsonPath('data.results.1.status', 'applied');
        $this->assertSame([], $this->ownerAlerts());

        // Not yet: the drain may still end by itself
        Carbon::setTestNow(now()->addMinutes(29));
        $this->summaries();
        $this->assertSame([], $this->ownerAlerts());

        Carbon::setTestNow(now()->addMinutes(2));
        $this->summaries();
        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString("Internetsiz berilgan chegirmalar: 1 ta, jami 4 100 so'm", $alerts[0]);
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_discount_summary')->count());

        // Once: neither the next run nor the till coming back tells it again
        Carbon::setTestNow(now()->addMinutes(10));
        $this->summaries();
        $this->sync([$this->op('order.close', ['order' => $order])], ['queue' => ['size' => 1]])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_the_scheduled_command_leaves_a_till_alone_while_its_batch_is_being_applied(): void
    {
        $order = (string) Str::uuid();
        $this->sync([$this->open($order), $this->discount($order)], ['queue' => ['size' => 50]])->assertOk();
        Carbon::setTestNow(now()->addMinutes(31));

        // The till's batch holds the lock (it sends the summary itself when it ends)
        $lock = Cache::lock(PosSyncService::lockName($this->till['device']), 120);
        $this->assertTrue($lock->get());
        try {
            $this->summaries();
            $this->assertSame([], $this->ownerAlerts());
        } finally {
            $lock->release();
        }

        $this->summaries();
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_the_scheduled_command_is_on_the_schedule_every_ten_minutes(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pos:offline-discount-summaries'));

        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
    }

    public function test_a_summary_is_marked_as_told_only_after_it_was_handed_to_the_sender(): void
    {
        $order = (string) Str::uuid();
        $failing = \Mockery::mock(PosOwnerAlerts::class);
        $failing->shouldReceive('send')->once()->andThrow(new \RuntimeException('the sender is down'));
        $this->app->instance(PosOwnerAlerts::class, $failing);
        $this->app->forgetInstance(OfflineDiscounts::class);

        // (the batch itself never fails over a notification)
        $this->sync([$this->open($order), $this->discount($order)])->assertOk()->assertJsonPath('data.results.1.status', 'applied');
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_discount_summary')->count());

        // The sender is back: the next batch of the till tells the owner, once
        $this->app->forgetInstance(PosOwnerAlerts::class);
        $this->app->forgetInstance(OfflineDiscounts::class);
        $this->sync([$this->op('order.close', ['order' => $order])])->assertOk();
        $this->sync([$this->op('order.send', ['order' => $order])])->assertOk();

        $this->assertCount(1, $this->ownerAlerts());
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_discount_summary')->count());
    }

    // --- 5. refused discounts in the summary -------------------------------------------------------------------------

    public function test_the_summary_lists_the_refused_discounts_and_what_they_left_unpaid(): void
    {
        $this->pinSession($this->till, $this->manager, '6157');
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

        $this->sync([
            $this->open($a), $this->discount($a), ...$this->pay($a, '36900'),
            // Above the offline limit: refused, the guest paid the discounted bill all the same
            $this->open($b), $this->discount($b, 'percent', '50', $this->manager), ...$this->pay($b, '20500'),
            // Refused, not paid yet
            $this->open($c), $this->discount($c, 'percent', '20'),
        ])->assertOk()
            ->assertJsonPath('data.results.5.status', 'rejected')
            ->assertJsonPath('data.results.7.reason', 'underpaid');

        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString("Internetsiz berilgan chegirmalar: 1 ta, jami 4 100 so'm", $alerts[0]);
        $this->assertStringContainsString("Rad etilgan: 2 ta — schyotlarda 20 500 so'm kam to'langan, menejer tekshirsin", $alerts[0]);

        $summary = AuditLog::where('action', 'pos.offline_discount_summary')->sole();
        $this->assertSame(1, $summary->payload_json['count']);
        $this->assertSame(2, $summary->payload_json['refused']);
        $this->assertSame('20500.00', $summary->payload_json['refused_underpaid']);

        // Told once
        $this->sync([$this->op('order.close', ['order' => $a])])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_only_refused_discounts_are_still_a_message(): void
    {
        $this->settings(['cashier_discount_max_percent' => '5']);
        $order = (string) Str::uuid();

        $this->sync([$this->open($order), $this->discount($order), ...$this->pay($order, '36900')])->assertOk()
            ->assertJsonPath('data.results.1.status', 'rejected');

        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringNotContainsString('Kim berdi', $alerts[0]);
        $this->assertStringContainsString("Rad etilgan: 1 ta — schyotlarda 4 100 so'm kam to'langan, menejer tekshirsin", $alerts[0]);
        $this->assertStringContainsString("Filial: {$this->branch->name}", $alerts[0]);
    }

    // --- 6. the promo fact in the order's JSON -----------------------------------------------------------------------

    public function test_the_order_says_whether_its_discount_is_a_promo_codes(): void
    {
        $this->settings(['modules' => ['vouchers' => true]]);
        PromoCode::create(['code' => 'KUZ10', 'type' => 'percentage', 'value' => 10, 'store_id' => $this->store->id, 'is_active' => true, 'max_discount_amount' => 0]);
        $order = (string) Str::uuid();
        $this->sync([$this->open($order)])->assertOk();
        $stored = Order::where('external_id', $order)->firstOrFail();

        $this->tillAs('GET', "/pos/orders/{$stored->uuid}")->assertOk()->assertJsonPath('data.discount_promo', false);
        $this->tillAs('POST', "/pos/orders/{$stored->uuid}/promo", ['version' => $stored->version, 'code' => 'KUZ10'])->assertOk()
            ->assertJsonPath('data.discount', '4100.00')
            ->assertJsonPath('data.discount_promo', true);

        // A discount by hand, typed with a promo-looking reason, replaces the code: offline as online
        $this->sync([$this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => '5', 'reason' => 'promo:KUZ10'])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied');
        $this->tillAs('GET', "/pos/orders/{$stored->uuid}")->assertOk()
            ->assertJsonPath('data.discount', '2050.00')
            ->assertJsonPath('data.discount_reason', 'promo:KUZ10')
            ->assertJsonPath('data.discount_promo', false);
    }
}
