<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The harakatlar jurnali: what is recorded, that no secret or clear phone number is, and that owners read only
 * their own store's log.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function deliver(Order $order): Order
    {
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, null);
        }

        return $order->fresh();
    }

    public function test_a_staff_cancellation_is_logged_with_its_reason_and_without_phone_numbers(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'out_of_stock', 'note' => 'Mijoz +998 90 123 45 67 ga qo\'ng\'iroq qilindi'])
            ->assertOk();

        $log = AuditLog::where('action', 'order.cancelled')->sole();
        $this->assertSame($this->manager->id, $log->user_id);
        $this->assertSame($this->store->id, $log->store_id);
        $this->assertSame($this->branch->id, $log->branch_id);
        $this->assertSame($order->id, (int) $log->model_id);
        $this->assertSame(['status' => 'new'], $log->before);
        $this->assertSame(['status' => 'canceled'], $log->after);
        $this->assertSame('out_of_stock', $log->payload_json['reason']);
        $this->assertNotNull($log->ip);

        $raw = json_encode($log->getAttributes());
        $this->assertStringNotContainsString('123 45 67', $raw);
        $this->assertStringNotContainsString('901234567', $raw);
        $this->assertStringContainsString('67', $log->payload_json['note']);
    }

    public function test_rejections_staff_status_changes_and_customer_cancels_are_logged(): void
    {
        $rejected = $this->makeOrder(OrderStatus::NEW);
        $this->setStatus($rejected, $this->owner, 'rejected', ['reason' => 'store_closed'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.rejected', 'model_id' => $rejected->id, 'user_id' => $this->owner->id]);

        $accepted = $this->makeOrder(OrderStatus::NEW);
        $this->setStatus($accepted, $this->manager, 'accepted')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.status_changed', 'model_id' => $accepted->id, 'user_id' => $this->manager->id]);

        $own = $this->makeOrder(OrderStatus::NEW);
        $this->actingAs($this->customer->fresh(), 'sanctum')->postJson("/api/v1/orders/{$own->uuid}/cancel", ['reason' => 'customer_request'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.cancelled', 'model_id' => $own->id, 'user_id' => $this->customer->id]);

        // The system's own moves (here: a delivery run without an actor) are not staff actions
        $this->deliver($this->makeOrder(OrderStatus::NEW, ['order_type' => 'pickup']));
        $this->assertSame(3, AuditLog::count());
    }

    public function test_only_staff_and_terminal_addresses_are_kept_and_only_owners_see_them(): void
    {
        $staffOrder = $this->makeOrder(OrderStatus::NEW);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        $this->setStatus($staffOrder, $this->manager, 'accepted')->assertOk();

        $own = $this->makeOrder(OrderStatus::NEW);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
        $this->actingAs($this->customer->fresh(), 'sanctum')->postJson("/api/v1/orders/{$own->uuid}/cancel", ['reason' => 'customer_request'])->assertOk();

        $this->assertSame('203.0.113.10', AuditLog::where('model_id', $staffOrder->id)->sole()->ip);
        $customerLog = AuditLog::where('model_id', $own->id)->where('user_id', $this->customer->id)->sole();
        $this->assertNull($customerLog->ip);

        $entries = collect($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/audit-logs')->assertOk()->json('data.data'));
        $this->assertSame('203.0.113.10', $entries->firstWhere('subject_id', $staffOrder->id)['ip']);
        $this->assertNull($entries->firstWhere('subject_id', $own->id)['ip']);
        $this->assertStringNotContainsString('198.51.100.77', $entries->toJson());
    }

    public function test_refunds_are_logged(): void
    {
        $admin = User::create(['name' => 'Admin', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('password')]);
        $order = $this->deliver($this->checkout($this->customer, 1));

        app(OrderStatusService::class)->refund($order, $admin, 'Taom sifatsiz edi');

        $log = AuditLog::where('action', 'order.refunded')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Taom sifatsiz edi', $log->payload_json['note']);
        $this->assertSame($this->store->id, $log->store_id);
    }

    public function test_price_changes_are_logged(): void
    {
        $this->actingAs($this->owner);
        $this->variant->refresh()->update(['price' => 55000]);
        $this->variant->update(['stock' => 3]); // not a price change

        $log = AuditLog::where('action', 'product.price_changed')->sole();
        $this->assertSame(['price' => '50000.00'], $log->before);
        $this->assertSame(['price' => 55000], $log->after);
        $this->assertSame($this->store->id, $log->store_id);
        $this->assertSame($this->owner->id, $log->user_id);
    }

    public function test_pin_device_and_permission_changes_are_logged_without_secrets(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$cashier->id}/pin", ['pin' => '4831'])->assertOk();
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/staff/{$cashier->id}/pin")->assertOk();

        $code = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', ['type' => 'pos'])->json('data.code');
        $paired = $this->withBearer(null)->postJson('/api/v1/devices/pair', ['code' => $code])->assertCreated()->json('data');
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/devices/{$paired['device']['uuid']}/revoke")->assertOk();

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', ['role' => 'waiter', 'permission' => 'orders.discount', 'allowed' => true])->assertOk();

        $actions = AuditLog::orderBy('id')->pluck('action')->all();
        foreach (['staff.pin_set', 'staff.pin_reset', 'device.pairing_code_created', 'device.paired', 'device.revoked', 'permissions.changed'] as $action) {
            $this->assertContains($action, $actions);
        }
        $this->assertSame($this->store->id, AuditLog::where('action', 'device.paired')->value('store_id'));
        $this->assertNotNull(AuditLog::where('action', 'device.paired')->value('device_id'));

        $everything = AuditLog::all()->map->getAttributes()->toJson();
        $this->assertStringNotContainsString('4831', $everything);
        $this->assertStringNotContainsString($code, $everything);
        $this->assertStringNotContainsString($paired['token'], $everything);
        $this->assertStringNotContainsString(explode('|', $paired['token'])[1], $everything);
    }

    public function test_a_pin_session_is_logged_with_its_terminal(): void
    {
        $cashier = $this->givePin($this->makeStaff(UserRole::CASHIER, $this->branch), '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $cashier->id, 'pin' => '4831'])->assertOk();

        $log = AuditLog::where('action', 'staff.session_started')->sole();
        $this->assertSame($cashier->id, $log->user_id);
        $this->assertSame($terminal['device']->id, $log->device_id);
    }

    public function test_actions_through_a_pin_session_name_the_terminal(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->givePin($this->manager, '5926');
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $session = $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $this->manager->id, 'pin' => '5926'])
            ->json('data.token');

        $this->bearerJson('POST', "/api/v1/orders/{$order->uuid}/status", $session, ['status' => 'canceled', 'reason' => 'other'])->assertOk();

        $log = AuditLog::where('action', 'order.cancelled')->sole();
        $this->assertSame($this->manager->id, $log->user_id);
        $this->assertSame($terminal['device']->id, $log->device_id);
    }

    public function test_owners_read_only_their_own_store_log_with_filters(): void
    {
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreignStore);
        $this->setStatus($this->makeOrder(OrderStatus::NEW), $this->owner, 'canceled')->assertOk();
        $this->setStatus($this->makeOrder(OrderStatus::NEW), $this->manager, 'accepted')->assertOk();
        AuditLog::create(['action' => 'order.cancelled', 'store_id' => $foreignStore->id]);

        $page = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/audit-logs')->assertOk()->json('data');
        $this->assertSame(2, $page['total']);
        $this->assertSame('order.status_changed', $page['data'][0]['action']);
        $this->assertSame('Order', $page['data'][0]['subject_type']);
        $this->assertSame(['id' => $this->manager->id, 'name' => $this->manager->name, 'role' => 'manager'], $page['data'][0]['actor']);

        $this->assertSame(1, $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/audit-logs?action=order.cancelled')->json('data.total'));
        $this->assertSame(2, $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/audit-logs?action=order.*')->json('data.total'));
        $this->assertSame(1, $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/audit-logs?user_id={$this->manager->id}")->json('data.total'));
        $this->assertSame(0, $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/audit-logs?from=' . now()->addDay()->toDateString())->json('data.total'));

        $this->assertSame(1, $this->actingAs($foreignOwner, 'sanctum')->getJson('/api/v1/owner/audit-logs')->json('data.total'));

        // The log is the owner's (audit.view)
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/audit-logs')->assertForbidden();
        $this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/owner/audit-logs')->assertForbidden();
    }

    public function test_super_admin_model_overrides_are_redacted(): void
    {
        $admin = User::create(['name' => 'Admin', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('password')]);
        $this->actingAs($admin);

        $this->owner->update(['phone' => '+998935551122', 'telegram_invite_token' => 'emp_super_secret']);

        $payload = json_encode(AuditLog::where('action', 'superadmin_override')->latest('id')->value('payload_json'));
        $this->assertStringNotContainsString('5551122', $payload);
        $this->assertStringNotContainsString('emp_super_secret', $payload);
    }
}
