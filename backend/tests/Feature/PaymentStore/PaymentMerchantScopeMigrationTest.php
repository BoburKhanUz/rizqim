<?php

namespace Tests\Feature\PaymentStore;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\User;
use App\Services\Payment\PaymeCashbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security review F5/F6 migrations: payments.merchant_scope (2026_12_09_100100) is backfilled from each payment's order,
 * set by the database for code that does not know it, and makes a provider transaction id unique per merchant account
 * instead of across all of them; stores.payme_key_digest (2026_12_09_100000) is backfilled for every store. Both run
 * again and roll back.
 */
class PaymentMerchantScopeMigrationTest extends TestCase
{
    private const SCOPE = 'migrations/2026_12_09_100100_scope_payment_transaction_ids_by_merchant_account.php';
    private const DIGEST = 'migrations/2026_12_09_100000_add_payme_key_digest_to_stores_table.php';

    private Store $store;
    private ?Store $own = null;
    private User $customer;

    /**
     * Committed fixtures, no wrapping transaction: PostgreSQL refuses CREATE/DROP INDEX CONCURRENTLY inside one. Removed
     * in tearDown, which also leaves both migrations complete whatever a test did.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--force' => true]);
        $this->store = Store::forceCreate(['name' => 'Scope ' . Str::random(6), 'status' => 'active', 'payment_settings' => ['cash' => ['enabled' => true]]]);
        $this->customer = User::create(['name' => 'Mijoz', 'phone' => '+99890' . random_int(1000000, 9999999), 'password' => bcrypt('x')]);
    }

    protected function tearDown(): void
    {
        (require database_path(self::DIGEST))->up();
        DB::table('payments')->where('store_id', $this->store->id)->delete();
        (require database_path(self::SCOPE))->up();
        StoreTopupRequest::where('store_id', $this->store->id)->delete();
        Order::withTrashed()->where('store_id', $this->store->id)->forceDelete();
        $this->own?->forceDelete();
        $this->store->forceDelete();
        $this->customer->forceDelete();

        parent::tearDown();
    }

    private function order(string $owner): Order
    {
        return Order::create(['store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 0,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'click', 'payment_owner' => $owner, 'payment_status' => 'pending']);
    }

    // Does DDL itself: as the schema owner when the roles are split
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_rows_written_before_the_scope_are_backfilled_and_the_identity_is_unique_per_account(): void
    {
        $platform = $this->order('PLATFORM');
        $own = $this->order('STORE');
        // Any value but PLATFORM is the store's own account (OrderMerchantAccount::isStore)
        $legacy = $this->order('OWN');
        $topup = StoreTopupRequest::create(['store_id' => $this->store->id, 'amount' => '100000', 'provider' => 'payme', 'status' => 'pending']);

        $migration = require database_path(self::SCOPE);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('payments', 'merchant_scope'));

        // Written by code before the scope: no merchant_scope column at all
        $ids = [
            'platform' => $this->rawPayment(['order_id' => $platform->id, 'provider_transaction_id' => '5001']),
            'own' => $this->rawPayment(['order_id' => $own->id, 'provider_transaction_id' => '5002']),
            'legacy' => $this->rawPayment(['order_id' => $legacy->id, 'provider_transaction_id' => '5003']),
            'topup' => $this->rawPayment(['order_id' => null, 'store_id' => $this->store->id, 'provider' => 'payme', 'provider_transaction_id' => 'tp-1',
                'payload' => json_encode(['account' => ['order_id' => 'topup_' . $topup->uuid]])]),
        ];

        $migration->up();
        $store = 'store:' . $this->store->id;
        $expected = ['platform' => 'platform', 'own' => $store, 'legacy' => $store, 'topup' => 'platform'];
        $this->assertScopes($ids, $expected);
        $this->assertComplete();
        $migration->up();
        $this->assertComplete();

        // Code that does not know the column (or names a wrong one) gets the order's account from the database
        $raw = $this->rawPayment(['order_id' => $own->id, 'provider_transaction_id' => '5004']);
        $wrong = $this->rawPayment(['order_id' => $platform->id, 'provider_transaction_id' => '5005', 'merchant_scope' => $store]);
        $this->assertScopes(['raw' => $raw, 'wrong' => $wrong], ['raw' => $store, 'wrong' => 'platform']);

        // The same id in another account is another payment; twice in one account it is refused
        $this->rawPayment(['order_id' => $own->id, 'provider_transaction_id' => '5001']);
        try {
            DB::transaction(fn () => $this->rawPayment(['order_id' => $platform->id, 'provider_transaction_id' => '5001']));
            $this->fail('the same transaction id twice in one merchant account');
        } catch (QueryException $e) {
            $this->assertStringContainsString('payments_provider_scope_transaction_unique', $e->getMessage());
        }

        // A rollback that would have to drop one of them is refused, changing nothing
        try {
            $migration->down();
            $this->fail('down() with an id recorded in two accounts');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('merchant', $e->getMessage());
        }
        $this->assertComplete();

        DB::table('payments')->where('provider_transaction_id', '5001')->where('merchant_scope', $store)->delete();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('payments', 'merchant_scope'));
        $this->assertTrue($this->constraintExists('payments_provider_provider_transaction_id_unique'));
        $migration->up();
        $this->assertScopes($ids, $expected);
        $this->assertComplete();
    }

    #[\Tests\Concerns\AsSchemaOwner]
    public function test_every_stores_payme_key_digest_is_backfilled_and_it_runs_again_and_rolls_back(): void
    {
        $key = 'own-key-' . Str::random(20);
        $own = $this->own = Store::forceCreate(['name' => 'Own ' . Str::random(6), 'status' => 'active',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'm', 'secret_key' => $key]]]);

        $migration = require database_path(self::DIGEST);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('stores', 'payme_key_digest'));
        $migration->up();

        $this->assertSame(PaymeCashbox::digest($key), DB::table('stores')->where('id', $own->id)->value('payme_key_digest'));
        $this->assertSame(PaymeCashbox::NONE, DB::table('stores')->where('id', $this->store->id)->value('payme_key_digest'));
        $this->assertSame(0, DB::table('stores')->whereNull('payme_key_digest')->count());
        $this->assertSame([(int) $own->id], PaymeCashbox::storesWithKey($key));
        $migration->up();
        $this->assertSame(PaymeCashbox::digest($key), DB::table('stores')->where('id', $own->id)->value('payme_key_digest'));

        // A store the backfill has not reached yet is still matched (never a dropped Payme call)
        DB::table('stores')->where('id', $own->id)->update(['payme_key_digest' => null]);
        $this->assertSame([(int) $own->id], PaymeCashbox::storesWithKey($key));
        $migration->up();
        $this->assertSame(PaymeCashbox::digest($key), DB::table('stores')->where('id', $own->id)->value('payme_key_digest'));
    }

    /**
     * Third review M1/L3: the backfill recomputes every row (a digest left stale by a release without the digest hook is
     * repaired by running it again), and a store whose settings cannot be decrypted is left NULL (still matched, retried
     * on the next run), never written `none` (which nothing would ever match or retry).
     */
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_the_digest_backfill_recomputes_stale_rows_and_leaves_unreadable_ones_null(): void
    {
        $key = 'own-key-' . Str::random(20);
        $own = $this->own = Store::forceCreate(['name' => 'Own ' . Str::random(6), 'status' => 'active',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'm', 'secret_key' => $key]]]);
        DB::table('stores')->where('id', $own->id)->update(['payme_key_digest' => PaymeCashbox::digest('the-previous-key')]);
        $unreadable = (new \Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString(json_encode(['payme' => ['secret_key' => 'x']]));
        DB::table('stores')->where('id', $this->store->id)->update(['payment_settings' => $unreadable, 'payme_key_digest' => null]);

        try {
            (require database_path(self::DIGEST))->up();

            $this->assertSame(PaymeCashbox::digest($key), DB::table('stores')->where('id', $own->id)->value('payme_key_digest'));
            $this->assertNull(DB::table('stores')->where('id', $this->store->id)->value('payme_key_digest'));
        } finally {
            DB::table('stores')->where('id', $this->store->id)->update(['payment_settings' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['cash' => ['enabled' => true]]))]);
        }
    }

    /**
     * @param  array<string, int>  $ids
     * @param  array<string, string>  $scopes
     */
    private function assertScopes(array $ids, array $scopes): void
    {
        foreach ($ids as $name => $id) {
            $this->assertSame($scopes[$name], DB::table('payments')->where('id', $id)->value('merchant_scope'), $name);
        }
    }

    private function assertComplete(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'merchant_scope'));
        $this->assertFalse(DB::selectOne("SELECT NOT attnotnull AS n FROM pg_attribute WHERE attrelid = 'payments'::regclass AND attname = 'merchant_scope'")->n);
        $this->assertFalse($this->constraintExists('payments_provider_provider_transaction_id_unique'));
        $this->assertFalse($this->constraintExists('payments_merchant_scope_not_null'));
        $this->assertTrue(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid WHERE c.relname = 'payments_provider_scope_transaction_unique' AND i.indisunique AND i.indisvalid) AS e")->e);
        $this->assertSame(0, DB::table('payments')->whereNull('merchant_scope')->count());
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = ?) AS e", [$name])->e;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rawPayment(array $attributes): int
    {
        return (int) DB::table('payments')->insertGetId($attributes + [
            'uuid' => (string) Str::uuid(),
            'provider' => 'click',
            'amount' => '1000.00',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
