<?php

namespace Tests\Feature\PosSync;

use App\Models\FiscalReceipt;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review P3-35 (review A #3, #6, #9, #10) on POST /pos/sync: an operation whose own content cannot be stored is
 * answered and never blocks the till's queue; a device price is kept only when the dish had it while the till's menu
 * was taken (else the server's price, a conflict and, when lower, the owner hears); the device's clock is corrected and
 * kept within 48 hours; modifiers are this store's only.
 */
class PosSyncReviewFixesTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_a_long_card_transaction_id_and_an_unknown_card_type_are_stored_as_they_fit(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'card', 'amount' => '41000', 'card_type' => 'amex-gold-premium', 'transaction_id' => str_repeat('7', 70)]];

        $this->sync($sale['ops'])->assertOk()
            ->assertJsonPath('data.stopped_at', null)
            ->assertJsonPath('data.results.2.status', 'applied');
        $payment = Payment::sole();
        $this->assertSame(str_repeat('7', 64), $payment->transaction_id);
        $this->assertSame('other', $payment->card_type);
        $this->assertSame(4, PosSyncOperation::count());
    }

    public function test_an_operation_that_fails_on_its_own_content_is_rejected_and_the_queue_goes_on(): void
    {
        $order = (string) Str::uuid();
        $other = (string) Str::uuid();
        // A line total no money column holds: fails the same way every time
        $poison = $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '1000000')]]);
        $next = $this->op('order.open', ['order' => $other, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')]]);

        $response = $this->sync([$poison, $next])->assertOk()
            ->assertJsonPath('data.stopped_at', null)
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'invalid_payload')
            ->assertJsonPath('data.results.1.status', 'applied');
        $this->assertNotNull(Order::where('external_id', $other)->first());

        // Sent again: answered from the record, nothing applied twice
        $this->sync([$poison, $next])->assertOk()->assertJsonPath('data.results.0.duplicate', true);
        $this->assertSame(2, PosSyncOperation::count());
        $this->assertSame($response->json('data.results.0.op_id'), $poison['op_id']);
    }

    public function test_an_amount_beyond_a_money_column_is_refused_as_invalid_not_a_database_error(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '99999999999']];

        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.stopped_at', null)
            ->assertJsonPath('data.results.2.status', 'rejected')
            ->assertJsonPath('data.results.2.reason', 'amount_invalid');
        $this->assertSame(0, Payment::count());
    }

    public function test_a_bad_menu_taken_at_or_date_never_fails_the_batch(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][0]['payload']['menu_taken_at'] = 'yesterday-ish';
        $this->sync($sale['ops'], ['queue' => ['size' => 4, 'oldest_at' => '2026-10-22T12:00:00Z']])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.3.status', 'applied');

        // A line's due time that is no date: that operation is refused, the batch goes on
        $other = $this->walkInSale('K1-002');
        $other['ops'][0]['payload']['items'][0]['due_at'] = 'soon';
        $this->sync($other['ops'])->assertOk()
            ->assertJsonPath('data.stopped_at', null)
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'invalid');
        $this->assertSame(8, PosSyncOperation::count());
    }

    public function test_a_device_price_the_dish_never_had_gives_way_to_the_servers_with_a_conflict_and_an_owner_alert(): void
    {
        $order = (string) Str::uuid();
        $line = $this->line($this->shashlik, '2', '1');
        $response = $this->sync([$this->op('order.open', [
            'order' => $order,
            'channel' => 'pos_takeaway',
            'items' => [$line],
            'menu_taken_at' => now()->subHour()->toJSON(),
        ])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'offline_price_mismatch')
            ->assertJsonPath('data.results.0.refs.flags.0.device_price', '1.00')
            ->assertJsonPath('data.results.0.refs.flags.0.server_price', '18000.00');
        $this->assertStringContainsString('18000', (string) $response->json('data.results.0.message'));

        $item = OrderItem::where('client_line_id', $line['line_id'])->sole();
        $this->assertSame('18000.00', (string) $item->unit_price);
        $this->assertSame('36000.00', (string) $item->total_price);
        $this->assertSame('online', $item->price_source);
        $this->assertSame('36000.00', (string) Order::where('external_id', $order)->value('total_price'));

        $alerts = array_filter(app(TelegramMessenger::class)->sent, fn (array $message) => str_contains($message['text'], 'narx menyudagidan past'));
        $this->assertCount(1, $alerts);

        // A device price above every known one is applied the server's way too, without an alert
        app(TelegramMessenger::class)->sent = [];
        $this->sync([$this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '1', '99000')]])])
            ->assertOk()->assertJsonPath('data.results.0.reason', 'offline_price_mismatch');
        $this->assertSame([], array_values(array_filter(app(TelegramMessenger::class)->sent, fn (array $message) => str_contains($message['text'], 'narx menyudagidan past'))));
    }

    public function test_a_price_the_dish_had_since_the_snapshot_is_kept_as_an_offline_price(): void
    {
        // The till took its menu at 13:00; the owner raised the price at 13:30; at 14:00 the till syncs a sale at the
        // old price
        $menuTakenAt = now();
        Carbon::setTestNow(now()->addMinutes(30));
        $this->shashlik->update(['price' => '20000']);
        Carbon::setTestNow(now()->addMinutes(30));

        $order = (string) Str::uuid();
        $line = $this->line($this->shashlik, '2', '18000');
        $this->sync([$this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$line], 'menu_taken_at' => $menuTakenAt->toJSON()])])
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.flags.0.flag', 'offline_price');

        $item = OrderItem::where('client_line_id', $line['line_id'])->sole();
        $this->assertSame('18000.00', (string) $item->unit_price);
        $this->assertSame('offline_snapshot', $item->price_source);

        // A snapshot taken after the change never had the old price
        $this->sync([$this->op('order.open', [
            'order' => (string) Str::uuid(), 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '1', '18000')],
            'menu_taken_at' => now()->subMinutes(10)->toJSON(),
        ])])->assertOk()->assertJsonPath('data.results.0.reason', 'offline_price_mismatch');
    }

    public function test_a_modifier_price_is_checked_like_the_dishs_and_another_stores_modifier_is_refused(): void
    {
        $group = ModifierGroup::create(['store_id' => $this->store->id, 'name' => "Qo'shimcha"]);
        $sauce = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Sous', 'price_delta' => '3000']);
        $otherStore = Store::whereKeyNot($this->store->id)->first() ?? Store::create(['name' => 'Boshqa', 'slug' => 'boshqa-' . Str::random(4)]);
        $foreignGroup = ModifierGroup::create(['store_id' => $otherStore->id, 'name' => 'Begona']);
        $foreign = Modifier::create(['modifier_group_id' => $foreignGroup->id, 'name' => 'Begona', 'price_delta' => '0']);

        // The till's sauce for nothing: the sauce never cost that
        $line = $this->line($this->shashlik, '1');
        $line['modifiers'] = [['id' => $sauce->uuid, 'price_delta' => '0']];
        $this->sync([$this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway', 'items' => [$line]])])->assertOk()
            ->assertJsonPath('data.results.0.reason', 'offline_price_mismatch');
        $this->assertSame('21000.00', (string) OrderItem::where('client_line_id', $line['line_id'])->value('total_price'));

        $line = $this->line($this->shashlik, '1');
        $line['modifiers'] = [['id' => $foreign->uuid, 'price_delta' => '0']];
        $this->sync([$this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway', 'items' => [$line]])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'modifier_not_found');
    }

    public function test_the_devices_clock_is_corrected_by_the_skew_and_kept_within_48_hours(): void
    {
        // A clock a month ahead: the receipt's sale time is never in the future
        $sale = $this->walkInSale();
        $sale['ops'][2]['occurred_at'] = now()->addDays(30)->toJSON();
        $this->sync($sale['ops'])->assertOk()
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.refs.flags.0.flag', 'device_time_clamped');
        $receipt = FiscalReceipt::where('subject_id', OrderBill::firstOrFail()->id)->where('subject_type', 'order_bill')->sole();
        $this->assertTrue($receipt->sold_at->lte(now()));
        $this->assertTrue($receipt->sold_at->gte($this->shift->opened_at));

        // A clock two hours behind, sent with the same skew: the server's time of it
        $other = $this->walkInSale('K1-002');
        foreach ($other['ops'] as $i => $op) {
            $other['ops'][$i]['occurred_at'] = Carbon::parse($op['occurred_at'])->subHours(2)->toJSON();
        }
        $this->sync($other['ops'], ['sent_at' => now()->subHours(2)->toJSON()])->assertOk()
            ->assertJsonPath('data.clock_warning', true)
            ->assertJsonPath('data.results.2.status', 'applied');
        $payment = Payment::where('order_id', Order::where('external_id', $other['order'])->value('id'))->sole();
        $this->assertSame(now()->subMinutes(19)->toJSON(), $payment->offline_paid_at?->toJSON());
    }
}
