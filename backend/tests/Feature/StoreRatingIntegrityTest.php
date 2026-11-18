<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave M4 H1/H2: a store's public rating is written only by the customer of a delivered order of that store, once per
 * order; staff of any store never rate; a missing order and somebody else's order answer the same 404 (rating and
 * complaints), so order ids cannot be counted.
 */
class StoreRatingIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->otherStore = $this->makeStore('Rival Kebab');
    }

    private function rate(User $user, Store $store, ?int $orderId, int $rating = 1): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson("/api/v1/stores/{$store->id}/rate", array_filter([
            'rating' => $rating,
            'comment' => 'test',
            'order_id' => $orderId,
        ], fn ($v) => $v !== null));
    }

    private function assertNothingRated(): void
    {
        $this->assertSame(0, StoreRating::count());
        $store = $this->store->fresh();
        $this->assertSame(0, (int) $store->review_count);
    }

    public function test_a_stranger_cannot_rate_with_someone_elses_order(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        $stranger = $this->makeCustomer();

        $this->rate($stranger, $this->store, $order->id)->assertNotFound();
        $this->rate($stranger, $this->store, null)->assertStatus(422)->assertJsonValidationErrors('order_id');
        $this->assertNothingRated();
    }

    public function test_staff_of_any_store_cannot_rate(): void
    {
        $rivalOwner = $this->makeStaff(UserRole::OWNER, null, $this->otherStore);
        $ownCashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        // Even with a delivered order of their own in that store
        $rivalOrder = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $rivalOwner->id]);
        $ownOrder = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $ownCashier->id]);
        $ownerOrder = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $this->owner->id]);

        $message = "Do'kon xodimlari baho qoldira olmaydi.";
        $this->rate($rivalOwner, $this->store, $rivalOrder->id)->assertForbidden()->assertJsonPath('message', $message);
        $this->rate($ownCashier, $this->store, $ownOrder->id)->assertForbidden();
        $this->rate($this->owner, $this->store, $ownerOrder->id, 5)->assertForbidden();
        $this->assertNothingRated();
    }

    public function test_an_order_of_another_store_or_an_unfinished_order_is_refused(): void
    {
        $elsewhere = $this->makeOrder(OrderStatus::DELIVERED, ['store_id' => $this->otherStore->id]);
        $this->rate($this->customer, $this->store, $elsewhere->id)->assertNotFound();

        foreach ([OrderStatus::NEW, OrderStatus::ON_THE_WAY, OrderStatus::CANCELED, OrderStatus::REFUNDED] as $status) {
            $order = $this->makeOrder($status);
            $this->rate($this->customer, $this->store, $order->id)->assertStatus(422)
                ->assertJsonPath('message', "Buyurtma yetkazib berilgandan keyin baholash mumkin.");
        }
        $this->assertNothingRated();
    }

    public function test_one_rating_per_order_and_the_aggregate_comes_from_the_rows(): void
    {
        $first = $this->makeOrder(OrderStatus::DELIVERED);
        $second = $this->makeOrder(OrderStatus::DELIVERED);

        $this->rate($this->customer, $this->store, $first->id, 5)->assertOk()
            ->assertJsonPath('data.review_count', 1);
        $this->rate($this->customer, $this->store, $first->id, 1)->assertStatus(400)
            ->assertJsonPath('message', 'Siz ushbu buyurtma uchun allaqachon baho bergansiz.');
        $this->assertSame(5, (int) StoreRating::sole()->rating);

        // A stale aggregate is replaced by the rows, not incremented
        DB::table('stores')->where('id', $this->store->id)->update(['rating' => 1, 'review_count' => 40]);
        $this->rate($this->customer, $this->store, $second->id, 4)->assertOk()
            ->assertJsonPath('data.review_count', 2);
        $store = $this->store->fresh();
        $this->assertSame(2, (int) $store->review_count);
        $this->assertEquals(4.5, (float) $store->rating);
    }

    public function test_the_database_keeps_one_rating_per_order(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        StoreRating::create(['user_id' => $this->customer->id, 'store_id' => $this->store->id, 'order_id' => $order->id, 'rating' => 5]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        StoreRating::create(['user_id' => $this->customer->id, 'store_id' => $this->store->id, 'order_id' => $order->id, 'rating' => 1]);
    }

    public function test_a_missing_and_a_foreign_order_answer_the_same(): void
    {
        $foreign = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $this->makeCustomer()->id]);
        $missing = (int) Order::max('id') + 1000;

        $rateMissing = $this->rate($this->customer, $this->store, $missing);
        $rateForeign = $this->rate($this->customer, $this->store, $foreign->id);
        $rateMissing->assertNotFound();
        $this->assertSame($rateMissing->json(), $rateForeign->json());
        $this->assertSame($rateMissing->status(), $rateForeign->status());

        $complain = fn (int $id) => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/complaints', [
            'order_id' => $id, 'type' => 'OTHER', 'description' => 'Sovuq keldi',
        ]);
        $complainMissing = $complain($missing);
        $complainForeign = $complain($foreign->id);
        $complainMissing->assertNotFound();
        $this->assertSame($complainMissing->json(), $complainForeign->json());
        $this->assertSame($complainMissing->status(), $complainForeign->status());
        $this->assertDatabaseCount('complaints', 0);

        // The customer's own order still works
        $mine = $this->makeOrder(OrderStatus::DELIVERED);
        $complain($mine->id)->assertCreated();
        $complain(0)->assertStatus(422)->assertJsonValidationErrors('order_id');
    }
}
