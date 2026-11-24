<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\Payment\PaymeCashbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Third review M1: `stores.payme_key_digest` is an index hint, never the source of truth. A digest goes stale when code
 * that does not keep it (a release before the column, after a code-only rollback) saves a store whose owner changed
 * their Payme key: the digest is then non-NULL but of the old key. Every Payme call signed with the store's real key
 * must still be answered for its store, and the digest repaired, so no payment of that store is dropped:
 *  - CheckPerformTransaction / CreateTransaction: the order's own key authorised the call, so its store's merchant
 *    account is always one of the request's (the replay of a CreateTransaction finds its payment);
 *  - the methods about a transaction id: the stores that hold that id (a bounded handful, through an index) are checked
 *    against their decrypted key when their digest does not match;
 *  - `payments:payme-digests` recomputes every digest (after a rollback, and at every deploy).
 */
class PaymeStaleDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Store $store;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payme.secret_key' => 'platform-' . Str::random(24)]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998901213301', 'password' => bcrypt('password')]);
        $this->key = 'new-key-' . Str::random(24);
        $this->store = Store::forceCreate([
            'name' => 'Stale Cafe',
            'status' => 'active',
            'payment_account_type' => 'own_account',
            'credit_limit' => -1000000,
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'pm-stale', 'secret_key' => $this->key]],
        ]);
    }

    /**
     * What a release without the digest hook leaves after the owner changed the key: the digest of the previous key.
     */
    private function stale(?Store $store = null): void
    {
        DB::table('stores')->where('id', ($store ?? $this->store)->id)->update(['payme_key_digest' => PaymeCashbox::digest('old-key-of-the-store')]);
    }

    private function digest(?Store $store = null): ?string
    {
        return DB::table('stores')->where('id', ($store ?? $this->store)->id)->value('payme_key_digest');
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => 'payme',
            'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
    }

    private function payme(string $method, array $params, ?string $key = null)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 5, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . ($key ?? $this->key)),
        ]);
    }

    private function create(string $id, Order $order, ?string $key = null)
    {
        return $this->payme('CreateTransaction', ['id' => $id, 'time' => 1764000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]], $key);
    }

    /**
     * The review's probe, end to end, with the digest stale before the first call.
     */
    public function test_the_probe_sequence_goes_through_with_a_stale_digest_and_repairs_it(): void
    {
        $order = $this->order();
        $this->stale();

        $this->payme('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $order->uuid]])->assertJsonPath('result.allow', true);
        $transaction = $this->create('stale-1', $order)->assertJsonPath('result.state', 1)->json('result.transaction');
        $this->create('stale-1', $order)->assertJsonPath('result.state', 1)->assertJsonPath('result.transaction', $transaction);
        $this->payme('PerformTransaction', ['id' => 'stale-1'])->assertJsonPath('result.state', 2)->assertJsonPath('result.transaction', $transaction);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->payme('CheckTransaction', ['id' => 'stale-1'])->assertJsonPath('result.state', 2);
        $rows = $this->payme('GetStatement', ['from' => 1, 'to' => 1764000000001])->json('result.transactions');
        $this->assertSame(['stale-1'], array_column($rows, 'id'));
        $this->payme('CancelTransaction', ['id' => 'stale-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('order_id', $order->id)->sole()->status);
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
    }

    /**
     * Each method on its own: the digest is made stale again before every call (as if the repair could not be written,
     * or another rollback came in between), and every call is still answered for the store.
     */
    public function test_every_method_finds_its_store_on_its_own_while_the_digest_is_stale(): void
    {
        $order = $this->order();

        $this->stale();
        $this->payme('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $order->uuid]])->assertJsonPath('result.allow', true);
        $this->stale();
        $transaction = $this->create('stale-2', $order)->assertJsonPath('result.state', 1)->json('result.transaction');
        $this->stale();
        $this->create('stale-2', $order)->assertJsonPath('result.state', 1)->assertJsonPath('result.transaction', $transaction);
        $this->stale();
        $this->payme('CheckTransaction', ['id' => 'stale-2'])->assertJsonPath('result.state', 1);
        $this->stale();
        $this->payme('PerformTransaction', ['id' => 'stale-2'])->assertJsonPath('result.state', 2);
        $this->stale();
        $this->payme('PerformTransaction', ['id' => 'stale-2'])->assertJsonPath('result.state', 2);
        $this->stale();
        $this->payme('CancelTransaction', ['id' => 'stale-2', 'reason' => 5])->assertJsonPath('result.state', -2);

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
    }

    /**
     * A transaction created before the rollback (the digest stale from the first call after it): PerformTransaction is
     * the first call that comes, and it finds its payment.
     */
    public function test_a_transaction_method_alone_repairs_the_digest_of_the_store_that_holds_the_id(): void
    {
        $order = $this->order();
        $this->create('stale-3', $order)->assertJsonPath('result.state', 1);
        $this->stale();

        $this->payme('PerformTransaction', ['id' => 'stale-3'])->assertJsonPath('result.state', 2);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
    }

    /**
     * The fallback is bounded and authenticates nothing by itself: a wrong key naming a real id is refused and changes
     * no digest; it reads only the stores that hold that id (never every store), and an id nobody holds reads none.
     */
    public function test_a_wrong_key_is_refused_and_the_fallback_reads_only_the_stores_holding_the_id(): void
    {
        foreach (range(1, 5) as $n) {
            Store::forceCreate(['name' => "Other {$n}", 'status' => 'active', 'payment_account_type' => 'own_account',
                'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => "pm-{$n}", 'secret_key' => "k{$n}-" . Str::random(24)]]]);
        }
        $order = $this->order();
        $this->create('stale-4', $order)->assertJsonPath('result.state', 1);
        $this->stale();
        $stale = $this->digest();

        $read = [];
        Store::retrieved(function (Store $store) use (&$read) {
            $read[] = (int) $store->id;
        });

        $wrong = 'wrong-' . Str::random(24);
        $this->payme('PerformTransaction', ['id' => 'stale-4'], $wrong)->assertJsonPath('error.code', -32504);
        $this->payme('CheckTransaction', ['id' => 'stale-4'], $wrong)->assertJsonPath('error.code', -32504);
        $this->assertSame([(int) $this->store->id], array_values(array_unique($read)));
        $this->assertSame($stale, $this->digest());

        $read = [];
        $this->payme('CheckTransaction', ['id' => 'nobody-holds-this'], $wrong)->assertJsonPath('error.code', -32504);
        $this->payme('GetStatement', ['from' => 1, 'to' => 2], $wrong)->assertJsonPath('error.code', -32504);
        $this->assertSame([], $read);
        $this->assertSame(PaymentStatus::PENDING, Payment::where('order_id', $order->id)->sole()->status);
    }

    /**
     * Fourth review: a repair never overwrites a newer digest. The request verified the store's key K1 (stale digest);
     * meanwhile the owner saved K2 (the model hook wrote digest(K2)); the late repair for K1 must leave digest(K2), or
     * GetStatement signed with K2 would be refused until something repaired it again.
     */
    public function test_a_late_repair_never_overwrites_the_digest_of_a_key_saved_meanwhile(): void
    {
        $this->stale();
        $verified = Store::findOrFail($this->store->id); // what the request read and checked K1 against
        $this->assertNotNull($verified->getRawOriginal('payment_settings'));

        $newKey = 'newer-key-' . Str::random(24);
        $owner = Store::findOrFail($this->store->id);
        $owner->payment_settings = ['payme' => ['enabled' => true, 'merchant_id' => 'pm-stale', 'secret_key' => $newKey]];
        $owner->save();
        $this->assertSame(PaymeCashbox::digest($newKey), $this->digest());

        PaymeCashbox::remember((int) $verified->id, $this->key, $verified->getRawOriginal('payment_settings'));
        $this->assertSame(PaymeCashbox::digest($newKey), $this->digest());
        $this->payme('GetStatement', ['from' => 1763999999999, 'to' => 1764000000001], $newKey)->assertJsonPath('result.transactions', []);

        // While the settings are still the ones verified, the repair goes through
        $this->stale();
        $current = Store::findOrFail($this->store->id);
        PaymeCashbox::remember((int) $current->id, $newKey, $current->getRawOriginal('payment_settings'));
        $this->assertSame(PaymeCashbox::digest($newKey), $this->digest());
    }

    /**
     * The command run after a rollback (docs/OPERATIONS.md) and at every deploy: every digest recomputed from the key,
     * a store whose settings cannot be read left NULL (still matched, retried next time) and counted.
     */
    public function test_the_command_recomputes_every_digest(): void
    {
        $plain = Store::forceCreate(['name' => 'Plain', 'status' => 'active', 'payment_settings' => ['cash' => ['enabled' => true]]]);
        $broken = Store::forceCreate(['name' => 'Broken', 'status' => 'active', 'payment_settings' => ['cash' => ['enabled' => true]]]);
        $this->stale();
        DB::table('stores')->where('id', $plain->id)->update(['payme_key_digest' => null]);
        // Settings encrypted with a key this install does not have (APP_KEY rotated without APP_PREVIOUS_KEYS)
        DB::table('stores')->where('id', $broken->id)->update([
            'payment_settings' => (new \Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString('{"payme":{"secret_key":"x"}}'),
            'payme_key_digest' => PaymeCashbox::digest('x'),
        ]);

        $this->artisan('payments:payme-digests')
            ->expectsOutputToContain('yangilandi: 3, yozilmadi: 1')
            ->assertExitCode(1);

        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
        $this->assertSame(PaymeCashbox::NONE, $this->digest($plain));
        $this->assertNull($this->digest($broken));

        // Idempotent: a second run changes nothing
        DB::table('stores')->where('id', $broken->id)->update(['payment_settings' => Crypt::encryptString('{"cash":{"enabled":true}}')]);
        $this->artisan('payments:payme-digests')->assertExitCode(0);
        $this->artisan('payments:payme-digests')->expectsOutputToContain('yangilandi: 0, yozilmadi: 0')->assertExitCode(0);
        $this->assertSame(PaymeCashbox::NONE, $this->digest($broken));
    }
}
