<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentPaymeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_KEY = 'platform_payme_key';

    protected Store $store;
    protected User $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payme.secret_key' => self::PLATFORM_KEY]);

        $this->store = Store::forceCreate([
            'name' => 'Payme Cafe',
            'slug' => 'payme-cafe',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
            'debt_balance' => 150000,
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998902220000',
            'role' => UserRole::CUSTOMER->value,
            'password' => bcrypt('password'),
        ]);

        $this->order = $this->makeOrder();
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => 'payme',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function rpc(string $method, array $params, ?string $key = self::PLATFORM_KEY)
    {
        $headers = $key === null ? [] : ['Authorization' => 'Basic ' . base64_encode('Paycom:' . $key)];

        return $this->postJson('/api/v1/payments/payme/webhook', [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => $method,
            'params' => $params,
        ], $headers);
    }

    private function create(string $trxId, $account, int $amount = 5000000, int $time = 1760000000000, ?string $key = self::PLATFORM_KEY)
    {
        return $this->rpc('CreateTransaction', [
            'id' => $trxId,
            'time' => $time,
            'amount' => $amount,
            'account' => ['order_id' => $account],
        ], $key);
    }

    public function test_missing_wrong_or_unconfigured_key_is_rejected(): void
    {
        $params = ['amount' => 5000000, 'account' => ['order_id' => $this->order->uuid]];

        $this->rpc('CheckPerformTransaction', $params, null)->assertJsonPath('error.code', -32504);
        $this->rpc('CheckPerformTransaction', $params, 'wrong_key')->assertJsonPath('error.code', -32504);

        config(['services.payme.secret_key' => null]);
        $this->rpc('CheckPerformTransaction', $params, 'payme_sandbox_secret')->assertJsonPath('error.code', -32504);
        $this->rpc('CheckPerformTransaction', $params, '')->assertJsonPath('error.code', -32504);
    }

    public function test_store_key_cannot_authorize_platform_orders_or_be_smuggled_through_account(): void
    {
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'owner_chosen_key']],
        ]);

        $this->create('trx-platform', $this->order->uuid, key: 'owner_chosen_key')->assertJsonPath('error.code', -32504);
        $this->create('trx-platform', $this->order->uuid)->assertJsonPath('result.state', 1);

        // A store collecting into its own account legitimately uses its own key...
        $ownOrder = $this->makeOrder(['payment_owner' => 'STORE']);
        $this->create('trx-own', $ownOrder->uuid, key: 'owner_chosen_key')->assertJsonPath('result.state', 1);

        // ...but that key must not perform another order's transaction by pointing `account` at its own order.
        $this->rpc('PerformTransaction', [
            'id' => 'trx-platform',
            'account' => ['order_id' => $ownOrder->uuid],
        ], 'owner_chosen_key')->assertJsonPath('error.code', -32504);

        $this->assertSame(PaymentStatus::PENDING, Payment::where('provider_transaction_id', 'trx-platform')->first()->status);
        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
    }

    public function test_wrong_amount_is_rejected_with_incorrect_amount_code(): void
    {
        $this->rpc('CheckPerformTransaction', [
            'amount' => 100000,
            'account' => ['order_id' => $this->order->uuid],
        ])->assertJsonPath('error.code', -31001);

        $this->create('trx-cheap', $this->order->uuid, 100000)->assertJsonPath('error.code', -31001);

        $this->assertSame(0, Payment::where('provider', 'payme')->count());
        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
    }

    public function test_unknown_order_uses_account_error_code(): void
    {
        $this->rpc('CheckPerformTransaction', [
            'amount' => 5000000,
            'account' => ['order_id' => 'not-a-real-order'],
        ])->assertJsonPath('error.code', -31050);
    }

    public function test_legitimate_payment_and_repeated_calls_return_the_same_result(): void
    {
        Event::fake([PaymentPaid::class]);

        $this->rpc('CheckPerformTransaction', [
            'amount' => 5000000,
            'account' => ['order_id' => $this->order->uuid],
        ])->assertJsonPath('result.allow', true);

        $created = $this->create('trx-1', $this->order->uuid)->assertJsonPath('result.state', 1);
        $repeated = $this->create('trx-1', $this->order->uuid)->assertJsonPath('result.state', 1);
        $this->assertSame($created->json('result'), $repeated->json('result'));
        $this->assertSame(1760000000000, $created->json('result.create_time'));

        $performed = $this->rpc('PerformTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 2);
        $performedAgain = $this->rpc('PerformTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 2);
        $this->assertSame($performed->json('result'), $performedAgain->json('result'));

        $this->rpc('CheckTransaction', ['id' => 'trx-1'])
            ->assertJsonPath('result.state', 2)
            ->assertJsonPath('result.create_time', 1760000000000)
            ->assertJsonPath('result.perform_time', $performed->json('result.perform_time'));

        Event::assertDispatchedTimes(PaymentPaid::class, 1);
        $this->assertSame(PaymentStatus::PAID, $this->order->fresh()->payment_status);
    }

    public function test_second_transaction_for_the_same_order_cannot_double_pay(): void
    {
        Event::fake([PaymentPaid::class]);

        $this->create('trx-a', $this->order->uuid)->assertJsonPath('result.state', 1);
        $this->create('trx-b', $this->order->uuid)->assertJsonPath('error.code', -31099);

        $this->rpc('PerformTransaction', ['id' => 'trx-a'])->assertJsonPath('result.state', 2);
        $this->create('trx-c', $this->order->uuid)->assertJsonPath('error.code', -31051);

        Event::assertDispatchedTimes(PaymentPaid::class, 1);
        $this->assertSame(1, Payment::where('provider', 'payme')->where('status', PaymentStatus::PAID->value)->count());
    }

    public function test_perform_refuses_a_transaction_whose_order_was_paid_meanwhile(): void
    {
        Event::fake([PaymentPaid::class]);

        $this->create('trx-a', $this->order->uuid)->assertJsonPath('result.state', 1);
        $this->order->update(['payment_status' => PaymentStatus::PAID->value]);

        $this->rpc('PerformTransaction', ['id' => 'trx-a'])->assertJsonPath('error.code', -31008);

        Event::assertNotDispatched(PaymentPaid::class);
        $this->assertSame(PaymentStatus::PENDING, Payment::where('provider_transaction_id', 'trx-a')->first()->status);
    }

    public function test_topup_is_credited_once_and_a_performed_topup_cannot_be_refunded(): void
    {
        $topup = StoreTopupRequest::create([
            'store_id' => $this->store->id,
            'amount' => 100000,
            'provider' => 'payme',
            'status' => 'pending',
        ]);
        $ref = 'topup_' . $topup->uuid;

        $this->create('trx-topup', $ref, 100)->assertJsonPath('error.code', -31001);
        $this->create('trx-topup', $ref, 10000000)->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => 'trx-topup'])->assertJsonPath('result.state', 2);
        $this->rpc('PerformTransaction', ['id' => 'trx-topup'])->assertJsonPath('result.state', 2);

        $this->assertEquals(50000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(1, StoreTransaction::where('store_id', $this->store->id)->where('type', StoreTransactionType::TOPUP->value)->count());

        $this->rpc('CancelTransaction', ['id' => 'trx-topup', 'reason' => 5])->assertJsonPath('error.code', -31007);

        $this->assertEquals(50000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame('paid', $topup->fresh()->status);
    }

    public function test_get_statement_lists_platform_transactions_created_in_the_period(): void
    {
        $second = $this->makeOrder();
        $late = $this->makeOrder();

        $this->create('trx-1', $this->order->uuid, time: 1760000000000)->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 2);
        $this->create('trx-2', $second->uuid, time: 1760000005000)->assertJsonPath('result.state', 1);
        $this->rpc('CancelTransaction', ['id' => 'trx-2', 'reason' => 3])->assertJsonPath('result.state', -1);
        $this->create('trx-late', $late->uuid, time: 1760000100000)->assertJsonPath('result.state', 1);

        // A store's own cashbox transaction is not part of the platform cashbox's statement.
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'owner_chosen_key']],
        ]);
        $own = $this->makeOrder(['payment_owner' => 'STORE']);
        $this->create('trx-own', $own->uuid, time: 1760000002000, key: 'owner_chosen_key')->assertJsonPath('result.state', 1);

        $statement = $this->rpc('GetStatement', ['from' => 1760000000000, 'to' => 1760000010000])->assertOk();

        $this->assertSame(['trx-1', 'trx-2'], array_column($statement->json('result.transactions'), 'id'));
        $statement
            ->assertJsonPath('result.transactions.0.time', 1760000000000)
            ->assertJsonPath('result.transactions.0.create_time', 1760000000000)
            ->assertJsonPath('result.transactions.0.amount', 5000000)
            ->assertJsonPath('result.transactions.0.account.order_id', $this->order->uuid)
            ->assertJsonPath('result.transactions.0.transaction', (string) Payment::where('provider_transaction_id', 'trx-1')->value('id'))
            ->assertJsonPath('result.transactions.0.state', 2)
            ->assertJsonPath('result.transactions.0.reason', null)
            ->assertJsonPath('result.transactions.1.state', -1)
            ->assertJsonPath('result.transactions.1.reason', 3)
            ->assertJsonPath('result.transactions.1.perform_time', 0);
        $this->assertGreaterThan(0, $statement->json('result.transactions.0.perform_time'));
        $this->assertGreaterThan(0, $statement->json('result.transactions.1.cancel_time'));

        $this->rpc('GetStatement', ['from' => 'yesterday'])->assertJsonPath('error.code', -32600);
        $this->rpc('GetStatement', ['from' => 1, 'to' => 2], 'wrong_key')->assertJsonPath('error.code', -32504);
    }

    public function test_cancel_is_idempotent_and_delivered_orders_cannot_be_refunded(): void
    {
        $this->create('trx-1', $this->order->uuid)->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 2);

        $cancelled = $this->rpc('CancelTransaction', ['id' => 'trx-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $again = $this->rpc('CancelTransaction', ['id' => 'trx-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->assertSame($cancelled->json('result'), $again->json('result'));
        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);

        $delivered = $this->makeOrder(['status' => OrderStatus::DELIVERED->value]);
        $this->create('trx-2', $delivered->uuid)->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => 'trx-2'])->assertJsonPath('result.state', 2);
        $this->rpc('CancelTransaction', ['id' => 'trx-2', 'reason' => 5])->assertJsonPath('error.code', -31007);
        $this->assertSame(PaymentStatus::PAID, $delivered->fresh()->payment_status);
    }
}
