<?php

namespace Tests\Feature\PosFastFood;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Review M2 (SQLSTATE 40P01 in the fast food lunch e2e and in CI): a Telegram order and a till sale of overlapping dishes
 * deadlocked on product_variants. One side locked its cart's variants FOR UPDATE in id order; the other, holding a
 * combo's variant, inserted the combo's kitchen part lines one by one, and each insert's foreign key took FOR KEY SHARE
 * on the part's variant (a lower id it had never locked). Two lock orders on one table: a cycle.
 *
 * The rule checked here for every path that takes stock or writes order lines: in each transaction the product_variants
 * rows are first touched (locked FOR UPDATE, referenced by an order_items insert, or written) in ascending id, and no
 * variant is first touched after a menu_batches lock. Carts are sent in both line orders.
 */
class StockLockOrderTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    private ProductVariant $burgerCombo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCombo();
        $this->makeCombo();

        // A combo created after its dishes, as a real menu is built: its variant has a higher id than its parts
        $this->burgerCombo = $this->makeVariant($this->makeProduct('Burger kombo', ['mxik_code' => '10202001018000001']), '40000', ['name' => 'Kombo', 'stock' => 1000]);
        $this->saveCombo([
            ['name' => 'Asosiy', 'min_picks' => 1, 'max_picks' => 1, 'options' => [['variant_id' => $this->lavash->id, 'is_default' => true]]],
            ['name' => 'Ichimlik', 'min_picks' => 1, 'max_picks' => 1, 'options' => [['variant_id' => $this->pepsi->id, 'is_default' => true]]],
        ], $this->burgerCombo->product)->assertCreated();
        $this->assertGreaterThan($this->pepsi->id, $this->burgerCombo->id);
    }

    /**
     * Runs $run and returns, per application transaction, the product_variants ids in the order the statements touched
     * them ('B' marks a menu_batches lock or write).
     *
     * @return list<list<int|string>>
     */
    private function touches(callable $run): array
    {
        $ids = ProductVariant::withTrashed()->pluck('id', 'uuid')->mapWithKeys(fn ($id, $uuid) => [strtolower((string) $uuid) => (int) $id])->all();
        $known = array_flip($ids);
        $transactions = [];
        $current = null;

        Event::listen(TransactionBeginning::class, function () use (&$transactions, &$current) {
            if (DB::transactionLevel() === 2) { // level 1 is RefreshDatabase's
                $transactions[] = [];
                $current = array_key_last($transactions);
            }
        });
        DB::listen(function (QueryExecuted $query) use (&$transactions, &$current, $ids, $known) {
            if ($current === null || DB::transactionLevel() < 2) {
                return;
            }
            $sql = $query->sql;
            $bindings = array_values($query->bindings);
            $touched = [];

            if (str_contains($sql, '"menu_batches"') && (str_ends_with($sql, 'for update') || str_starts_with($sql, 'update'))) {
                $transactions[$current][] = 'B';

                return;
            }
            if (str_starts_with($sql, 'select') && str_contains($sql, 'from "product_variants"') && str_ends_with($sql, 'for update')) {
                $byUuid = str_contains($sql, '"uuid" in');
                foreach ($bindings as $binding) {
                    if ($byUuid && is_string($binding) && isset($ids[strtolower($binding)])) {
                        $touched[] = $ids[strtolower($binding)];
                    } elseif (!$byUuid && is_numeric($binding) && isset($known[(int) $binding])) {
                        $touched[] = (int) $binding;
                    }
                }
                sort($touched);
            } elseif (str_starts_with($sql, 'update "product_variants"') && preg_match('/"(?:product_variants"\.")?id" = \?/', $sql, $m, PREG_OFFSET_CAPTURE)) {
                $touched[] = (int) $bindings[substr_count(substr($sql, 0, $m[0][1]), '?')];
            } elseif (str_starts_with($sql, 'insert into "order_items"') && preg_match('/^insert into "order_items" \(([^)]*)\)/', $sql, $m)) {
                $columns = array_map(fn ($c) => trim($c, ' "'), explode(',', $m[1]));
                $at = array_search('product_variant_id', $columns, true);
                if ($at !== false && $bindings[$at] !== null) {
                    $touched[] = (int) $bindings[$at];
                }
            }

            foreach ($touched as $id) {
                $transactions[$current][] = $id;
            }
        });

        $run();
        $current = null;

        return $transactions;
    }

    /** @param list<list<int|string>> $transactions */
    private function assertOneLockOrder(array $transactions, string $what): void
    {
        $this->assertNotEmpty(array_filter($transactions), "{$what}: no stock statement seen");
        foreach ($transactions as $sequence) {
            $seen = [];
            $last = 0;
            $batch = false;
            foreach ($sequence as $touch) {
                if ($touch === 'B') {
                    $batch = true;

                    continue;
                }
                if (isset($seen[$touch])) {
                    continue;
                }
                $order = implode(', ', $sequence);
                $this->assertFalse($batch, "{$what}: variant {$touch} first touched after a menu_batches lock ({$order})");
                $this->assertGreaterThan($last, $touch, "{$what}: variant {$touch} first touched after variant {$last} ({$order})");
                $seen[$touch] = true;
                $last = $touch;
            }
        }
    }

    private function line(ProductVariant $variant, int $quantity = 1): array
    {
        return ['variant_id' => $variant->uuid, 'quantity' => $quantity];
    }

    public function test_a_telegram_order_with_a_combo_locks_the_combo_and_its_parts_in_one_id_order(): void
    {
        foreach ([[$this->line($this->burgerCombo), $this->line($this->pepsi)], [$this->line($this->pepsi), $this->line($this->burgerCombo)]] as $k => $cart) {
            $touches = $this->touches(fn () => $this->order($cart)->assertCreated());
            $this->assertOneLockOrder($touches, "Telegram cart {$k}");
        }

        // Stock as before the fix: the combo and the plain pepsi line taken, the parts' own stock untouched
        $this->assertSame(998, (int) $this->burgerCombo->refresh()->stock);
        $this->assertSame(998, (int) $this->pepsi->refresh()->stock);
        $this->assertSame(1000, (int) $this->lavash->refresh()->stock);
    }

    public function test_a_till_sale_with_a_combo_locks_the_combo_and_its_parts_in_one_id_order(): void
    {
        foreach ([[$this->line($this->burgerCombo), $this->line($this->ayran)], [$this->line($this->ayran), $this->line($this->burgerCombo)]] as $k => $cart) {
            $touches = $this->touches(fn () => $this->counterOrder($cart)->assertCreated());
            $this->assertOneLockOrder($touches, "Till cart {$k}");
        }

        $this->assertSame(998, (int) $this->burgerCombo->refresh()->stock);
        $this->assertSame(998, (int) $this->ayran->refresh()->stock);
        $this->assertSame(1000, (int) $this->pepsi->refresh()->stock);
    }

    public function test_cancelling_a_till_order_gives_the_stock_back_in_id_order(): void
    {
        // Lines in descending id: the cancel used to give the stock back line by line, the higher id first
        $uuid = $this->counterOrder([$this->line($this->garlicSauce, 2), $this->line($this->lavash, 3)], false)->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(998, (int) $this->garlicSauce->refresh()->stock);

        $touches = $this->touches(fn () => app(\App\Domain\Pos\Orders\PosOrderService::class)->cancel($order, (int) $order->version, $this->owner, 'Mijoz ketdi', null));
        $this->assertOneLockOrder($touches, 'Till cancel');

        $this->assertSame(OrderStatus::CANCELED, $order->refresh()->status);
        $this->assertSame(1000, (int) $this->garlicSauce->refresh()->stock);
        $this->assertSame(1000, (int) $this->lavash->refresh()->stock);
    }

    public function test_a_hall_order_with_guest_lines_locks_all_its_variants_before_its_batches(): void
    {
        // The guests' bread and tea (added after the first lines in the same transaction) and a batch-tracked dish
        $bread = $this->makeVariant($this->makeProduct('Non', ['mxik_code' => '10202001019000001']), '3000', ['name' => 'Dona', 'stock' => 1000]);
        $batched = $this->makeVariant(tap($this->makeProduct('Osh', ['mxik_code' => '10202001020000001']), fn (Product $p) => $p->forceFill(['batch_tracked' => true])->save()), '35000', ['name' => 'Porsiya', 'stock' => 1000]);
        app(\App\Services\Catalog\BatchService::class)->open($this->branch, $batched->product, null, \App\Support\Quantity::of(50), null, $this->owner);
        \App\Models\AutoGuestItem::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_variant_id' => $bread->id, 'quantity_per_guest' => '1']);

        $touches = $this->touches(fn () => $this->tillAs('POST', '/pos/orders', [
            'branch_id' => $this->branch->id,
            'channel' => 'pos_hall',
            'guests' => 2,
            'items' => [$this->line($batched), $this->line($this->lavash)],
        ])->assertCreated()->assertJsonCount(3, 'data.items'));
        $this->assertOneLockOrder($touches, 'Till order with guest lines');
    }
}
