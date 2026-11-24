<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security review F3-F6: a provider's transaction id belongs to the merchant account it was made in
 * (payments.merchant_scope: the platform's, or a store's own), and a callback signed with one account's key never reads,
 * squats or learns about another account's transactions.
 */
class PaymentMerchantScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private string $platformClick;
    private string $platformPayme;

    /** @var array<string, array{store: Store, click: string, payme: string}> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Built at runtime: no merchant key is written in the code base
        $this->platformClick = 'platform-' . Str::random(24);
        $this->platformPayme = 'platform-' . Str::random(24);
        config(['services.click.secret_key' => $this->platformClick, 'services.payme.secret_key' => $this->platformPayme]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998901213001', 'password' => bcrypt('password')]);

        foreach (['x', 'y'] as $name) {
            $this->stores[$name] = $this->ownStore($name);
        }
    }

    /**
     * @return array{store: Store, click: string, payme: string}
     */
    private function ownStore(string $name): array
    {
        $click = "{$name}-click-" . Str::random(24);
        $payme = "{$name}-payme-" . Str::random(24);

        return [
            'store' => Store::forceCreate([
                'name' => strtoupper($name) . ' Cafe',
                'status' => 'active',
                'payment_account_type' => 'own_account',
                'credit_limit' => -1000000,
                'fiscal_settings' => ['enabled' => true],
                'payment_settings' => [
                    'click' => ['enabled' => true, 'service_id' => "svc-{$name}", 'merchant_id' => "m-{$name}", 'secret_key' => $click],
                    'payme' => ['enabled' => true, 'merchant_id' => "pm-{$name}", 'secret_key' => $payme],
                ],
            ]),
            'click' => $click,
            'payme' => $payme,
        ];
    }

    private function order(string $store, string $method, string $owner = 'STORE'): Order
    {
        return Order::create([
            'store_id' => $this->stores[$store]['store']->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => $method,
            'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
    }

    private function click(Order $order, string $key, int $action, string $trans, string $service = 'svc-x')
    {
        $prepareId = $action === 1 ? (string) $order->id : '';
        $amount = '50000';
        $data = [
            'click_trans_id' => $trans, 'service_id' => $service, 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'amount' => $amount, 'action' => $action, 'error' => 0,
            'sign_time' => '2026-11-24 12:00:00',
            'sign_string' => md5($trans . $service . $key . $order->uuid . $prepareId . $amount . $action . '2026-11-24 12:00:00'),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $order->id;
        }

        return $this->postJson('/api/v1/payments/click/webhook', $data);
    }

    private function payme(string $method, array $params, string $key)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 3, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $key),
        ]);
    }

    private function create(string $id, Order|string $account, string $key)
    {
        return $this->payme('CreateTransaction', ['id' => $id, 'time' => 1764000000000, 'amount' => 5000000,
            'account' => ['order_id' => $account instanceof Order ? $account->uuid : $account]], $key);
    }

    // --- F5: Click transaction-id squatting ----------------------------------------------------------------------

    /**
     * The review's probe: X forges Prepare/Complete on its own order with a guessed future click_trans_id N. Click's real
     * Complete with N for another account's order (the platform's, or store Y's own) must still pay that order.
     */
    public function test_a_squatted_click_transaction_id_never_breaks_another_accounts_payment(): void
    {
        $mine = $this->order('x', 'click');
        $this->click($mine, $this->stores['x']['click'], 0, '900001')->assertJson(['error' => 0]);
        $this->click($mine, $this->stores['x']['click'], 1, '900001')->assertJson(['error' => 0]);
        $this->click($mine, $this->stores['x']['click'], 1, '900002')->assertJson(['error' => -4]);

        $platform = $this->order('y', 'click', 'PLATFORM');
        $this->click($platform, $this->platformClick, 0, '900001', 'platform-svc')->assertJson(['error' => 0]);
        $this->click($platform, $this->platformClick, 1, '900001', 'platform-svc')->assertJson(['error' => 0, 'error_note' => 'Success']);
        $this->assertSame(PaymentStatus::PAID, $platform->fresh()->payment_status);

        $theirs = $this->order('y', 'click');
        $this->click($theirs, $this->stores['y']['click'], 1, '900001', 'svc-y')->assertJson(['error' => 0, 'error_note' => 'Success']);
        $this->assertSame(PaymentStatus::PAID, $theirs->fresh()->payment_status);

        // Each is one payment of its own account, and every replay stays idempotent
        $this->assertSame(['platform', 'store:' . $this->stores['x']['store']->id, 'store:' . $this->stores['y']['store']->id],
            Payment::where('provider', 'click')->where('provider_transaction_id', '900001')->orderBy('merchant_scope')->pluck('merchant_scope')->all());
        $this->click($platform, $this->platformClick, 1, '900001', 'platform-svc')->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);
        $this->click($theirs, $this->stores['y']['click'], 1, '900001', 'svc-y')->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);
        $this->click($mine, $this->stores['x']['click'], 1, '900001')->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);
        $this->assertSame(3, Payment::where('provider', 'click')->count());

        // Within one account an id stays one order's: another order of X with N is still refused
        $second = $this->order('x', 'click');
        $this->click($second, $this->stores['x']['click'], 1, '900001')->assertJson(['error' => -9]);
        $this->assertSame(PaymentStatus::PENDING, $second->fresh()->payment_status);
    }

    // --- F4: CreateTransaction with another cashbox's id ---------------------------------------------------------

    public function test_a_foreign_cashboxs_transaction_id_is_answered_like_an_unknown_one(): void
    {
        // Y's cashbox and the platform's each hold a real transaction
        $theirs = $this->order('y', 'payme');
        $this->create('real-y', $theirs, $this->stores['y']['payme'])->assertJsonPath('result.state', 1);
        $platform = $this->order('y', 'payme', 'PLATFORM');
        $this->create('real-p', $platform, $this->platformPayme)->assertJsonPath('result.state', 1);

        // X names those ids on its own orders: exactly what an id nobody holds gets
        $answers = [];
        foreach (['never-seen', 'real-y', 'real-p'] as $id) {
            $answers[$id] = $this->create($id, $this->order('x', 'payme'), $this->stores['x']['payme'])->json();
            unset($answers[$id]['result']['transaction']);
        }
        $this->assertSame($answers['never-seen'], $answers['real-y']);
        $this->assertSame($answers['never-seen'], $answers['real-p']);
        $this->assertSame(1, $answers['real-y']['result']['state'] ?? null);

        // The other cashboxes' transactions are untouched and still theirs
        $this->payme('PerformTransaction', ['id' => 'real-y'], $this->stores['y']['payme'])->assertJsonPath('result.state', 2);
        $this->payme('PerformTransaction', ['id' => 'real-p'], $this->platformPayme)->assertJsonPath('result.state', 2);
        $this->assertSame([PaymentStatus::PAID, PaymentStatus::PAID], [$theirs->fresh()->payment_status, $platform->fresh()->payment_status]);
        $this->assertSame(1, Payment::where('order_id', $theirs->id)->count());

        // Within one cashbox Payme's semantics stay: an id of another of its orders is "belongs to another order"
        $this->create('real-y', $this->order('y', 'payme'), $this->stores['y']['payme'])->assertJsonPath('error.code', -31050);
    }

    // --- F3: CheckPerformTransaction authorised by its account -------------------------------------------------

    public function test_check_perform_is_signed_by_the_key_of_the_account_it_names_whatever_transaction_id_it_carries(): void
    {
        $mine = $this->order('x', 'payme');
        $this->create('own-x', $mine, $this->stores['x']['payme'])->assertJsonPath('result.state', 1);
        $theirs = $this->order('y', 'payme');
        $topup = StoreTopupRequest::create(['store_id' => $this->stores['y']['store']->id, 'amount' => 50000, 'provider' => 'payme', 'status' => 'pending']);

        foreach ([$theirs->uuid, 'topup_' . $topup->uuid, $this->order('x', 'payme', 'PLATFORM')->uuid] as $account) {
            $answer = $this->payme('CheckPerformTransaction', ['id' => 'own-x', 'amount' => 5000000, 'account' => ['order_id' => $account]], $this->stores['x']['payme']);
            $answer->assertJsonPath('error.code', -32504);
            $this->assertArrayNotHasKey('result', $answer->json());
        }

        // Its own order is checked as before
        $this->payme('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $this->order('x', 'payme')->uuid]], $this->stores['x']['payme'])
            ->assertJsonPath('result.allow', true);
    }

    // --- F6: GetStatement and the transaction methods find the cashbox through an index --------------------------

    public function test_a_store_key_is_matched_without_reading_every_stores_settings(): void
    {
        foreach (range(1, 6) as $n) {
            $this->ownStore("s{$n}");
        }
        $chain = $this->stores['x']['store'];
        $sibling = Store::forceCreate(['name' => 'X2', 'status' => 'active', 'payment_account_type' => 'own_account', 'credit_limit' => -1000000,
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'pm-x', 'secret_key' => $this->stores['x']['payme']]]]);
        $this->create('stmt-1', $this->order('x', 'payme'), $this->stores['x']['payme'])->assertJsonPath('result.state', 1);

        $read = [];
        Store::retrieved(function (Store $store) use (&$read) {
            $read[] = (int) $store->id;
        });

        // A key no store has reads no store at all
        $this->payme('GetStatement', ['from' => 1, 'to' => 1764000000001], 'nobody-' . Str::random(24))->assertJsonPath('error.code', -32504);
        $this->payme('CheckTransaction', ['id' => 'stmt-1'], 'nobody-' . Str::random(24))->assertJsonPath('error.code', -32504);
        $this->assertSame([], $read);

        // A store's key reads the stores of its cashbox only
        $rows = $this->payme('GetStatement', ['from' => 1, 'to' => 1764000000001], $this->stores['x']['payme'])->json('result.transactions');
        $this->assertSame(['stmt-1'], array_column($rows, 'id'));
        $this->assertSame([(int) $chain->id, (int) $sibling->id], array_values(array_unique($read)));
    }

    public function test_the_digest_follows_every_change_of_the_key(): void
    {
        $store = $this->stores['x']['store'];
        $old = $this->stores['x']['payme'];
        $order = $this->order('x', 'payme');
        $this->create('rot-1', $order, $old)->assertJsonPath('result.state', 1);

        $new = 'x-rotated-' . Str::random(24);
        $store->fresh()->forceFill(['payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'pm-x', 'secret_key' => $new]]])->save();

        // Payme signs with the cashbox's current key, also for a transaction created before the change
        $this->payme('PerformTransaction', ['id' => 'rot-1'], $old)->assertJsonPath('error.code', -32504);
        $this->payme('PerformTransaction', ['id' => 'rot-1'], $new)->assertJsonPath('result.state', 2);

        // A store without a Payme key of its own is indexed as such
        $plain = Store::forceCreate(['name' => 'Plain', 'status' => 'active', 'payment_settings' => ['cash' => ['enabled' => true]]]);
        $this->assertSame(\App\Services\Payment\PaymeCashbox::NONE, Store::whereKey($plain->id)->value('payme_key_digest'));
        $this->assertNotSame(\App\Services\Payment\PaymeCashbox::NONE, Store::whereKey($store->id)->value('payme_key_digest'));
    }
}
