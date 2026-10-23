<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Pos\Accounts\CustomerAccountException;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Booking\BookingException;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Sync\PosSyncException;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\OrderItem;
use App\Models\RolePermissionOverride;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Review P3-35: held pre-order lines carry their kitchen station from the start, and every POS line its product and
 * category (review C F1); the kitchen feed reads
 * include_ready=true/false (P3W3 #7); a round sent at once is announced as sent too (P3W3 #8); a POS rule refusing an
 * action is not reported as a server error (P3W3 #12); a reports.view reader sees the open shifts (review C F7).
 */
class PosReviewFixesKitchenTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());
    }

    public function test_held_pre_order_lines_carry_their_station_on_the_kitchen_feed(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 4, ['items' => [
            $this->line($this->shashlik, 4, ['fire_mode' => 'hold', 'due_at' => now()->addHours(2)->toIso8601String()]),
        ]]));
        $held = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->shashlik->id)->sole();
        $this->assertNull($held->sent_at);
        $this->assertSame(77, (int) $held->station_id);

        $cook = $this->makeCook($this->branch);
        $tickets = $this->pos($cook, 'GET', '/staff/kitchen/tickets')->assertOk()->json('data');
        $line = collect($tickets[0]['items'])->firstWhere('id', $held->id);
        $this->assertSame(77, $line['station_id']);
        $this->assertSame('hold', $line['fire_mode']);
        // ... and its dish, so a display routes a line without a station by the branch's station routes
        $product = $this->shashlik->product;
        $this->assertSame((int) $product->id, $line['product_id']);
        $this->assertNotNull($product->category_id);
        $this->assertSame((int) $product->category_id, $line['category_id']);
    }

    public function test_the_kitchen_feed_reads_include_ready_as_true_or_false(): void
    {
        $cook = $this->makeCook($this->branch);
        foreach (['true', 'false', '1', '0'] as $value) {
            $this->pos($cook, 'GET', "/staff/kitchen/tickets?include_ready={$value}")->assertOk();
        }
        $this->pos($cook, 'GET', '/staff/kitchen/tickets?include_ready=maybe')->assertStatus(422);
    }

    public function test_a_round_sent_at_once_is_announced_as_added_and_sent(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier));
        Event::fake([OrderItemsChangedBroadcast::class]);

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", [
            'version' => (int) $order->refresh()->version, 'send' => true, 'items' => [$this->line($this->tea, 2), $this->line($this->shashlik, 2, ['fire_mode' => 'hold'])],
        ])->assertOk();

        $tea = (int) OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->tea->id)->latest('id')->value('id');
        $changes = [];
        Event::assertDispatched(OrderItemsChangedBroadcast::class, function (OrderItemsChangedBroadcast $event) use (&$changes) {
            $changes[] = [$event->change, $event->itemIds, $event->version];

            return true;
        });
        $this->assertSame('round_added', $changes[0][0]);
        $this->assertCount(2, $changes[0][1]);
        $this->assertSame(['sent', [$tea]], [$changes[1][0], $changes[1][1]]);
        $this->assertSame($changes[0][2], $changes[1][2], 'one change, one version');
    }

    public function test_a_pos_rule_refusing_an_action_is_not_reported_but_a_server_error_is(): void
    {
        Exceptions::fake();

        // A stale version on the till: 409, answered, not reported
        $order = $this->openedOrder($this->openPos($this->cashier));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => 99, 'mode' => 'single'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');
        Exceptions::assertNotReported(PosActionException::class);

        foreach ([
            PosActionException::forbidden('approval_required', 'Tasdiq kerak.'),
            new PosOrderException('Eski versiya.', 409, 'version_conflict'),
            new BookingException('Band.', 'slot_taken', 409),
            new CustomerAccountException('Limit.', 'credit_limit_exceeded'),
            FiscalException::invalid('Mos emas.'),
            new PosSyncException('sync_in_progress', 'Kuting.'),
        ] as $refusal) {
            report($refusal);
        }
        Exceptions::assertNothingReported();

        report($error = new PosOrderException('Holat xatosi.', 500, 'status'));
        Exceptions::assertReported(fn (PosOrderException $e) => $e === $error);
    }

    public function test_a_reports_reader_sees_the_open_shifts(): void
    {
        foreach ([Permission::POS_OPEN_SHIFT, Permission::POS_CLOSE_SHIFT, Permission::POS_TAKE_PAYMENT] as $permission) {
            RolePermissionOverride::create(['store_id' => $this->store->id, 'role' => UserRole::MANAGER->value, 'permission' => $permission->value, 'allowed' => false]);
        }

        $this->pos($this->manager, 'GET', '/pos/shifts/current')->assertOk()->assertJsonPath('data.shifts', []);
        // Read-only: opening one is still refused
        $this->pos($this->manager, 'POST', '/pos/shifts', ['opening_cash' => '0'])->assertForbidden();
    }
}
