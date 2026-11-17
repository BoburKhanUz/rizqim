<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PosHallFixtures;
use Tests\TestCase;

/**
 * Wave M2 review R3-1 / R2-2: an ordinary sale moves only the tokens of the screens that show something different
 * (RevisionMap::NARROW_COLUMNS has the per-screen reasoning). Before the fix every settled order and every sold stock
 * line moved the store token and every branch token of the store.
 */
class RevisionNarrowTouchTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    private Branch $third;

    private ChangeRevision $revisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosHall();
        $this->third = $this->makeBranch($this->store, 'Third');
        $this->revisions = app(ChangeRevision::class);
    }

    /** @return array<string, string> */
    private function tokens(): array
    {
        return [
            'store' => $this->revisions->store($this->store->id),
            'branch' => $this->revisions->branch($this->branch->id),
            'other' => $this->revisions->branch($this->otherBranch->id),
            'third' => $this->revisions->branch($this->third->id),
        ];
    }

    /** @return list<string> the scopes whose token moved since $before */
    private function moved(array $before): array
    {
        $after = $this->tokens();

        return array_keys(array_filter($before, fn (string $token, string $scope) => $after[$scope] !== $token, ARRAY_FILTER_USE_BOTH));
    }

    private function deliveredOrder(string $method, string $owner): Order
    {
        $order = Order::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'user_id' => $this->waiter->id, 'total_price' => 100000,
            'status' => OrderStatus::DELIVERED->value, 'payment_method' => $method, 'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PAID->value, 'commission_amount' => 7000,
        ]);

        return Order::findOrFail($order->id);
    }

    private function sell(ProductVariant $variant, string|int $quantity, string $place): void
    {
        $table = $this->place($this->hall, $place, 'table', ['seats' => 4]);
        $this->openPos($this->cashier, $table, 2, ['items' => [$this->line($variant, $quantity)]])->assertCreated();
    }

    public function test_settling_orders_moves_no_store_or_other_branch_token(): void
    {
        foreach ([['click', 'PLATFORM'], ['cash', 'STORE']] as [$method, $owner]) {
            $order = $this->deliveredOrder($method, $owner);
            $before = $this->tokens();
            app(LedgerService::class)->processOrderDelivered($order);
            $this->assertNotContains('store', $this->moved($before), "{$method} settlement: the ledger moved the store token");
            $this->assertSame([], array_intersect(['other', 'third'], $this->moved($before)), "{$method} settlement: the ledger moved other branches");
        }
        $this->assertGreaterThan(0, DB::table('store_transactions')->count(), 'the ledger wrote nothing: the test proves nothing');
    }

    public function test_a_balance_write_moves_nothing_unless_the_debt_lock_flips(): void
    {
        $store = Store::findOrFail($this->store->id);
        $store->forceFill(['credit_limit' => -100000])->save();
        $this->assertFalse($store->fresh()->is_order_locked);

        $before = $this->tokens();
        Store::findOrFail($store->id)->forceFill(['debt_balance' => '50000'])->save();
        DB::table('stores')->where('id', $store->id)->update(['wallet_balance' => 12345]);
        DB::table('stores')->where('id', $store->id)->increment('debt_balance', 1000);
        $this->assertSame([], $this->moved($before), 'balance writes below the credit limit move nothing');

        // Over the limit while the status stays active: is_order_locked (the marketplace store document) flips
        $before = $this->tokens();
        Store::findOrFail($store->id)->forceFill(['debt_balance' => '150000'])->save();
        $this->assertTrue(Store::findOrFail($store->id)->is_order_locked);
        $this->assertEqualsCanonicalizing(['store', 'branch', 'other', 'third'], $this->moved($before), 'Eloquent save that locks');

        $before = $this->tokens();
        DB::table('stores')->where('id', $store->id)->increment('debt_balance', 1000);
        $this->assertSame([], $this->moved($before), 'still locked');

        // The unpaid subscription part never counts against the limit: a mass update that unlocks
        $before = $this->tokens();
        Store::query()->whereKey($store->id)->update(['subscription_debt' => '100000']);
        $this->assertFalse(Store::findOrFail($store->id)->is_order_locked);
        $this->assertEqualsCanonicalizing(['store', 'branch', 'other', 'third'], $this->moved($before), 'mass update that unlocks');

        // Any other column of stores touches as before
        $before = $this->tokens();
        DB::table('stores')->where('id', $store->id)->update(['debt_balance' => 0, 'name' => 'Yangi nom']);
        $this->assertEqualsCanonicalizing(['store', 'branch', 'other', 'third'], $this->moved($before), 'a name change');
    }

    public function test_a_sale_above_the_stock_band_moves_only_the_selling_branch(): void
    {
        $before = $this->tokens();
        $this->sell($this->tea, 1, 'Stol A');
        $this->assertSame(['branch'], $this->moved($before), 'a sold line of a well-stocked variant');
        $this->assertSame('999', (string) ProductVariant::findOrFail($this->tea->id)->stock);
    }

    public function test_stock_inside_the_band_and_the_last_unit_move_the_store_but_no_other_branch(): void
    {
        ProductVariant::query()->whereKey($this->tea->id)->update(['stock' => 25]); // 1000 -> 25: both above the band

        $before = $this->tokens();
        $this->sell($this->tea, 4, 'Stol A'); // 25 -> 21
        $this->assertSame(['branch'], $this->moved($before), 'still above the band');

        $before = $this->tokens();
        $this->sell($this->tea, 1, 'Stol B'); // 21 -> 20: the menu document must show 20
        $this->assertEqualsCanonicalizing(['store', 'branch'], $this->moved($before), 'into the band');

        ProductVariant::query()->whereKey($this->tea->id)->update(['stock' => 1]);
        $before = $this->tokens();
        $this->sell($this->tea, 1, 'Stol C'); // the last unit: sold out on the marketplace
        $this->assertEqualsCanonicalizing(['store', 'branch'], $this->moved($before), 'the last unit');
        $this->assertSame('0', (string) ProductVariant::findOrFail($this->tea->id)->stock);

        // Back in stock (a restock or a void): the store again, still no branch screen
        $before = $this->tokens();
        ProductVariant::query()->whereKey($this->tea->id)->increment('stock', 50);
        $this->assertSame(['store'], $this->moved($before), 'back in stock');

        // Anything else on a variant (a price) is the menu itself: store and every branch, as before
        $before = $this->tokens();
        ProductVariant::findOrFail($this->tea->id)->update(['price' => 6000]);
        $this->assertEqualsCanonicalizing(['store', 'branch', 'other', 'third'], $this->moved($before), 'a price change');
    }

    public function test_a_kazan_portion_moves_its_branch_and_the_store_only_near_the_end(): void
    {
        $batch = MenuBatch::forceCreate([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => 100, 'quantity_left' => 100, 'started_at' => now(), 'label' => '1-qozon',
        ]);

        $before = $this->tokens();
        $this->sell($this->plov, 1, 'Stol A');
        $this->assertSame('99.000', MenuBatch::findOrFail($batch->id)->getRawOriginal('quantity_left'));
        $this->assertSame(['branch'], $this->moved($before), 'a portion of a full kazan');

        MenuBatch::query()->whereKey($batch->id)->update(['quantity_left' => 2]);
        $before = $this->tokens();
        $this->sell($this->plov, 1, 'Stol B');
        $this->assertEqualsCanonicalizing(['store', 'branch'], $this->moved($before), 'a portion near the end');

        // Sold out on the other branch's kazan: that branch and the store, never the selling branch's neighbours
        $other = MenuBatch::forceCreate([
            'store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => 100, 'quantity_left' => 100, 'started_at' => now(), 'label' => '2-qozon',
        ]);
        $before = $this->tokens();
        MenuBatch::query()->whereKey($other->id)->whereNull('closed_at')->decrement('quantity_left', 1);
        $this->assertSame(['other'], $this->moved($before), "a portion of the other branch's kazan");
    }

    public function test_a_place_write_still_touches_only_its_branch(): void
    {
        $before = $this->tokens();
        DiningTable::query()->whereKey($this->table->id)->update(['seats' => 6]);
        $this->assertSame(['branch'], $this->moved($before));
    }
}
