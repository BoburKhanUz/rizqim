<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Middleware\BindTenant;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: the `tenant` middleware binds the caller's store for one request only: staff (also in a PIN session on
 * a terminal) -> users.store_id, a terminal's own token -> devices.store_id, a caller without a store -> nothing. Several
 * requests in one process (as every feature test makes them) never see the store of the request before, also after
 * a request that failed. Nothing here forgets scoped instances between the requests.
 */
class TenantBindingTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $probe = fn () => response()->json(['store' => app(TenantContext::class)->storeId(), 'orders' => Order::query()->orderBy('id')->pluck('id')]);
        Route::middleware(['api', 'auth:sanctum', 'tenant'])->get('api/test-tenant/staff', $probe);
        Route::middleware(['api', 'auth:device', 'tenant'])->get('api/test-tenant/device', $probe);
        Route::middleware(['api', 'auth:sanctum'])->get('api/test-tenant/none', $probe);
        Route::middleware(['api', 'auth:sanctum', 'tenant'])->get('api/test-tenant/fails', fn () => throw new RuntimeException('till error'));
    }

    private function storeOf(string $uri, ?string $token): mixed
    {
        $response = $this->bearerJson('GET', $uri, $token)->assertOk();
        $this->assertNull(app(TenantContext::class)->storeId(), "{$uri}: the store outlived the request");

        return $response->json('store');
    }

    public function test_each_request_binds_its_own_caller_and_nothing_outlives_it(): void
    {
        $a = $this->makeStore('A');
        $branchA = $this->makeBranch($a, 'A1');
        $b = $this->makeStore('B');
        $branchB = $this->makeBranch($b, 'B1');
        $ownerA = $this->makeStaff(UserRole::OWNER, null, $a);
        $ownerB = $this->makeStaff(UserRole::OWNER, null, $b);
        $cashierA = $this->makeStaff(UserRole::CASHIER, $branchA, $a);
        $customer = $this->makeCustomer();
        $tillA = $this->pairTerminal($ownerA, $branchA, DeviceType::POS);
        $tillB = $this->pairTerminal($ownerB, $branchB, DeviceType::POS);
        $this->givePin($cashierA, '4831');
        $pin = (string) $this->bearerJson('POST', "/api/v1/devices/{$tillA['device']->uuid}/staff-session", $tillA['token'], [
            'user_id' => $cashierA->id,
            'pin' => '4831',
        ])->assertOk()->json('data.token');
        $tokenA = $ownerA->createToken('t')->plainTextToken;
        $tokenB = $ownerB->createToken('t')->plainTextToken;
        $customerToken = $customer->createToken('t')->plainTextToken;
        $this->customer = $customer;
        $this->store = $a;
        $this->branch = $branchA;
        $orderA = $this->makeOrder(OrderStatus::NEW, ['store_id' => $a->id, 'branch_id' => $branchA->id]);
        $orderB = $this->makeOrder(OrderStatus::NEW, ['store_id' => $b->id, 'branch_id' => $branchB->id]);

        $this->assertSame($a->id, $this->storeOf('/api/test-tenant/staff', $tokenA));
        // The very next request of the process, by a caller without a store: unbound, sees every row as before
        $this->assertNull($this->storeOf('/api/test-tenant/staff', $customerToken));
        $this->assertSame([$orderA->id, $orderB->id], $this->bearerJson('GET', '/api/test-tenant/staff', $customerToken)->json('orders'));
        $this->assertSame($b->id, $this->storeOf('/api/test-tenant/device', $tillB['token']));
        $this->assertSame($a->id, $this->storeOf('/api/test-tenant/staff', $pin));
        $this->assertSame($a->id, $this->storeOf('/api/test-tenant/device', $tillA['token']));
        $this->assertSame([$orderB->id], $this->bearerJson('GET', '/api/test-tenant/staff', $tokenB)->json('orders'));
        $this->assertSame([$orderA->id], $this->bearerJson('GET', '/api/test-tenant/staff', $tokenA)->json('orders'));
        // A route outside the tenant groups, same owner token: unbound
        $this->assertNull($this->storeOf('/api/test-tenant/none', $tokenA));
        $this->assertSame([$orderA->id, $orderB->id], $this->bearerJson('GET', '/api/test-tenant/none', $tokenA)->json('orders'));

        // A request that fails inside the tenant binding leaves nothing behind either
        $this->bearerJson('GET', '/api/test-tenant/fails', $tokenB)->assertStatus(500);
        $this->assertNull(app(TenantContext::class)->storeId());
        $this->assertSame(2, Order::query()->count(), 'the test, after the requests, reads unscoped');
    }

    public function test_a_caller_without_a_store_gets_todays_answer_on_a_tenant_route(): void
    {
        $customerToken = $this->makeCustomer()->createToken('t')->plainTextToken;
        $routes = ['GET /api/v1/pos/bootstrap', 'GET /api/v1/owner/products', 'GET /api/v1/staff/orders', 'GET /api/v1/complaints', 'POST /api/v1/pos/orders', 'GET /api/v1/terminal'];

        foreach ($routes as $route) {
            [$method, $uri] = explode(' ', $route);
            $bound = $this->bearerJson($method, $uri, $customerToken);
            $this->assertNull(app(TenantContext::class)->storeId());
            $unbound = $this->withoutMiddleware(BindTenant::class)->bearerJson($method, $uri, $customerToken);
            $this->withMiddleware(BindTenant::class);
            $this->assertSame([$unbound->status(), $unbound->json()], [$bound->status(), $bound->json()], $route);
        }
    }

    public function test_store_of_each_kind_of_caller(): void
    {
        $store = $this->makeStore('S');
        $this->assertSame($store->id, BindTenant::storeOf($this->makeStaff(UserRole::COOK, null, $store)));
        $this->assertNull(BindTenant::storeOf($this->makeCustomer()));
        // A customer row that kept a store id (staff removed from the store) is not staff: not bound
        $this->assertNull(BindTenant::storeOf($this->makeCustomer(0, ['store_id' => $store->id])));
        $this->assertNull(BindTenant::storeOf(null));
    }
}
