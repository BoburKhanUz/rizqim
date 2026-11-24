<?php

namespace Tests\Feature\CustomerReceipts;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P (receipts): an order keeps its seller as it was when it was placed (orders.seller_snapshot), so the history
 * shows who sold it even after the restaurant changed its details; an order from before has the store's current
 * details. The snapshot reaches the customer as `seller` only.
 */
class OrderSellerSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        DB::table('stores')->where('id', $this->store->id)->update([
            'legal_name' => '"Lazzat Burger" MChJ',
            'tin' => '305123456',
            'legal_address' => 'Toshkent sh., Chilonzor t., Bunyodkor 1',
            'complaint_contact' => '@lazzat_support',
            'phone' => '+998901112233',
        ]);
        $this->store->refresh();
    }

    private function sellerOf(string $uuid): array
    {
        $row = collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data'))->firstWhere('uuid', $uuid);
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('seller_snapshot', $row);

        return $row['seller'];
    }

    public function test_placing_an_order_keeps_the_seller_as_it_was(): void
    {
        $order = $this->checkout($this->customer);

        // jsonb keeps its own key order
        $this->assertEquals([
            'legal_name' => '"Lazzat Burger" MChJ',
            'tin' => '305123456',
            'legal_address' => 'Toshkent sh., Chilonzor t., Bunyodkor 1',
            'phone' => '+998901112233',
            'complaint_contact' => '@lazzat_support',
        ], $order->fresh()->seller_snapshot);

        // The restaurant changes its details later: the order still shows the seller it was bought from
        DB::table('stores')->where('id', $this->store->id)->update(['legal_name' => 'Yangi MChJ', 'legal_address' => 'Boshqa manzil', 'tin' => '311111111']);

        $seller = $this->sellerOf($order->uuid);
        $this->assertSame('"Lazzat Burger" MChJ', $seller['legal_name']);
        $this->assertSame('305123456', $seller['tin']);
        $this->assertSame('Toshkent sh., Chilonzor t., Bunyodkor 1', $seller['legal_address']);
    }

    public function test_an_order_from_before_the_snapshot_shows_the_stores_current_details(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        $this->assertNull($order->fresh()->seller_snapshot);

        $this->assertSame([
            'legal_name' => '"Lazzat Burger" MChJ',
            'tin' => '305123456',
            'legal_address' => 'Toshkent sh., Chilonzor t., Bunyodkor 1',
            'phone' => '+998901112233',
            'complaint_contact' => '@lazzat_support',
        ], $this->sellerOf($order->uuid));
    }

    public function test_a_store_without_a_phone_of_its_own_gives_the_branchs(): void
    {
        DB::table('stores')->where('id', $this->store->id)->update(['phone' => null]);
        DB::table('branches')->where('id', $this->branch->id)->update(['phone' => '+998712223344']);

        $order = $this->checkout($this->customer);

        $this->assertSame('+998712223344', $order->fresh()->seller_snapshot['phone']);
    }
}
