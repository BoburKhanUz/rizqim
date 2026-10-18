<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusUpdated;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\TelegramAction;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A courier hears that an order was handed to them exactly once, from the OrderStatusUpdated listener, whichever way
 * it was assigned or reassigned.
 */
class CourierAssignedAlertTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:courier-alert-token',
            'services.telegram.webhook_secret' => 'courier-alert-secret',
        ]);
        Queue::fake([SendTelegramRequest::class]);

        $this->setUpLifecycleStore();
        $this->manager->update(['telegram_id' => '5001']);
        $this->courier->update(['telegram_id' => '5002']);
        $this->owner->update(['telegram_id' => '5003']);
        $this->customer->update(['telegram_id' => '5009']);
    }

    /**
     * @return Collection<int, SendTelegramRequest> messages queued for the user's chat so far
     */
    protected function messagesTo(User $user): Collection
    {
        return Queue::pushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->method === 'sendMessage'
            && (string) ($job->params['chat_id'] ?? '') === (string) $user->telegram_id)->values();
    }

    /**
     * @return Collection<int, SendTelegramRequest> every courier assignment message queued so far
     */
    protected function assignmentAlerts(): Collection
    {
        return Queue::pushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => ($job->notification['type'] ?? null) === 'telegram_courier_assigned')->values();
    }

    protected function assignCourier(Order $order, User $courier)
    {
        return $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $courier->id]);
    }

    protected function pressButton(Order $order, string $action, string $telegramUserId)
    {
        $token = Str::random(32);
        TelegramAction::create(['order_id' => $order->id, 'action' => $action, 'token' => $token, 'expires_at' => now()->addHour()]);

        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1000000), 'callback_query' => [
            'id' => 'cb-' . $action,
            'from' => ['id' => (int) $telegramUserId, 'first_name' => 'Staff'],
            'message' => ['message_id' => 7, 'chat' => ['id' => (int) $telegramUserId]],
            'data' => $token,
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'courier-alert-secret']);
    }

    public function test_the_courier_gets_the_message_assign_courier_has_always_sent(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);

        $this->assignCourier($order, $this->courier)->assertOk();

        $message = $this->messagesTo($this->courier)->sole();
        $this->assertSame(
            "🚚 *Yangi buyurtma biriktirildi!*\n\n"
            . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
            . "📍 *Manzil:* Amir Temur 1\n"
            . "💰 *Jami summa:* 50 000 UZS\n\n"
            . "Ilovadan buyurtmani qabul qiling va yo'lga chiqing! 🚀",
            $message->params['text']
        );
        $this->assertSame('Markdown', $message->params['parse_mode']);
        $this->assertSame(
            ['inline_keyboard' => [[['text' => '🗺️ Yandex Navigatorda ochish', 'url' => 'https://yandex.com/maps/?pt=69.240562,41.311081&z=16&l=map']]]],
            json_decode($message->params['reply_markup'], true)
        );
        // Recorded in the courier's in-app feed once sent
        $this->assertSame(['user_id' => $this->courier->id, 'order_id' => $order->id, 'type' => 'telegram_courier_assigned'], $message->notification);
    }

    public function test_every_way_of_handing_an_order_to_a_courier_messages_them_once(): void
    {
        $paths = [
            'assign-courier endpoint' => [
                OrderStatus::READY,
                fn (Order $order) => $this->assignCourier($order, $this->courier)->assertOk(),
            ],
            'status update naming the courier' => [
                OrderStatus::READY,
                fn (Order $order) => $this->setStatus($order, $this->manager, 'courier_assigned', ['courier_id' => $this->courier->id])->assertOk(),
            ],
            'status update, least busy courier picked' => [
                OrderStatus::READY,
                fn (Order $order) => $this->setStatus($order, $this->owner, 'courier_assigned')->assertOk(),
            ],
            'Telegram "deliver" button, least busy courier picked' => [
                OrderStatus::PREPARING,
                fn (Order $order) => $this->pressButton($order, 'deliver', '5001')->assertJsonPath('status', 'success'),
            ],
        ];

        foreach ($paths as $label => [$status, $assign]) {
            $order = $this->makeOrder($status);
            $before = $this->messagesTo($this->courier)->count();

            $assign($order);

            $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status, $label);
            $this->assertSame($this->courier->id, $order->fresh()->courier_id, $label);
            $messages = $this->messagesTo($this->courier);
            $this->assertCount($before + 1, $messages, $label);
            $this->assertSame(
                ['user_id' => $this->courier->id, 'order_id' => $order->id, 'type' => 'telegram_courier_assigned'],
                $messages->last()->notification,
                $label
            );
        }

        $this->assertCount(count($paths), $this->assignmentAlerts());
    }

    public function test_the_admin_panels_send_to_delivery_messages_the_courier_once(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->owner);
        Filament::setTenant($this->store);
        $order = $this->makeOrder(OrderStatus::PREPARING);

        Livewire::test(ListOrders::class)
            ->callTableAction('deliver', $order)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertSame($this->courier->id, $order->fresh()->courier_id);
        // Sent straight on its way: the delivery card with its "delivered" button, no assignment message on top
        $this->assertStringStartsWith("🚚 *Buyurtma yo'lda!*", $this->messagesTo($this->courier)->sole()->params['text']);
        $this->assertCount(0, $this->assignmentAlerts());
    }

    public function test_a_reassigned_order_messages_only_the_new_courier_once(): void
    {
        $second = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => '5004']);
        $order = $this->makeOrder(OrderStatus::READY);

        $this->assignCourier($order, $this->courier)->assertOk();
        $this->assignCourier($order, $second)->assertOk();
        // The same courier again changes nothing
        $this->assignCourier($order, $second)->assertOk();

        $this->assertCount(1, $this->messagesTo($this->courier));
        $this->assertCount(1, $this->messagesTo($second));

        // Handed back through a status update
        $this->setStatus($order, $this->manager, 'courier_assigned', ['courier_id' => $this->courier->id])->assertOk();

        $this->assertCount(2, $this->messagesTo($this->courier));
        $this->assertCount(1, $this->messagesTo($second));
        $this->assertCount(3, $this->assignmentAlerts());

        // Swapped as it leaves: the new courier's one message is the delivery card
        $this->setStatus($order, $this->manager, 'on_the_way', ['courier_id' => $second->id])->assertOk();

        $this->assertCount(2, $this->messagesTo($second));
        $this->assertStringStartsWith("🚚 *Buyurtma yo'lda!*", $this->messagesTo($second)->last()->params['text']);
        $this->assertCount(2, $this->messagesTo($this->courier));
        $this->assertCount(3, $this->assignmentAlerts());
    }

    public function test_nobody_is_messaged_without_a_courier_or_their_telegram_account(): void
    {
        // A store without couriers: its owner stands in, here without Telegram
        $this->courier->delete();
        $this->owner->update(['telegram_id' => null]);
        $order = $this->makeOrder(OrderStatus::READY);

        $this->setStatus($order, $this->manager, 'courier_assigned')->assertOk();
        $this->assertSame($this->owner->id, $order->fresh()->courier_id);

        // An order waiting for a courier that has none (a legacy row)
        event(new OrderStatusUpdated($this->makeOrder(OrderStatus::COURIER_ASSIGNED)));

        $this->assertCount(0, $this->assignmentAlerts());
        // Only the customer heard about these orders
        Queue::assertNotPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => (string) ($job->params['chat_id'] ?? '') !== '5009');
    }

    public function test_the_message_waits_for_the_commit_and_a_refused_or_rolled_back_assignment_sends_none(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);
        $foreignCourier = $this->makeStaff(UserRole::COURIER, null, $this->makeStore('Other Store'), ['telegram_id' => '5005']);

        $this->assignCourier($order, $foreignCourier)->assertStatus(422);

        try {
            DB::transaction(function () use ($order) {
                app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);

                $this->assertCount(0, $this->assignmentAlerts());

                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(OrderStatus::READY, $order->fresh()->status);
        $this->assertCount(0, $this->assignmentAlerts());
        $this->assertCount(0, $this->messagesTo($this->courier));
        $this->assertCount(0, $this->messagesTo($foreignCourier));

        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);

        $this->assertCount(1, $this->messagesTo($this->courier));
    }
}
