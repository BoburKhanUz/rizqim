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

    public function test_an_employee_without_a_customers_history_is_deleted_as_before(): void
    {
        $this->setUpLifecycleStore();
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['waiter_id' => $cook->id]); // served it, did not buy it

        $this->bearerJson('DELETE', "/api/v1/owner/employees/{$cook->id}", $this->owner->createToken('t')->plainTextToken)->assertOk();

        $this->assertNull(User::find($cook->id));
        $this->assertSame(1, Order::where('store_id', $this->store->id)->count());
    }
}
