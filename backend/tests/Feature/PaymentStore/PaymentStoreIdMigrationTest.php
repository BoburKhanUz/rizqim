<?php

namespace Tests\Feature\PaymentStore;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: payments.store_id (migration 2026_11_27_100000). Rows written before it are backfilled, a row written by
 * code that does not know the column gets its store from the database, the column always equals the parent's store,
 * and the migration can run again and be rolled back.
 */
class PaymentStoreIdMigrationTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const MIGRATION = 'migrations/2026_11_27_100000_add_store_id_to_payments_table.php';

    private Store $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        // Ids that differ from the first store's, so a wrong store is visible
        $this->other = $this->makeStore('Other Store');
        $this->makeBranch($this->other, 'Other Branch');
    }

    // M4: does DDL itself: as the schema owner when the roles are split
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_rows_written_before_the_migration_are_backfilled_and_it_runs_again_and_rolls_back(): void
    {
        $mine = $this->makeOrder(OrderStatus::NEW);
        $theirs = $this->makeOrder(OrderStatus::NEW, ['store_id' => $this->other->id]);
        $topup = $this->topup($this->other);

        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('payments', 'store_id'));

        // Written by the code before M4: no store_id column at all
        $ids = [
            'mine' => $this->rawPayment(['order_id' => $mine->id]),
            'theirs' => $this->rawPayment(['order_id' => $theirs->id]),
            'topup' => $this->rawPayment(['order_id' => null, 'provider' => 'payme', 'payload' => json_encode(['account' => ['order_id' => 'topup_' . $topup->uuid]])]),
        ];

        $migration->up();
        $this->assertStores($ids, ['mine' => $this->store->id, 'theirs' => $this->other->id, 'topup' => $this->other->id]);
        $this->assertComplete();

        $migration->up();
        $this->assertComplete();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('payments', 'store_id'));
        $this->assertNull(DB::selectOne("SELECT to_regprocedure('payments_parent_store(bigint, jsonb)') AS f")->f);
        $migration->up();
        $this->assertStores($ids, ['mine' => $this->store->id, 'theirs' => $this->other->id, 'topup' => $this->other->id]);
        $this->assertComplete();
    }

    #[\Tests\Concerns\AsSchemaOwner]
    public function test_a_payment_without_a_parent_store_leaves_the_column_nullable_and_the_migration_completes(): void
    {
        $mine = $this->makeOrder(OrderStatus::NEW);
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $resolved = $this->rawPayment(['order_id' => $mine->id]);
        // No order, and a top-up reference that names no request: nothing to take the store from
        $orphan = $this->rawPayment(['order_id' => null, 'provider' => 'payme', 'payload' => json_encode(['account' => ['order_id' => 'topup_' . Str::uuid()]])]);
        Log::spy();

        $migration->up();

        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context) => $context['payments'] === [$orphan]);
        $this->assertSame($this->store->id, (int) DB::table('payments')->where('id', $resolved)->value('store_id'));
        $this->assertNull(DB::table('payments')->where('id', $orphan)->value('store_id'));
        $this->assertTrue($this->nullable());
        $this->assertFalse($this->constraintExists('payments_store_id_not_null'), 'no check is left that would refuse an update of the orphan row');
        $this->assertTrue($this->constraintValid('payments_store_id_foreign'));
        // The orphan row can still be updated (a Payme cancel of it answers instead of failing)
        DB::table('payments')->where('id', $orphan)->update(['status' => 'cancelled']);
    }

    public function test_a_row_written_by_code_that_does_not_know_the_column_gets_the_parent_store(): void
    {
        $theirs = $this->makeOrder(OrderStatus::NEW, ['store_id' => $this->other->id]);
        $topup = $this->topup($this->other);

        $order = $this->rawPayment(['order_id' => $theirs->id]);
        $top = $this->rawPayment(['order_id' => null, 'provider' => 'payme', 'payload' => json_encode(['account' => ['order_id' => 'topup_' . strtoupper($topup->uuid)]])]);

        $this->assertSame($this->other->id, (int) DB::table('payments')->where('id', $order)->value('store_id'));
        $this->assertSame($this->other->id, (int) DB::table('payments')->where('id', $top)->value('store_id'));
    }

    public function test_the_column_always_equals_the_parent_store(): void
    {
        $mine = $this->makeOrder(OrderStatus::NEW);
        $theirs = $this->makeOrder(OrderStatus::NEW, ['store_id' => $this->other->id]);

        // A store that is not the order's is replaced by the order's (no payment fails because of this column)
        $payment = Payment::create(['order_id' => $mine->id, 'store_id' => $this->other->id, 'provider' => 'cash', 'amount' => '1000', 'status' => 'paid']);
        $this->assertSame($this->store->id, $payment->fresh()->store_id);

        DB::table('payments')->where('id', $payment->id)->update(['store_id' => $this->other->id]);
        $this->assertSame($this->store->id, $payment->fresh()->store_id);

        // Moved to another order (no code path does it today): the store follows the order
        DB::table('payments')->where('id', $payment->id)->update(['order_id' => $theirs->id]);
        $this->assertSame($this->other->id, $payment->fresh()->store_id);

        // Any other update leaves it alone
        DB::table('payments')->where('id', $payment->id)->update(['status' => 'refunded']);
        $this->assertSame($this->other->id, $payment->fresh()->store_id);

        $this->assertSame(0, $this->mismatches());
    }

    public function test_a_payment_whose_store_cannot_be_found_is_refused_by_the_not_null_column(): void
    {
        // No creation site writes one (each has an order or a top-up request); a malformed reference is not a cast error
        foreach ([null, json_encode(['account' => ['order_id' => 'topup_not-a-uuid']]), json_encode(['account' => ['order_id' => 'topup_' . Str::uuid()]]), json_encode([1, 2])] as $payload) {
            try {
                DB::transaction(fn () => $this->rawPayment(['order_id' => null, 'payload' => $payload]));
                $this->fail('a payment without a store was stored');
            } catch (QueryException $e) {
                $this->assertSame('23502', $e->getCode(), $e->getMessage()); // not_null_violation
            }
        }
    }

    /**
     * @param  array<string, int>  $ids
     * @param  array<string, int>  $stores
     */
    private function assertStores(array $ids, array $stores): void
    {
        $this->assertSame($stores, array_map(fn (int $id) => (int) DB::table('payments')->where('id', $id)->value('store_id'), $ids));
        $this->assertSame(0, $this->mismatches());
    }

    private function assertComplete(): void
    {
        $this->assertFalse($this->nullable(), 'payments.store_id is NOT NULL');
        $this->assertTrue($this->constraintValid('payments_store_id_foreign'));
        $this->assertFalse($this->constraintExists('payments_store_id_not_null'), 'the helper check is dropped once NOT NULL is set');
        $this->assertSame(['payments_store_id_insert', 'payments_store_id_update'], collect(DB::select("SELECT tgname FROM pg_trigger WHERE tgrelid = 'payments'::regclass AND tgname LIKE 'payments_store_id%' AND tgenabled = 'O' ORDER BY tgname"))->pluck('tgname')->all());
    }

    /**
     * Rows whose store is not their parent's (the order's, or the top-up request's).
     */
    private function mismatches(): int
    {
        return (int) DB::selectOne(<<<'SQL'
            SELECT count(*) AS n FROM payments p
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN store_topup_requests t ON p.order_id IS NULL AND 'topup_' || t.uuid = lower(p.payload -> 'account' ->> 'order_id')
            WHERE p.store_id IS DISTINCT FROM coalesce(o.store_id, t.store_id)
            SQL)->n;
    }

    private function nullable(): bool
    {
        return DB::selectOne("SELECT NOT attnotnull AS n FROM pg_attribute WHERE attrelid = 'payments'::regclass AND attname = 'store_id'")->n;
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = ?) AS e", [$name])->e;
    }

    private function constraintValid(string $name): bool
    {
        return (bool) DB::selectOne("SELECT bool_or(convalidated) AS v FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = ?", [$name])->v;
    }

    private function topup(Store $store): StoreTopupRequest
    {
        return StoreTopupRequest::create(['store_id' => $store->id, 'amount' => '100000', 'provider' => 'payme', 'status' => 'pending']);
    }

    /**
     * A payment as code written before M4 inserts it (raw, no store_id).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function rawPayment(array $attributes): int
    {
        return (int) DB::table('payments')->insertGetId($attributes + [
            'uuid' => (string) Str::uuid(),
            'provider' => 'cash',
            'amount' => '1000.00',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
