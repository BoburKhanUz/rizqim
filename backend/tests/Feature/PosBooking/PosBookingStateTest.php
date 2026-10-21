<?php

namespace Tests\Feature\PosBooking;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-17: the booking's states (research report §4 diagram), optimistic versions, pre-ordered dishes, the calendar and
 * the search by phone.
 */
class PosBookingStateTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    private function act(string $uuid, string $action, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/{$action}", $data);
    }

    public function test_requested_confirmed_canceled(): void
    {
        $uuid = $this->booked();

        $this->act($uuid, 'confirm')->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.status_label', 'Tasdiqlangan');
        $this->act($uuid, 'confirm')->assertStatus(422)->assertJsonPath('data.reason', 'invalid_transition');

        $this->act($uuid, 'cancel')->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->act($uuid, 'cancel', ['reason' => 'Mehmonlar boshqa kunga oldi'])->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.cancel_reason', 'Mehmonlar boshqa kunga oldi');

        // Over: nothing moves it any more, nothing changes it
        $this->act($uuid, 'confirm')->assertStatus(422);
        $this->act($uuid, 'arrive')->assertStatus(422);
        $this->act($uuid, 'no-show')->assertStatus(422);
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['guests_planned' => 3])->assertStatus(422)
            ->assertJsonPath('data.reason', 'booking_not_editable');

        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.canceled']);
    }

    public function test_created_confirmed_and_a_no_show_only_after_the_start(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(10, 0)); // UTC: 15:00 in Tashkent

        $uuid = $this->createBooking(['confirm' => true])->assertCreated()->assertJsonPath('data.status', 'confirmed')->json('data.uuid');
        $this->act($uuid, 'no-show')->assertStatus(422)->assertJsonPath('data.reason', 'booking_not_started');

        $this->travelTo(now()->setTime(14, 0)); // 19:00 in Tashkent: the booked start
        $this->act($uuid, 'no-show')->assertOk()->assertJsonPath('data.status', 'no_show');
    }

    public function test_a_requested_booking_cannot_be_a_no_show(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6));
        $uuid = $this->booked();

        $this->act($uuid, 'no-show')->assertStatus(422)->assertJsonPath('data.reason', 'invalid_transition');
    }

    public function test_a_stale_version_is_a_409(): void
    {
        $uuid = $this->booked();

        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['guests_planned' => 10, 'version' => 1])->assertOk()
            ->assertJsonPath('data.version', 2);
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['guests_planned' => 11, 'version' => 1])->assertStatus(409)
            ->assertJsonPath('data.reason', 'stale_version')->assertJsonPath('data.version', 2);
        $this->act($uuid, 'confirm', ['version' => 1])->assertStatus(409);
        $this->act($uuid, 'confirm', ['version' => 2])->assertOk()->assertJsonPath('data.version', 3);
    }

    public function test_the_order_ending_completes_an_arrived_booking(): void
    {
        $uuid = $this->booked();
        $this->act($uuid, 'arrive')->assertOk()->assertJsonPath('data.booking.status', 'arrived');
        $order = Order::where('booking_id', Booking::where('uuid', $uuid)->value('id'))->firstOrFail();

        // Still open: nothing happens
        event(new OrderStatusUpdated($order));
        $this->assertSame('arrived', Booking::where('uuid', $uuid)->firstOrFail()->status->value);

        $order->forceFill(['status' => OrderStatus::DELIVERED])->save();
        event(new OrderStatusUpdated($order->refresh()));
        $booking = Booking::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('completed', $booking->status->value);
        $this->assertNotNull($booking->completed_at);
    }

    public function test_pre_ordered_dishes_with_kg_quantities_modifiers_and_due_times(): void
    {
        [$osh, $kazi] = $this->oshWithKazi();
        $uuid = $this->booked();

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", [
            'product_variant_id' => $osh->id,
            'quantity' => 3.5,
            'modifiers' => [['modifier_id' => $kazi->id, 'quantity' => 2]],
            'note' => 'Devzira guruch',
            'due_at' => '2026-11-05T19:30:00+05:00',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.product_name', 'Osh')
            ->assertJsonPath('data.items.0.unit', 'kg')
            ->assertJsonPath('data.items.0.quantity', 3.5)
            ->assertJsonPath('data.items.0.modifiers.0.name', 'Qazi')
            ->assertJsonPath('data.items.0.modifiers.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '120000.00')
            ->assertJsonPath('data.items.0.due_at', '2026-11-05T14:30:00.000000Z')
            ->assertJsonPath('data.items_count', 1);

        // The kg step (0.5) and the dish's own store are checked
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => $osh->id, 'quantity' => 1.2])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => $this->foreignVariant()->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('product_variant_id');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => $osh->id, 'quantity' => 1, 'modifiers' => [['modifier_id' => 999999]]])
            ->assertStatus(422)->assertJsonValidationErrors('modifiers');

        // Without a due time: the guests' arrival
        $second = $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => $this->variant->id, 'quantity' => 2])
            ->assertCreated()->json('data.items');
        $burger = collect($second)->firstWhere('product_variant_id', $this->variant->id);
        $this->assertSame('2026-11-05T14:00:00.000000Z', $burger['due_at']);

        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}/items/{$burger['id']}", ['quantity' => 4])->assertOk();
        $this->assertSame('4.000', DB::table('booking_items')->where('id', $burger['id'])->value('quantity'));
        $this->api($this->cashier, 'DELETE', "/api/v1/pos/bookings/{$uuid}/items/{$burger['id']}")->assertOk()->assertJsonPath('data.items_count', 1);
        $this->api($this->cashier, 'DELETE', "/api/v1/pos/bookings/{$uuid}/items/{$burger['id']}")->assertNotFound();

        // Not once the booking is over
        $this->act($uuid, 'cancel', ['reason' => 'Bekor'])->assertOk();
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => $osh->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('data.reason', 'booking_not_editable');
    }

    public function test_the_calendar_lists_rooms_with_their_bookings(): void
    {
        $evening = $this->booked(['dining_table_id' => $this->roomA, 'extra_table_ids' => [$this->roomB]]);
        $lunch = $this->booked(['dining_table_id' => $this->roomC, 'starts_at' => '2026-11-05T12:00:00+05:00', 'ends_at' => '2026-11-05T16:00:00+05:00']);
        $noRoom = $this->booked(['dining_table_id' => null]);
        $nextDay = $this->booked(['starts_at' => '2026-11-06T19:00:00+05:00', 'ends_at' => '2026-11-06T23:00:00+05:00']);
        $canceled = $this->booked(['dining_table_id' => $this->roomC, 'starts_at' => '2026-11-05T19:00:00+05:00']);
        $this->act($canceled, 'cancel', ['reason' => 'x'])->assertOk();
        $this->makeRoom($this->otherBranch, 'Begona xona');

        $data = $this->api($this->cashier, 'GET', '/api/v1/pos/bookings/calendar?from=2026-11-05&to=2026-11-05')->assertOk()->json('data');

        $this->assertSame($this->branch->id, $data['branch_id']);
        $this->assertSame('Asia/Tashkent', $data['timezone']);
        $rooms = collect($data['rooms'])->keyBy('id');
        $this->assertSame([$this->roomA, $this->roomB, $this->roomC], $rooms->keys()->all());
        $this->assertSame([$evening], array_column($rooms[$this->roomA]['bookings'], 'uuid'));
        $this->assertSame([$evening], array_column($rooms[$this->roomB]['bookings'], 'uuid'));
        $this->assertSame([$lunch], array_column($rooms[$this->roomC]['bookings'], 'uuid'));
        $this->assertSame([$noRoom], array_column($data['unassigned'], 'uuid'));
        $this->assertNotContains($nextDay, array_column($rooms[$this->roomA]['bookings'], 'uuid'));

        $withCanceled = $this->api($this->cashier, 'GET', '/api/v1/pos/bookings/calendar?from=2026-11-05&to=2026-11-06&include_canceled=1')->json('data.rooms');
        $this->assertSame([$lunch, $canceled], array_column(collect($withCanceled)->firstWhere('id', $this->roomC)['bookings'], 'uuid'));
        $this->assertSame([$evening, $nextDay], array_column(collect($withCanceled)->firstWhere('id', $this->roomA)['bookings'], 'uuid'));

        $this->api($this->cashier, 'GET', '/api/v1/pos/bookings/calendar?from=2026-11-05&to=2026-12-31')->assertStatus(422);
        $this->api($this->cashier, 'GET', '/api/v1/pos/bookings/calendar?from=2026-11-05&to=2026-11-01')->assertStatus(422);
    }

    public function test_search_by_phone_shows_the_guest_history(): void
    {
        $first = $this->booked(['starts_at' => '2026-10-01T19:00:00+05:00', 'ends_at' => '2026-10-01T23:00:00+05:00']);
        $second = $this->booked();
        $this->booked(['dining_table_id' => $this->roomB, 'contact_phone' => '+998 93 555 44 33']);
        $this->act($first, 'cancel', ['reason' => 'x'])->assertOk();

        $found = $this->api($this->cashier, 'GET', '/api/v1/pos/bookings?phone=901234567')->assertOk()->json('data.bookings');
        $this->assertSame([$second, $first], array_column($found, 'uuid'));
        $this->assertSame([$second, $first], array_column($this->api($this->cashier, 'GET', '/api/v1/pos/bookings?phone=' . urlencode('+998 (90) 123-45-67'))->json('data.bookings'), 'uuid'));
        $this->assertSame([$second], array_column($this->api($this->cashier, 'GET', '/api/v1/pos/bookings?phone=4567&status=requested')->json('data.bookings'), 'uuid'));
        $this->api($this->cashier, 'GET', '/api/v1/pos/bookings?phone=45')->assertStatus(422);

        // Without a phone: the day's bookings
        $this->assertCount(2, $this->api($this->cashier, 'GET', '/api/v1/pos/bookings?from=2026-11-05')->json('data.bookings'));
    }

    /**
     * @return array{0: ProductVariant, 1: Modifier}
     */
    private function oshWithKazi(): array
    {
        $category = Category::create(['name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'is_active' => true]);
        $product = Product::create(['store_id' => $this->store->id, 'category_id' => $category->id, 'name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'status' => 'active']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => null, 'price' => 120000, 'stock' => 100, 'is_active' => true, 'uuid' => (string) Str::uuid(),
            'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '1',
        ]);
        $group = ModifierGroup::create(['store_id' => $this->store->id, 'name' => "Qo'shimcha", 'min_select' => 0, 'max_select' => null, 'is_required' => false, 'is_active' => true]);
        $kazi = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Qazi', 'price_delta' => 30000, 'max_quantity' => 3, 'is_active' => true]);
        DB::table('product_modifier_groups')->insert(['product_id' => $product->id, 'modifier_group_id' => $group->id, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return [$variant, $kazi];
    }

    private function foreignVariant(): ProductVariant
    {
        $category = Category::create(['name' => 'X', 'slug' => 'x-' . Str::random(5), 'is_active' => true]);
        $product = Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $category->id, 'name' => 'Begona', 'slug' => 'b-' . Str::random(5), 'status' => 'active']);

        return ProductVariant::create(['product_id' => $product->id, 'name' => 'R', 'price' => 1000, 'stock' => 1, 'is_active' => true, 'uuid' => (string) Str::uuid()]);
    }
}
