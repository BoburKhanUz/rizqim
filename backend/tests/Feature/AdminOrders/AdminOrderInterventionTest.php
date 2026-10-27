<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-14: cancel, fix status and messages from the Boshqaruv markazi: admin.orders.intervene, step-up, reason, audited,
 * always through the order lifecycle.
 */
class AdminOrderInterventionTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        config(['services.telegram.bot_token' => '1:token']);
        $this->store->forceFill(['telegram_admin_chat_id' => '-100500'])->save();
        Queue::fake([SendTelegramRequest::class]);
    }

    public function test_operator_cancels_through_the_lifecycle_with_reason_and_audit(): void
    {
        $customer = $this->makeCustomer(100000);
        $order = $this->checkout($customer, 2, 'keshbek');
        $stockBefore = (string) $this->variant->fresh()->stock;

        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($operator);

        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, ['cancel_reason' => 'store_closed'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, ['cancel_reason' => 'payment_timeout', 'reason' => 'Ovqatxona javob bermadi'])
            ->assertStatus(422)->assertJsonValidationErrors('cancel_reason');

        $response = $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, [
            'cancel_reason' => 'store_closed',
            'note' => 'Ovqatxona bugun yopiq',
            'reason' => 'Ovqatxona javob bermadi',
        ])->assertOk();

        $this->assertSame('canceled', $response->json('data.status'));
        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame('store_closed', $order->cancel_reason?->value);
        $this->assertSame($operator->id, (int) $order->cancelled_by);
        // The lifecycle's own side effects: stock back, keshbek back to the balance
        $this->assertGreaterThan((float) $stockBefore, (float) $this->variant->fresh()->stock);
        $this->assertEquals(100000, (float) $customer->fresh()->balance);

        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->sole();
        $this->assertSame($operator->id, (int) $history->user_id);
        $this->assertStringContainsString('Boshqaruv markazi: Ovqatxona javob bermadi', (string) $history->notes);

        $entry = AuditLog::where('action', 'admin.orders.cancelled')->sole();
        $this->assertSame(['status' => 'new', 'cancel_reason' => null], $entry->before);
        $this->assertSame('canceled', $entry->after['status']);
        $this->assertSame('store_closed', $entry->after['cancel_reason']);
        $this->assertSame('Ovqatxona javob bermadi', $entry->payload_json['reason']);
        $this->assertSame($this->store->id, (int) $entry->store_id);
        $this->assertSame($order->id, (int) $entry->model_id);
        // The store's own audit entry, with the team member as the actor
        $this->assertSame($operator->id, (int) AuditLog::where('action', 'order.cancelled')->sole()->user_id);

        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100500'
            && str_contains($job->params['text'], 'RIZQIM jamoasi tomonidan bekor qilindi'));

        // Finished: cannot be cancelled again
        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, ['cancel_reason' => 'other', 'reason' => 'Yana bir bor'])
            ->assertStatus(422)->assertJsonPath('code', 'illegal');
    }

    public function test_actions_need_the_permission_and_a_step_up(): void
    {
        $order = $this->orderWithHistory(10);
        $payload = ['cancel_reason' => 'other', 'reason' => 'Sinov uchun'];

        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $this->confirmedSession($viewer), $payload)->assertForbidden();
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $this->confirmedSession($support), ['status' => 'accepted', 'reason' => 'Sinov uchun'])->assertForbidden();

        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);
        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, $payload)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->adminJson('POST', "/orders/{$order->uuid}/message", $token, ['to' => 'store', 'text' => 'Salom', 'reason' => 'Sinov uchun'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'like', 'admin.orders.%')->count());
    }

    public function test_fix_status_only_moves_the_lifecycle_allows(): void
    {
        $order = $this->orderWithHistory(10, ['accepted' => 9, 'preparing' => 8], ['order_type' => 'delivery']);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($operator);

        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'delivered', 'reason' => 'Kuryer topshirdi'])
            ->assertStatus(422)->assertJsonPath('code', 'illegal');
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'canceled', 'reason' => 'Bekor'])
            ->assertStatus(422)->assertJsonPath('code', 'use_cancel');
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'refunded', 'reason' => 'Qaytarish'])
            ->assertStatus(422)->assertJsonPath('code', 'use_refund_request');

        $card = $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'ready', 'reason' => 'Oshxona tugmani bosmagan'])
            ->assertOk()->json('data');
        $this->assertSame('ready', $card['status']);
        $this->assertSame(OrderStatus::READY, $order->fresh()->status);
        $this->assertSame($operator->id, (int) OrderStatusHistory::where('order_id', $order->id)->where('status', 'ready')->sole()->user_id);

        $entry = AuditLog::where('action', 'admin.orders.status_fixed')->sole();
        $this->assertSame('preparing', $entry->before['status']);
        $this->assertSame('ready', $entry->after['status']);
        $this->assertSame('Oshxona tugmani bosmagan', $entry->payload_json['reason']);

        // To a courier: the given one must be the store's
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'courier_assigned', 'courier_id' => $operator->id, 'reason' => 'Kuryer berildi'])
            ->assertStatus(422)->assertJsonPath('code', 'courier');
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'courier_assigned', 'courier_id' => $this->courier->id, 'reason' => 'Kuryer berildi'])
            ->assertOk()->assertJsonPath('data.courier.id', $this->courier->id);
    }

    public function test_messages_to_the_store_and_the_customer(): void
    {
        $this->customer->forceFill(['telegram_id' => '555000111'])->save();
        $order = $this->orderWithHistory(10);
        $token = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));

        $this->adminJson('POST', "/orders/{$order->uuid}/message", $token, ['to' => 'store', 'text' => 'Buyurtmani qabul qiling, iltimos', 'reason' => 'Kechikmoqda'])
            ->assertOk()->assertJsonPath('data.recipients', 2); // the store's chat and the branch manager
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100500'
            && str_contains($job->params['text'], 'Buyurtmani qabul qiling, iltimos')
            && !isset($job->params['parse_mode']));

        $this->adminJson('POST', "/orders/{$order->uuid}/message", $token, ['to' => 'customer', 'text' => 'Buyurtmangiz biroz kechikadi', 'reason' => 'Mijozni ogohlantirish'])
            ->assertOk()->assertJsonPath('data.recipients', 1);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '555000111');

        $entries = AuditLog::where('action', 'admin.orders.messaged')->orderBy('id')->get();
        $this->assertSame(['store', 'customer'], $entries->map(fn ($entry) => $entry->payload_json['to'])->all());
        $this->assertSame('Kechikmoqda', $entries[0]->payload_json['reason']);

        $this->customer->forceFill(['telegram_id' => null])->save();
        $this->adminJson('POST', "/orders/{$order->uuid}/message", $token, ['to' => 'customer', 'text' => 'Salom', 'reason' => 'Sinov uchun'])
            ->assertStatus(422)->assertJsonPath('code', 'no_recipient');
        $this->adminJson('POST', "/orders/{$order->uuid}/message", $token, ['to' => 'courier', 'text' => 'Salom', 'reason' => 'Sinov uchun'])
            ->assertStatus(422);
    }
}
