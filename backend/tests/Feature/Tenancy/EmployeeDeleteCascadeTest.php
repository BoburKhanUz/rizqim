<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 3, security review H1: `DELETE /owner/employees/{id}` must not reach another store's money. A person is
 * one users row whatever they do: a store's employee who also bought at another store owns orders there
 * (orders.user_id ON DELETE CASCADE, and bills, payments, refunds, QR payments cascade from orders). Foreign-key
 * actions run as the table owner: no row-level security policy sees them. So an employee with a customer's history is
 * not deleted but taken out of the store; the answer to the owner is the same.
 */
class EmployeeDeleteCascadeTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    public function test_removing_an_employee_who_bought_at_another_store_leaves_that_stores_order_and_payment(): void
    {
        $this->setUpLifecycleStore(); // store A, with the product the checkout buys
        $storeB = $this->makeStore('Boshqa');
        $ownerB = $this->makeStaff(UserRole::OWNER, null, $storeB);
        $employeeB = $this->givePin($this->makeStaff(UserRole::CASHIER, $this->makeBranch($storeB, 'B filial'), $storeB));
        $token = $employeeB->createToken('t')->plainTextToken;
        $order = $this->checkout($employeeB, 2, 'cash', 'pickup');
        $this->assertSame([$this->store->id, 1], [(int) $order->store_id, DB::table('payments')->where('order_id', $order->id)->count()]);

        $this->bearerJson('DELETE', "/api/v1/owner/employees/{$employeeB->id}", $ownerB->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => "Xodim tizimdan o'chirildi.", 'data' => null]);

        $this->assertSame(1, DB::table('orders')->where('id', $order->id)->count(), "store A's order is still there");
        $this->assertSame(1, DB::table('payments')->where('order_id', $order->id)->count(), "store A's payment is still there");
        // Out of the store: no role, no store, no PIN, no session; the person stays as the customer they also are
        $person = User::findOrFail($employeeB->id);
        $this->assertSame([UserRole::CUSTOMER, null, null, null], [$person->role, $person->store_id, $person->branch_id, $person->pin_hash]);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $employeeB->id)->count());
        $this->bearerJson('GET', '/api/v1/owner/employees', $token)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/owner/employees', $ownerB->createToken('t2')->plainTextToken)
            ->assertOk()->assertJsonMissing(['id' => $employeeB->id]);
    }

    /**
     * Audit of the same route: the delete's ON DELETE SET NULL actions would also erase WHO from the store's own money
     * journal (payments.received_by, pos_shifts.opened_by, cash_movements.user_id, orders.waiter_id, audit_logs.user_id
     * and many more). Staff who did anything keep their users row, out of the store.
     *
     * @return array<string, array{0: string}>
     */
    public static function activities(): array
    {
        return ['took a payment' => ['payment'], 'opened a shift' => ['shift'], 'served an order' => ['waiter'], 'delivered an order' => ['courier'], 'acted in the journal' => ['audit']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('activities')]
    public function test_an_employee_who_did_anything_in_the_store_keeps_their_name_on_it(string $activity): void
    {
        $this->setUpLifecycleStore();
        $staff = $this->givePin($this->makeStaff(UserRole::CASHIER, $this->branch));
        $order = $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, match ($activity) {
            'waiter' => ['waiter_id' => $staff->id],
            'courier' => ['courier_id' => $staff->id],
            default => [],
        });
        $row = match ($activity) {
            'payment' => ['payments', 'received_by', DB::table('payments')->insertGetId(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'order_id' => $order->id, 'store_id' => $this->store->id, 'provider' => 'cash', 'amount' => '50000.00', 'status' => 'paid', 'received_by' => $staff->id, 'created_at' => now(), 'updated_at' => now()])],
            'shift' => ['pos_shifts', 'opened_by', DB::table('pos_shifts')->insertGetId(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'business_date' => now()->toDateString(), 'status' => 'closed', 'opened_by' => $staff->id, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()])],
            'waiter' => ['orders', 'waiter_id', $order->id],
            'courier' => ['orders', 'courier_id', $order->id],
            'audit' => ['audit_logs', 'user_id', \App\Models\AuditLog::create(['store_id' => $this->store->id, 'user_id' => $staff->id, 'action' => 'pos.shift_opened'])->id],
        };

        $this->bearerJson('DELETE', "/api/v1/owner/employees/{$staff->id}", $this->owner->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => "Xodim tizimdan o'chirildi.", 'data' => null]);

        $this->assertSame($staff->id, (int) DB::table($row[0])->where('id', $row[2])->value($row[1]), "{$row[0]}.{$row[1]} still names the person");
        $person = User::findOrFail($staff->id);
        $this->assertSame([UserRole::CUSTOMER, null, null, null], [$person->role, $person->store_id, $person->branch_id, $person->pin_hash]);
    }

    public function test_an_account_that_never_did_anything_is_deleted_as_before(): void
    {
        $this->setUpLifecycleStore();
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->makeOrder(\App\Enums\OrderStatus::NEW);

        $this->bearerJson('DELETE', "/api/v1/owner/employees/{$cook->id}", $this->owner->createToken('t')->plainTextToken)->assertOk();

        $this->assertNull(User::find($cook->id));
        $this->assertSame(1, Order::where('store_id', $this->store->id)->count());
    }
}
