<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\ModifierGroup;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\StaffOrderFixtures;
use Tests\TestCase;

/**
 * Wave M2: the write hooks move the right change token (branch, store, customer) for every kind of write, only after
 * the commit, never for a rolled-back write or a liveness stamp, and by exactly 1 per commit.
 */
class RevisionHooksTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private ChangeRevision $revisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->revisions = app(ChangeRevision::class);
    }

    /** @return array<string, string> */
    private function tokens(): array
    {
        return [
            'branch' => $this->revisions->branch($this->branch->id),
            'other' => $this->revisions->branch($this->otherBranch->id),
            'store' => $this->revisions->store($this->store->id),
            'customer' => $this->revisions->user($this->customer->id),
            'courier' => $this->revisions->user($this->courier->id),
        ];
    }

    /** @param list<string> $changed */
    private function assertMoved(array $before, array $changed, string $what): void
    {
        $after = $this->tokens();
        foreach ($before as $scope => $token) {
            if (in_array($scope, $changed, true)) {
                $this->assertNotSame($token, $after[$scope], "{$what}: the {$scope} token must change");
            } else {
                $this->assertSame($token, $after[$scope], "{$what}: the {$scope} token must not change");
            }
        }
    }

    private function hall(): Hall
    {
        return Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Zal ' . Str::random(4), 'sort_order' => 1]);
    }

    public function test_tokens_are_stable_until_a_write_and_move_by_one_per_commit(): void
    {
        $first = $this->revisions->branch($this->branch->id);
        $this->assertSame($first, $this->revisions->branch($this->branch->id));
        $this->assertMatchesRegularExpression('/^\d+$/', $first);

        DB::transaction(function () {
            $hall = $this->hall();
            DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall->id, 'name' => 'Stol 1', 'kind' => 'table']);
            DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall->id, 'name' => 'Stol 2', 'kind' => 'table']);
        });

        $this->assertSame((string) ((int) $first + 1), $this->revisions->branch($this->branch->id), 'three writes in one commit: +1');
    }

    public function test_an_eloquent_insert_update_and_delete_touch_only_their_branch(): void
    {
        $before = $this->tokens();
        $hall = $this->hall();
        $this->assertMoved($before, ['branch'], 'Hall::create');

        $before = $this->tokens();
        $hall->update(['name' => 'Yangi nom']);
        $this->assertMoved($before, ['branch'], 'Hall update');

        $before = $this->tokens();
        $hall->delete();
        $this->assertMoved($before, ['branch'], 'Hall delete');
    }

    public function test_mass_updates_query_builder_writes_and_deletes_are_hooked(): void
    {
        $hall = $this->hall();
        $table = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall->id, 'name' => 'Stol', 'kind' => 'table']);

        $before = $this->tokens();
        DiningTable::query()->where('hall_id', $hall->id)->update(['name' => 'Stol 9']);
        $this->assertMoved($before, ['branch'], 'Eloquent mass update');

        $before = $this->tokens();
        DB::table('dining_tables')->where('id', $table->id)->update(['seats' => 6]);
        $this->assertMoved($before, ['branch'], 'DB::table update');

        $before = $this->tokens();
        DB::table('dining_tables')->where('id', $table->id)->increment('seats');
        $this->assertMoved($before, ['branch'], 'increment');

        // An upsert that updates an existing row by its key, without a (nullable) rule column in the values
        $order = $this->makeOrder(OrderStatus::NEW, ['courier_id' => $this->courier->id]);
        $row = (array) DB::table('orders')->where('id', $order->id)->first();
        unset($row['courier_id']);
        $before = $this->tokens();
        DB::table('orders')->upsert([array_merge($row, ['notes' => 'eshik oldida'])], ['id'], ['notes']);
        $this->assertMoved($before, ['branch', 'customer', 'courier'], 'upsert by key reads the rule columns it left out');

        $before = $this->tokens();
        DB::table('dining_tables')->where('id', $table->id)->delete();
        $this->assertMoved($before, ['branch'], 'DB::table delete');

        $before = $this->tokens();
        DB::table('dining_tables')->where('id', -1)->update(['seats' => 1]);
        $this->assertMoved($before, [], 'an update that matched no row');
    }

    public function test_an_order_touches_its_branch_its_customer_and_its_courier_and_a_move_touches_both_branches(): void
    {
        $before = $this->tokens();
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->assertMoved($before, ['branch', 'customer'], 'new order');

        $before = $this->tokens();
        $order->update(['courier_id' => $this->courier->id]);
        $this->assertMoved($before, ['branch', 'customer', 'courier'], 'courier assigned');

        $before = $this->tokens();
        Order::query()->whereKey($order->id)->update(['branch_id' => $this->otherBranch->id]);
        $this->assertMoved($before, ['branch', 'other', 'customer', 'courier'], 'order moved to the other branch');

        $before = $this->tokens();
        $order->refresh()->delete(); // soft delete
        $this->assertMoved($before, ['other', 'customer', 'courier'], 'order soft-deleted');
    }

    public function test_an_order_line_reaches_the_order_scopes_through_its_parent(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $before = $this->tokens();
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Cheeseburger',
            'variant_name' => 'Regular', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 50000, 'total_price' => 50000,
        ]);
        $this->assertMoved($before, ['branch', 'customer'], 'order line insert');

        $before = $this->tokens();
        $item->update(['quantity' => 2]);
        $this->assertMoved($before, ['branch', 'customer'], 'order line update (model event path)');

        $before = $this->tokens();
        $order->items()->update(['quantity' => 3]);
        $this->assertMoved($before, ['branch', 'customer'], 'relation mass update');
    }

    public function test_a_store_wide_order_without_a_branch_touches_every_branch_of_its_store(): void
    {
        $before = $this->tokens();
        $this->makeOrder(OrderStatus::NEW, ['branch_id' => null]);
        $this->assertMoved($before, ['branch', 'other', 'customer'], 'order without a branch');
    }

    public function test_menu_and_stop_list_writes_touch_the_store_and_every_branch(): void
    {
        $before = $this->tokens();
        $this->variant->update(['price' => 52000]);
        $this->assertMoved($before, ['branch', 'other', 'store'], 'variant price (via products)');

        $before = $this->tokens();
        DB::table('stop_list_entries')->insert([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'variant',
            'subject_id' => $this->variant->id, 'reason' => 'tugadi', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertMoved($before, ['branch', 'other', 'store'], 'stop-list insert');
    }

    public function test_a_pivot_sync_is_hooked(): void
    {
        $group = ModifierGroup::create(['store_id' => $this->store->id, 'name' => 'Sous']);

        $before = $this->tokens();
        $this->product->modifierGroups()->sync([$group->id]);
        $this->assertMoved($before, ['branch', 'other', 'store'], 'pivot attach');

        $before = $this->tokens();
        $this->product->modifierGroups()->detach();
        $this->assertMoved($before, ['branch', 'other', 'store'], 'pivot detach');
    }

    public function test_platform_reference_data_touches_every_store(): void
    {
        $before = $this->tokens();
        Category::query()->first()?->update(['name' => 'Burgerlar']);
        $this->assertMoved($before, ['branch', 'other', 'store'], 'category rename');
    }

    public function test_a_store_feature_flag_touches_only_its_store(): void
    {
        $before = $this->tokens();
        \Laravel\Pennant\Feature::for($this->store)->activate('rizqim-test-flag');
        $this->assertMoved($before, ['branch', 'other', 'store'], 'store feature flag');
    }

    public function test_a_customer_notification_touches_only_the_customer(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $before = $this->tokens();
        NotificationLog::query()->forceCreate(['user_id' => $this->customer->id, 'order_id' => $order->id, 'type' => 'status', 'status' => 'sent']);
        // the order link also reaches nothing else: notification_logs is a USER rule only
        $this->assertMoved($before, ['customer'], 'notification');
    }

    public function test_liveness_stamps_touch_nothing_but_a_real_change_does(): void
    {
        $device = $this->pairTerminal($this->owner, $this->branch)['device'];

        $before = $this->tokens();
        $device->forceFill(['last_seen_at' => now()->addMinute()])->saveQuietly();
        Device::query()->whereKey($device->id)->update(['last_seen_at' => now()->addMinutes(2)]);
        $this->assertMoved($before, [], 'last_seen_at');

        $before = $this->tokens();
        $device->update(['name' => 'Kassa 2']);
        $this->assertMoved($before, ['branch'], 'device rename');
    }

    public function test_a_touch_waits_for_the_commit_and_a_rolled_back_write_touches_nothing(): void
    {
        $before = $this->tokens();
        DB::transaction(function () use ($before) {
            $this->hall();
            $this->assertSame($before['branch'], $this->revisions->branch($this->branch->id), 'not before the commit');
        });
        $this->assertMoved($before, ['branch'], 'after the commit');

        $before = $this->tokens();
        try {
            DB::transaction(function () {
                $this->hall();
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertMoved($before, [], 'rolled back');

        // A later commit still flushes normally (nothing left pending that would swallow a touch)
        $before = $this->tokens();
        $this->hall();
        $this->assertMoved($before, ['branch'], 'the next write');
    }

    public function test_one_flush_per_transaction_survives_a_rolled_back_savepoint(): void
    {
        // M2 review R2-3: one after-commit callback per transaction. A savepoint that rolls back drops the callback it
        // was registered in; the touches around it must still be applied at the outer commit, by exactly 1.
        $before = $this->tokens();
        DB::transaction(function () {
            try {
                DB::transaction(function () {
                    $this->hall(); // registers the flush inside the savepoint
                    throw new RuntimeException('rollback the savepoint');
                });
            } catch (RuntimeException) {
            }
            $this->makeOrder(OrderStatus::NEW); // the outer transaction goes on
        });
        $after = $this->tokens();
        $this->assertSame((string) ((int) $before['branch'] + 1), $after['branch'], 'outer commit: +1');
        $this->assertSame((string) ((int) $before['customer'] + 1), $after['customer']);
        $this->assertSame($before['store'], $after['store']);
    }

    public function test_a_lost_token_comes_back_as_an_unrelated_value_never_an_old_one(): void
    {
        $old = $this->revisions->branch($this->branch->id);
        Cache::store('array')->flush();
        $new = $this->revisions->branch($this->branch->id);
        $this->assertNotSame($old, $new);
        $this->assertGreaterThan(1_000_000_000, (int) $new);

        Cache::store('array')->flush();
        $this->hall(); // a touch on a missing key starts from a random base, not from 1
        $this->assertGreaterThan(1_000_000_000, (int) $this->revisions->branch($this->branch->id));
    }

    public function test_an_unreachable_revision_store_makes_every_read_a_fresh_token(): void
    {
        config(['realtime.revision_store' => 'no-such-store']);
        $revisions = new ChangeRevision;

        $this->assertNotSame($revisions->branch($this->branch->id), $revisions->branch($this->branch->id));
        $revisions->touchBranch($this->branch->id); // never throws
        $this->assertNotSame($revisions->store($this->store->id), $revisions->store($this->store->id));
    }
}
