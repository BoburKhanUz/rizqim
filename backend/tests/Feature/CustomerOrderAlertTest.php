<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TelegramAction;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The customer hears about a transition once, from the order event listeners, whichever way it was made.
 */
class CustomerOrderAlertTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:alert-token',
            'services.telegram.webhook_secret' => 'alert-secret',
        ]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $this->setUpLifecycleStore();
        $this->manager->update(['telegram_id' => '5001']);
        $this->courier->update(['telegram_id' => '5002']);
        $this->customer->update(['telegram_id' => '5009']);
    }

    /**
     * @return list<string> texts sent to the customer's chat so far
     */
    protected function customerMessages(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (string) ($request['chat_id'] ?? '') === '5009')
            ->map(fn ($pair) => $pair[0]['text'])
            ->values()
            ->all();
    }

    protected function pressButton(Order $order, string $action, string $telegramUserId): void
    {
        $token = Str::random(32);
        TelegramAction::create(['order_id' => $order->id, 'action' => $action, 'token' => $token, 'expires_at' => now()->addHour()]);

        $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1000000), 'callback_query' => [
            'id' => 'cb-' . $action,
            'from' => ['id' => (int) $telegramUserId, 'first_name' => 'Staff'],
            'message' => ['message_id' => 7, 'chat' => ['id' => (int) $telegramUserId]],
            'data' => $token,
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'alert-secret'])->assertJsonPath('status', 'success');
    }

    public function test_each_transition_reaches_the_customer_exactly_once_whichever_way_it_was_made(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $steps = [
            'accepted in the app' => [
                fn () => $this->setStatus($order, $this->manager, 'accepted')->assertOk(),
                'Buyurtmangiz qabul qilindi!',
            ],
            'preparing from a Telegram button' => [
                fn () => $this->pressButton($order, 'prepare', '5001'),
                'Buyurtmangiz tayyorlanmoqda!',
            ],
            'handed to a courier in the app' => [
                fn () => $this->actingAs($this->manager, 'sanctum')
                    ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk(),
                "Buyurtmangiz holati o'zgardi",
            ],
            // The courier line used to come only with the second, Mini App message
            'on the way, set by the courier' => [
                fn () => $this->setStatus($order, $this->courier, 'on_the_way')->assertOk(),
                "👤 *Kuryer:* {$this->courier->name} ({$this->courier->phone})",
            ],
            'delivered, from the courier app' => [
                fn () => $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertOk(),
                'Buyurtmangiz muvaffaqiyatli yetkazildi!',
            ],
            'refunded by the platform' => [
                fn () => app(OrderStatusService::class)->refund($order, null, 'Sifatsiz mahsulot'),
                // Cash: the store still has to give it back
                "Buyurtmangiz bo'yicha pul qaytariladi*\n🆔 ID: #{$order->short_code}\n💰 Summa: *50 000 UZS*",
            ],
        ];

        foreach ($steps as $label => [$step, $expected]) {
            $before = count($this->customerMessages());

            $step();

            $messages = $this->customerMessages();
            $this->assertCount($before + 1, $messages, $label);
            $this->assertStringContainsString($expected, end($messages), $label);
        }

        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        // One feed entry per status the in-app feed has always shown
        $this->assertSame(
            ['telegram_customer_accepted', 'telegram_customer_on_the_way', 'telegram_customer_delivered'],
            NotificationLog::where('user_id', $this->customer->id)->where('order_id', $order->id)->orderBy('id')->pluck('type')->all()
        );
    }

    public function test_a_rejected_order_and_a_payment_accepted_one_reach_the_customer_once(): void
    {
        $rejected = $this->makeOrder(OrderStatus::NEW);
        $paid = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click']);

        $this->setStatus($rejected, $this->manager, 'rejected')->assertOk();
        event(new PaymentPaid(Payment::create([
            'order_id' => $paid->id,
            'provider' => 'click',
            'amount' => 50000,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ])));

        $messages = $this->customerMessages();
        $this->assertCount(2, $messages);
        // Not the generic "holati o'zgardi: REJECTED" the listener used to send next to the app's own message
        $this->assertStringStartsWith('❌ *Buyurtmangiz bekor qilindi.*', $messages[0]);
        $this->assertStringStartsWith('✅ *Buyurtmangiz qabul qilindi!*', $messages[1]);

        $feed = fn (Order $order) => NotificationLog::where('user_id', $this->customer->id)->where('order_id', $order->id)->pluck('type')->all();
        $this->assertSame(['telegram_customer_canceled'], $feed($rejected));
        $this->assertSame(['telegram_customer_accepted'], $feed($paid));
    }

    public function test_a_refund_says_returned_only_once_the_money_is_back_and_names_the_amount_and_where_keshbek_went(): void
    {
        $refunded = fn (array $attributes) => $this->makeOrder(OrderStatus::REFUNDED, $attributes);
        $orders = [
            'keshbek' => $refunded(['order_type' => 'pickup', 'payment_method' => 'keshbek', 'payment_status' => 'refunded', 'total_price' => 42500]),
            'payme refunded by payme' => $refunded(['payment_method' => 'payme', 'payment_status' => 'refunded']),
            'click awaiting a manual refund' => $refunded(['payment_method' => 'click', 'payment_status' => 'paid']),
            'cash' => $refunded(['payment_method' => 'cash', 'payment_status' => 'paid', 'total_price' => 0]),
        ];

        foreach ($orders as $order) {
            app(TelegramNotificationService::class)->sendOrderStatusChanged($order);
        }

        $code = fn (string $label) => $orders[$label]->short_code;
        $this->assertSame([
            "💸 *Buyurtmangiz bo'yicha pul qaytarildi*\n🆔 ID: #{$code('keshbek')}\n💰 Summa: *42 500 UZS*\n👛 Mablag' keshbek balansingizga qaytarildi.",
            "💸 *Buyurtmangiz bo'yicha pul qaytarildi*\n🆔 ID: #{$code('payme refunded by payme')}\n💰 Summa: *50 000 UZS*",
            "💸 *Buyurtmangiz bo'yicha pul qaytariladi*\n🆔 ID: #{$code('click awaiting a manual refund')}\n💰 Summa: *50 000 UZS*",
            "💸 *Buyurtmangiz bo'yicha pul qaytariladi*\n🆔 ID: #{$code('cash')}",
        ], array_values($this->customerMessages()));
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_the_admin_refund_of_a_paid_click_order_tells_the_customer_it_will_be_returned(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'click', 'payment_status' => 'paid']);
        Payment::create(['order_id' => $order->id, 'provider' => 'click', 'amount' => 50000, 'status' => PaymentStatus::PAID, 'paid_at' => now()]);

        app(OrderStatusService::class)->refund($order, null, 'Sifatsiz mahsulot');

        $this->assertTrue($order->payments()->sole()->payload['manual_refund_required']);
        $this->assertStringStartsWith("💸 *Buyurtmangiz bo'yicha pul qaytariladi*", collect($this->customerMessages())->last());
    }
}
