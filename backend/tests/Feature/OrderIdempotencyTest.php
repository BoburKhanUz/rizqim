<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-21 Idempotency-Key on POST /orders: a retry with the same key and body returns the first response and creates
 * nothing; another body is a 409; failures release the key; keys are per user and expire after 24 hours.
 * Concurrent duplicates: MenuConcurrencyTest.
 */
class OrderIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function items(int $quantity = 1): array
    {
        return [['variant_id' => $this->variant->uuid, 'quantity' => $quantity]];
    }

    public function test_a_retry_with_the_same_key_returns_the_first_order(): void
    {
        $first = $this->order($this->items(2), [], null, ['Idempotency-Key' => 'checkout-1'])->assertCreated();
        $this->assertNull($first->headers->get('Idempotent-Replayed'));

        // Same body, keys in another order: the same request
        $retry = $this->actingAs($this->customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'checkout-1'])->postJson('/api/v1/orders', [
            'items' => $this->items(2),
            'payment_method' => 'cash',
            'branch_id' => $this->branch->id,
            'type' => 'pickup',
            'store_id' => $this->store->id,
        ])->assertCreated();

        $this->assertSame('true', $retry->headers->get('Idempotent-Replayed'));
        $this->assertSame($first->json(), $retry->json());
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $this->variant->fresh()->stock);
    }

    public function test_the_same_key_with_another_body_is_a_conflict(): void
    {
        $this->order($this->items(1), [], null, ['Idempotency-Key' => 'checkout-2'])->assertCreated();

        $this->order($this->items(3), [], null, ['Idempotency-Key' => 'checkout-2'])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Bu Idempotency-Key boshqa so'rov uchun ishlatilgan. Yangi so'rov uchun yangi kalit yuboring.");

        $this->assertSame(1, Order::count());
    }

    public function test_keys_belong_to_one_user(): void
    {
        $other = $this->makeCustomer();

        $this->order($this->items(), [], null, ['Idempotency-Key' => 'same'])->assertCreated();
        $this->order($this->items(), [], $other, ['Idempotency-Key' => 'same'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, Order::count());
        $this->assertSame(2, IdempotencyKey::count());
    }

    public function test_without_a_key_nothing_changes(): void
    {
        $this->order($this->items())->assertCreated();
        $this->order($this->items())->assertCreated();

        $this->assertSame(2, Order::count());
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_a_failed_attempt_releases_the_key_for_the_retry(): void
    {
        $this->variant->update(['stock' => 0]);
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-3'])->assertStatus(422);
        $this->assertSame(0, IdempotencyKey::count());

        $this->variant->update(['stock' => 5]);
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-3'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(1, Order::count());
    }

    public function test_a_key_expires_after_a_day(): void
    {
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-4'])->assertCreated();

        $this->travel(25)->hours();
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-4'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(2, Order::count());

        // Expired keys are pruned
        $this->travel(25)->hours();
        $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_a_key_still_in_progress_answers_409_after_waiting_and_a_dead_one_is_taken_over(): void
    {
        config(['menu.idempotency.wait_seconds' => 0]);
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-5'])->assertCreated();
        $row = IdempotencyKey::firstOrFail();

        // As if the first request were still running
        $row->forceFill(['response_status' => null, 'response_body' => null, 'locked_until' => now()->addMinute()])->save();
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-5'])
            ->assertStatus(409)
            ->assertJsonPath('message', "Shu kalit bilan yuborilgan so'rov hali bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.");

        // ...or had died without answering: the key is free again once its lock ran out
        $row->forceFill(['locked_until' => now()->subSecond()])->save();
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'checkout-5'])->assertCreated();
        $this->assertSame(2, Order::count());
    }

    public function test_an_invalid_key_is_refused(): void
    {
        $this->order($this->items(), [], null, ['Idempotency-Key' => str_repeat('a', 256)])->assertStatus(400);
        $this->order($this->items(), [], null, ['Idempotency-Key' => 'with space'])->assertStatus(400);
        $this->assertSame(0, Order::count());
    }
}
