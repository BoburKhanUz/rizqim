<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreTransactionType;
use App\Jobs\RetryFailedSettlementsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A delivery whose ledger booking fails (here: a column renamed under it, as in a deploy window) still delivers, is
 * marked and alerted, and RetryFailedSettlementsJob books it exactly once later.
 */
class LedgerSettlementRecoveryTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const SUPERADMIN_CHAT = '-100999';

    private User $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:recovery-token',
            'services.telegram.superadmin_chat_id' => self::SUPERADMIN_CHAT,
            'orders.settlement_retry_max_attempts' => 3,
        ]);
        Queue::fake([SendTelegramRequest::class]);

        $this->setUpLifecycleStore();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
    }

    private function breakLedger(): void
    {
        DB::statement('ALTER TABLE store_transactions RENAME COLUMN balance_after TO balance_after_renamed');
    }

    private function repairLedger(): void
    {
        DB::statement('ALTER TABLE store_transactions RENAME COLUMN balance_after_renamed TO balance_after');
    }

    private function deliver(bool $platformCollected = false): Order
    {
        // Owner decision 3: paid in a way the platform verified, so it earns the referral bonus: Paylov's read-back of
        // the store's own account (the ledger is the commission alone), or the platform's own Click account
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id]);
        if ($platformCollected) {
            $order->forceFill(['payment_method' => 'click', 'payment_owner' => 'PLATFORM', 'payment_status' => 'paid'])->save();
            \App\Models\Payment::create([
                'order_id' => $order->id, 'store_id' => $order->store_id, 'merchant_scope' => \App\Models\Payment::merchantScopeOf($order),
                'provider' => 'click', 'provider_transaction_id' => 'click-' . \Illuminate\Support\Str::random(12),
                'amount' => $order->total_price, 'status' => 'paid', 'paid_at' => now(),
            ]);
            $order->refresh();
        } else {
            $order = $this->payThroughPaylov($order);
        }

        $this->assertTrue(app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, $this->courier)->changed);

        return $order->fresh();
    }

    private function retry(): void
    {
        app()->call([new RetryFailedSettlementsJob, 'handle']);
    }

    /**
     * @return Collection<int, string> texts queued for the superadmin chat
     */
    private function alerts(): Collection
    {
        return Queue::pushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => ($job->params['chat_id'] ?? null) === self::SUPERADMIN_CHAT)
            ->map(fn (SendTelegramRequest $job) => $job->params['text'])->values();
    }

    /**
     * @return array{commission: int, referral: int, debt: string, referrer: float}
     */
    private function booked(Order $order): array
    {
        $rows = StoreTransaction::where('order_id', $order->id)->get();

        return [
            'commission' => $rows->filter(fn ($row) => $row->type === StoreTransactionType::COMMISSION)->count(),
            // Owner decision 3's addition: the platform pays the bonus (a referral_bonus row), the store's debt never moves
            'referral' => $rows->filter(fn ($row) => $row->type === StoreTransactionType::REFERRAL_BONUS)->count(),
            'debt' => (string) $this->store->fresh()->debt_balance,
            'referrer' => (float) $this->referrer->fresh()->balance,
        ];
    }

    public function test_a_failed_booking_still_delivers_and_leaves_a_marker_a_critical_log_and_an_alert(): void
    {
        Log::spy();
        $this->breakLedger();

        $order = $this->deliver();

        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(1, $order->settlement_attempts);
        $this->assertStringContainsString('balance_after', (string) $order->settlement_error);
        $this->assertSame('delivered', DB::table('order_status_histories')->where('order_id', $order->id)->latest('id')->value('status'));

        // Nothing at all was booked: neither the commission nor the referral reward
        $this->repairLedger();
        $this->assertSame(['commission' => 0, 'referral' => 0, 'debt' => '0.00', 'referrer' => 0.0], $this->booked($order));

        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, $order->uuid))->once();
        $this->assertCount(1, $this->alerts());
        $this->assertStringContainsString("#{$order->short_code}", $this->alerts()[0]);
        $this->assertNull(Queue::pushed(SendTelegramRequest::class)->first(fn ($job) => ($job->params['chat_id'] ?? null) === self::SUPERADMIN_CHAT)->params['parse_mode'] ?? null);
    }

    public function test_the_retry_books_it_exactly_once_and_a_second_retry_changes_nothing(): void
    {
        $this->breakLedger();
        $order = $this->deliver();
        $this->repairLedger();

        $this->retry();

        // 5 000 commission only: the 5 000 bonus is the platform's, not on the store's debt
        $booked = ['commission' => 1, 'referral' => 1, 'debt' => '5000.00', 'referrer' => 5000.0];
        $this->assertSame($booked, $this->booked($order));
        $order->refresh();
        $this->assertNull($order->settlement_failed_at);
        $this->assertNull($order->settlement_error);
        $this->assertSame(0, $order->settlement_attempts);
        $this->assertStringContainsString('qayta urinishda yozildi', $this->alerts()->last());

        $this->retry();
        app(\App\Services\Finance\LedgerService::class)->processOrderDelivered($order); // even a direct second booking

        $this->assertSame($booked, $this->booked($order));
        $this->assertCount(2, $this->alerts());
    }

    public function test_a_retry_after_the_customers_next_delivery_grants_nothing_more(): void
    {
        $this->breakLedger();
        $first = $this->deliver();
        $this->repairLedger();

        // The bonus is for a qualifying order, not "the first delivery": the referral was still open, so the next
        // qualifying delivery earns it; the first one's booking, retried later, books its commission and nothing more
        $second = $this->deliver();
        $this->assertSame(['commission' => 1, 'referral' => 1, 'debt' => '5000.00', 'referrer' => 5000.0], $this->booked($second));

        $this->retry();

        $this->assertSame(['commission' => 1, 'referral' => 0, 'debt' => '10000.00', 'referrer' => 5000.0], $this->booked($first));
        $this->assertSame(1, $this->booked($second)['referral']);
    }

    public function test_it_gives_up_after_the_configured_attempts_with_a_louder_alert(): void
    {
        $this->breakLedger();
        $order = $this->deliver();

        $this->retry();
        $this->assertSame(2, $order->fresh()->settlement_attempts);
        $this->assertCount(1, $this->alerts(), 'an intermediate failure is only logged');

        $this->retry();
        $this->assertSame(3, $order->fresh()->settlement_attempts);
        $this->assertCount(2, $this->alerts());
        $this->assertStringContainsString("orders:retry-settlements {$order->id}", $this->alerts()->last());

        // Left alone from now on, still marked
        $this->retry();
        $this->assertSame(3, $order->fresh()->settlement_attempts);
        $this->assertNotNull($order->fresh()->settlement_failed_at);
        $this->assertCount(2, $this->alerts());

        // Once the cause is fixed, a superadmin books it by hand
        $this->repairLedger();
        $this->artisan('orders:retry-settlements', ['orders' => [$order->uuid]])->expectsOutputToContain('booked')->assertSuccessful();
        $this->assertSame(1, $this->booked($order)['commission']);
        $this->assertNull($order->fresh()->settlement_failed_at);
    }

    public function test_an_order_refunded_before_the_retry_is_not_booked_any_more(): void
    {
        // Platform-collected and verified: had its booking succeeded it would have earned the bonus (a single admin may
        // still refund such an order)
        $this->breakLedger();
        $order = $this->deliver(platformCollected: true);
        $this->repairLedger();
        $this->assertTrue(\App\Services\Auth\ReferralService::qualifiesForReferralBonus($order));
        $admin = $this->makeStaff(\App\Enums\UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        app(OrderStatusService::class)->refund($order, $admin, 'Mijoz shikoyati');

        $this->retry();

        // Neither the commission, nor the settlement, nor the referral bonus: the refunded order earns nothing
        $this->assertSame(['commission' => 0, 'referral' => 0, 'debt' => '0.00', 'referrer' => 0.0], $this->booked($order));
        $this->assertSame('0.00', (string) $this->store->fresh()->wallet_balance);
        $this->assertNull($order->fresh()->settlement_failed_at);
        // ...nor later, when the referred customer's next order is delivered: that one earns it
        $this->deliver();
        $this->assertSame(5000.0, (float) $this->referrer->fresh()->balance);
        $this->assertSame(0, $this->booked($order)['referral']);
    }
}
