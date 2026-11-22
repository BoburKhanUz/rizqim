<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-payments (go-live): the Payme Merchant API protocol details the sandbox and Payme's own checks rely on
 * (developer.help.paycom.uz: "Формат ответа", "Общие ошибки", GetStatement): a localized `message` object shown to the
 * customer, the general error codes (-32700, -32601 with the method in `data`, -32400), whole-tiyin amounts and the
 * statement of a restaurant's own cashbox.
 */
class PaymentPaymeProtocolTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_KEY = 'platform_payme_key';

    private const STORE_KEY = 'store_cashbox_key';

    protected Store $store;

    protected User $customer;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payme.secret_key' => self::PLATFORM_KEY]);

        $this->store = Store::forceCreate([
            'name' => 'Payme Cafe',
            'slug' => 'payme-proto-cafe',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998902220099',
            'role' => UserRole::CUSTOMER->value,
            'password' => bcrypt('password'),
        ]);

        $this->order = $this->makeOrder();
    }

    private function makeOrder(array $attributes = [], ?Store $store = null): Order
    {
        return Order::create(array_merge([
            'store_id' => ($store ?? $this->store)->id,
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
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $key),
        ]);
    }

    private function create(string $trxId, Order $order, int $time, string $key = self::PLATFORM_KEY)
    {
        return $this->rpc('CreateTransaction', ['id' => $trxId, 'time' => $time, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]], $key);
    }

    public function test_errors_carry_a_localized_message_with_uzbek_text(): void
    {
        $notFound = $this->rpc('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => 'no-such-order']])
            ->assertJsonPath('error.code', -31050)
            ->assertJsonPath('error.data', 'order_id');
        $this->assertSame(['uz', 'ru', 'en'], array_keys($notFound->json('error.message')));
        $this->assertSame('Buyurtma topilmadi', $notFound->json('error.message.uz'));

        $this->rpc('CheckPerformTransaction', ['amount' => 100, 'account' => ['order_id' => $this->order->uuid]])
            ->assertJsonPath('error.code', -31001)
            ->assertJsonPath('error.message.uz', "Noto'g'ri summa")
            ->assertJsonPath('error.message.ru', 'Неверная сумма');

        $this->rpc('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $this->order->uuid]], 'wrong')
            ->assertJsonPath('error.code', -32504)
            ->assertJsonPath('error.message.en', 'Insufficient privileges');

        $this->rpc('CheckTransaction', ['id' => 'missing'])
            ->assertJsonPath('error.code', -31003)
            ->assertJsonPath('error.message.uz', 'Tranzaksiya topilmadi');
    }

    public function test_an_unknown_method_names_itself_in_data(): void
    {
        $this->rpc('ChangePassword', ['password' => 'x'])
            ->assertJsonPath('error.code', -32601)
            ->assertJsonPath('error.data', 'ChangePassword');
    }

    public function test_a_body_that_is_not_json_is_a_parse_error(): void
    {
        $this->call('POST', '/api/v1/payments/payme/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('Paycom:' . self::PLATFORM_KEY),
        ], '{"method": "CheckPerformTransaction", ')
            ->assertOk()
            ->assertJsonPath('error.code', -32700);
    }

    public function test_an_internal_failure_is_a_system_error_and_nothing_is_recorded(): void
    {
        Payment::creating(fn () => throw new \RuntimeException('database is down'));

        $this->create('trx-boom', $this->order, 1760000000000)->assertOk()->assertJsonPath('error.code', -32400);
        $this->assertSame(0, Payment::count());
        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
    }

    public function test_a_fraction_of_a_tiyin_is_an_incorrect_amount(): void
    {
        // 5 000 000.4 tiyin would round to the order's 50 000.00 so'm; Payme amounts are whole tiyin
        $this->rpc('CheckPerformTransaction', ['amount' => 5000000.4, 'account' => ['order_id' => $this->order->uuid]])
            ->assertJsonPath('error.code', -31001);
        $this->rpc('CreateTransaction', ['id' => 'trx-frac', 'time' => 1760000000000, 'amount' => '5000000.4', 'account' => ['order_id' => $this->order->uuid]])
            ->assertJsonPath('error.code', -31001);
        $this->assertSame(0, Payment::count());

        $this->rpc('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $this->order->uuid]])
            ->assertJsonPath('result.allow', true);
    }

    public function test_a_restaurants_own_cashbox_gets_its_own_statement(): void
    {
        $own = Store::forceCreate([
            'name' => 'Own Cashbox Cafe',
            'slug' => 'own-cashbox-cafe',
            'status' => 'active',
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => self::STORE_KEY]],
        ]);
        $ownOrder = $this->makeOrder(['payment_owner' => 'STORE'], $own);

        $this->create('trx-platform', $this->order, 1760000000000)->assertJsonPath('result.state', 1);
        $this->create('trx-own', $ownOrder, 1760000001000, self::STORE_KEY)->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => 'trx-own'], self::STORE_KEY)->assertJsonPath('result.state', 2);

        $period = ['from' => 1760000000000, 'to' => 1760000010000];
        $mine = $this->rpc('GetStatement', $period, self::STORE_KEY)->assertOk();
        $this->assertSame(['trx-own'], array_column($mine->json('result.transactions'), 'id'));
        $mine->assertJsonPath('result.transactions.0.state', 2)->assertJsonPath('result.transactions.0.amount', 5000000);

        // The platform cashbox still sees only its own
        $this->assertSame(['trx-platform'], array_column($this->rpc('GetStatement', $period)->json('result.transactions'), 'id'));
        // An unknown key is still refused
        $this->rpc('GetStatement', $period, 'nobodys_key')->assertJsonPath('error.code', -32504);
    }
}
