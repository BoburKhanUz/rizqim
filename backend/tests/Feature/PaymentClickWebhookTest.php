<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentClickWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_SECRET = 'platform_click_secret';

    protected Store $store;
    protected User $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => self::PLATFORM_SECRET]);

        $this->store = Store::forceCreate([
            'name' => 'Click Cafe',
            'slug' => 'click-cafe',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
            'debt_balance' => 150000,
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998901110000',
            'role' => UserRole::CUSTOMER->value,
            'password' => bcrypt('password'),
        ]);

        $this->order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => 'click',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
    }

    private function click(int $action, string $merchantTransId, string $amount, string $clickTransId = '9001', ?string $secret = self::PLATFORM_SECRET, $prepareId = null, array $overrides = [])
    {
        $prepareId ??= $this->order->id;
        $signTime = '2026-10-18 12:00:00';

        $data = [
            'click_trans_id' => $clickTransId,
            'service_id' => '32104',
            'click_paydoc_id' => '555',
            'merchant_trans_id' => $merchantTransId,
            'amount' => $amount,
            'action' => $action,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5($clickTransId . '32104' . $secret . $merchantTransId . ($action === 1 ? $prepareId : '') . $amount . $action . $signTime),
        ];

        if ($action === 1) {
            $data['merchant_prepare_id'] = $prepareId;
        }

        return $this->postJson('/api/v1/payments/click/webhook', array_merge($data, $overrides));
    }

    public function test_missing_or_incorrect_signature_is_rejected(): void
    {
        $this->click(1, $this->order->uuid, '50000', overrides: ['sign_string' => ''])
            ->assertJson(['error' => -1]);

        $this->click(1, $this->order->uuid, '50000', secret: 'wrong_secret')
            ->assertJson(['error' => -1]);

        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
        $this->assertSame(0, Payment::where('provider', 'click')->count());
    }

    public function test_unconfigured_secret_fails_closed_even_with_the_old_public_default(): void
    {
        config(['services.click.secret_key' => null]);
        Log::spy();

        $this->click(0, $this->order->uuid, '50000', secret: 'click_sandbox_secret')->assertJson(['error' => -1]);
        $this->click(1, $this->order->uuid, '50000', secret: 'click_sandbox_secret')->assertJson(['error' => -1]);
        $this->click(1, $this->order->uuid, '50000', secret: '')->assertJson(['error' => -1]);

        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'CLICK_SECRET_KEY'));
    }

    public function test_platform_secret_can_be_managed_in_superadmin_payment_providers(): void
    {
        // Payment URLs already prefer these credentials, so the webhook must verify with the same secret.
        config(['services.click.secret_key' => null]);
        PaymentProvider::create([
            'name' => 'Click',
            'slug' => 'click',
            'is_active' => true,
            'credentials' => ['service_id' => '32104', 'secret_key' => 'db-click-secret'],
        ]);

        $this->click(1, $this->order->uuid, '50000', secret: self::PLATFORM_SECRET)->assertJson(['error' => -1]);
        $this->click(1, $this->order->uuid, '50000', secret: 'db-click-secret')->assertJson(['error' => 0]);
    }

    public function test_store_key_cannot_confirm_a_platform_collected_order(): void
    {
        // An owner can type any key into their store settings; it must not authenticate money the platform collects.
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['click' => ['enabled' => true, 'service_id' => '1', 'merchant_id' => '2', 'secret_key' => 'owner_chosen_key']],
        ]);

        $this->click(1, $this->order->uuid, '50000', secret: 'owner_chosen_key')->assertJson(['error' => -1]);
        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);

        // A store collecting into its own merchant account is verified with its own key.
        $this->order->update(['payment_owner' => 'STORE']);
        $this->click(1, $this->order->uuid, '50000', secret: 'owner_chosen_key')->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $this->order->fresh()->payment_status);
    }

    public function test_wrong_amount_is_rejected_on_prepare_and_complete(): void
    {
        $this->click(0, $this->order->uuid, '1000')->assertJson(['error' => -2]);
        $this->click(1, $this->order->uuid, '1000')->assertJson(['error' => -2]);
        $this->click(1, $this->order->uuid, '49999.99')->assertJson(['error' => -2]);

        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
        $this->assertSame(0, Payment::where('provider', 'click')->count());
    }

    public function test_legitimate_payment_completes_and_duplicate_complete_fires_payment_paid_once(): void
    {
        Event::fake([PaymentPaid::class]);

        $this->click(0, $this->order->uuid, '50000.00')
            ->assertJson(['error' => 0, 'merchant_prepare_id' => $this->order->id]);

        $first = $this->click(1, $this->order->uuid, '50000.00')->assertJson(['error' => 0, 'error_note' => 'Success']);
        $second = $this->click(1, $this->order->uuid, '50000.00')->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);

        $this->assertSame($first->json('merchant_confirm_id'), $second->json('merchant_confirm_id'));
        Event::assertDispatchedTimes(PaymentPaid::class, 1);
        $this->assertSame(1, Payment::where('provider', 'click')->where('status', PaymentStatus::PAID->value)->count());
        $this->assertSame(PaymentStatus::PAID, $this->order->fresh()->payment_status);
    }

    public function test_second_click_transaction_for_a_paid_order_is_refused(): void
    {
        $this->click(0, $this->order->uuid, '50000', clickTransId: '9001')->assertJson(['error' => 0]);
        $this->click(0, $this->order->uuid, '50000', clickTransId: '9002')->assertJson(['error' => 0]);

        $this->click(1, $this->order->uuid, '50000', clickTransId: '9001')->assertJson(['error' => 0]);
        $this->click(1, $this->order->uuid, '50000', clickTransId: '9002')->assertJson(['error' => -4]);

        $this->assertSame(1, Payment::where('provider', 'click')->where('status', PaymentStatus::PAID->value)->count());
        $this->assertSame(1, OrderStatusHistory::where('order_id', $this->order->id)->count());
        $this->assertSame(OrderStatus::ACCEPTED, $this->order->fresh()->status);
    }

    public function test_payment_paid_listener_is_idempotent_and_never_regresses_the_order(): void
    {
        $this->click(1, $this->order->uuid, '50000')->assertJson(['error' => 0]);
        $payment = Payment::where('provider', 'click')->firstOrFail();

        $this->order->update(['status' => OrderStatus::PREPARING->value]);
        event(new PaymentPaid($payment));

        $this->assertSame(OrderStatus::PREPARING, $this->order->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $this->order->id)->count());
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        $this->order->update(['status' => OrderStatus::CANCELED->value]);

        $this->click(0, $this->order->uuid, '50000')->assertJson(['error' => -9]);
        $this->click(1, $this->order->uuid, '50000')->assertJson(['error' => -9]);

        $this->assertSame(PaymentStatus::PENDING, $this->order->fresh()->payment_status);
        $this->assertSame(OrderStatus::CANCELED, $this->order->fresh()->status);
    }

    public function test_topup_duplicate_complete_credits_the_store_once(): void
    {
        $topup = StoreTopupRequest::create([
            'store_id' => $this->store->id,
            'amount' => 100000,
            'provider' => 'click',
            'status' => 'pending',
        ]);
        $ref = 'topup_' . $topup->uuid;

        $this->click(1, $ref, '1000', prepareId: $topup->id)->assertJson(['error' => -2]);
        $this->click(1, $ref, '100000', prepareId: $topup->id)->assertJson(['error' => 0, 'error_note' => 'Success']);
        $this->click(1, $ref, '100000', prepareId: $topup->id)->assertJson(['error' => 0, 'error_note' => 'Already confirmed']);
        $this->click(1, $ref, '100000', clickTransId: '9002', prepareId: $topup->id)->assertJson(['error' => -4]);

        $this->assertEquals(50000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(1, StoreTransaction::where('store_id', $this->store->id)->where('type', StoreTransactionType::TOPUP->value)->count());
        $this->assertSame('paid', $topup->fresh()->status);
    }
}
