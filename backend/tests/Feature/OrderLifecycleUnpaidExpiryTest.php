<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Jobs\CancelUnpaidOnlineOrdersJob;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class OrderLifecycleUnpaidExpiryTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        config(['orders.unpaid_online_ttl_minutes' => 30]);
    }

    protected function aged(Order $order, int $minutes): Order
    {
        $order->forceFill(['created_at' => now()->subMinutes($minutes)])->saveQuietly();

        return $order;
    }

    protected function expire(): void
    {
        dispatch_sync(new CancelUnpaidOnlineOrdersJob);
    }

    public function test_unpaid_online_order_is_cancelled_after_the_ttl_giving_back_stock_and_promo(): void
    {
        config(['services.telegram.bot_token' => '123456:fake-token']);
        Http::fake();
        PromoCode::create(['code' => 'ONCE', 'type' => 'percentage', 'value' => 10, 'min_order_amount' => 0, 'is_active' => true, 'usage_limit' => 1]);
        $customer = $this->makeCustomer(0, ['telegram_id' => '9100']);
        $order = $this->aged($this->checkout($customer, 3, 'click', 'pickup', 'ONCE'), 31);

        $this->assertSame(7, (int) $this->variant->fresh()->stock);
        try {
            $this->checkout($this->makeCustomer(), 1, 'cash', 'pickup', 'ONCE');
            $this->fail('The unpaid order should still hold the only promo use.');
        } catch (ValidationException) {
        }

        $this->expire();
        $this->expire(); // next minute: nothing left to do

        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
        $this->checkout($this->makeCustomer(), 1, 'cash', 'pickup', 'ONCE');

        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->sole();
        $this->assertNull($history->user_id);
        $this->assertStringContainsString("30 daqiqa ichida amalga oshirilmadi", $history->notes);
        Http::assertSent(fn ($request) => (string) ($request['chat_id'] ?? '') === '9100'
            && str_contains($request['text'] ?? '', 'bekor qilindi'));
        $this->assertSame(1, NotificationLog::where('user_id', $customer->id)->where('order_id', $order->id)->where('type', 'canceled')->count());
    }

    public function test_paid_recent_offline_and_accepted_orders_are_left_alone(): void
    {
        $recent = $this->aged($this->checkout($this->customer, 1, 'click'), 10);
        $paidStillNew = $this->aged($this->checkout($this->makeCustomer(), 1, 'click'), 45);
        $paidStillNew->update(['payment_status' => PaymentStatus::PAID->value]);
        $cash = $this->aged($this->checkout($this->makeCustomer(), 1, 'cash'), 120);
        $keshbek = $this->aged($this->checkout($this->makeCustomer(100000), 1, 'keshbek'), 120);
        $accepted = $this->aged($this->checkout($this->makeCustomer(), 1, 'click'), 120);
        $this->setStatus($accepted, $this->manager, 'accepted')->assertOk();

        $this->expire();

        foreach ([$recent, $paidStillNew, $cash, $keshbek] as $order) {
            $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        }
        $this->assertSame(OrderStatus::ACCEPTED, $accepted->fresh()->status);
        $this->assertSame(5, (int) $this->variant->fresh()->stock);
    }

    public function test_ttl_is_configurable_and_zero_disables_it(): void
    {
        $order = $this->aged($this->checkout($this->customer, 1, 'click'), 45);

        config(['orders.unpaid_online_ttl_minutes' => 0]);
        $this->expire();
        config(['orders.unpaid_online_ttl_minutes' => 60]);
        $this->expire();
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        config(['orders.unpaid_online_ttl_minutes' => 40]);
        $this->expire();
        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
    }

    public function test_a_payment_landing_first_wins_and_a_late_one_cannot_revive_the_order(): void
    {
        // Picked up as a candidate, then paid before the job takes the order lock
        $paidMeanwhile = $this->aged($this->checkout($this->customer, 1, 'click'), 31);
        $staleCandidate = Order::find($paidMeanwhile->id);
        Order::whereKey($paidMeanwhile->id)->update(['payment_status' => PaymentStatus::PAID->value]);

        (new ReflectionMethod(CancelUnpaidOnlineOrdersJob::class, 'expire'))
            ->invoke(new CancelUnpaidOnlineOrdersJob, $staleCandidate, app(OrderStatusService::class), 30);

        $this->assertSame(OrderStatus::NEW, $paidMeanwhile->fresh()->status);

        // Expired first, then the provider's callback arrives
        $expired = $this->aged($this->checkout($this->makeCustomer(), 1, 'click'), 31);
        $this->expire();
        $payment = Payment::where('order_id', $expired->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));

        $this->assertSame(OrderStatus::CANCELED, $expired->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('order_id', $expired->id)->where('status', 'accepted')->count());
    }

    public function test_scheduler_runs_it_every_minute_without_a_queue_worker(): void
    {
        config(['queue.default' => 'database']); // a queued job would only be stored, not run
        $order = $this->aged($this->checkout($this->customer, 1, 'click'), 31);

        $this->artisan('schedule:list')->expectsOutputToContain('CancelUnpaidOnlineOrdersJob')->assertSuccessful();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
    }
}
