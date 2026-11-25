<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * Owner decision 3 under real concurrency: a referred customer's two orders, both paid into the platform's own account
 * (verified), are delivered at the same moment at two different stores (forked processes, each with its own connection,
 * each a courier's request bound to its own store by row-level security). The referrer gets the bonus exactly once:
 * LedgerService::grantReferralReward locks the customer's users row before it reads whether the bonus was booked, so
 * the second delivery waits and then sees the first one's. The parent holds the referrer's row meanwhile, so both
 * deliveries are inside the grant at once. Fixtures are committed (the children must see them) and removed in tearDown.
 */
class ReferralConcurrencyTest extends TestCase
{
    use AccessFixtures;

    private string $dir;

    private ?Store $second = null;

    private ?User $referrer = null;

    /** @var list<User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->setUpLifecycleStore();
        $this->second = $this->makeStore('Ikkinchi ovqatxona');
        $this->referrer = $this->users[] = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
        $this->dir = sys_get_temp_dir() . '/rizqim-referral-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            $stores = array_values(array_filter([$this->store->id, $this->second?->id]));
            $userIds = User::whereIn('store_id', $stores)->pluck('id')
                ->merge(collect($this->users)->pluck('id'))->push($this->customer->id)->unique()->all();
            StoreTransaction::query()->withoutGlobalScopes()->whereIn('store_id', $stores)->delete();
            Payment::query()->withoutGlobalScopes()->whereIn('store_id', $stores)->delete();
            Order::withoutGlobalScopes()->withTrashed()->whereIn('store_id', $stores)->forceDelete();
            DB::table('personal_access_tokens')->whereIn('tokenable_id', $userIds)->delete();
            $categoryId = $this->product->category_id;
            $this->product->forceDelete();
            Store::whereKey($stores)->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
        }

        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    /**
     * A 50 000 order of the referred customer on its way with $courier, paid into the platform's own Click account.
     */
    private function paidOrderOnItsWay(Store $store, User $courier): Order
    {
        $branchId = $store->is($this->store) ? $this->branch->id : (int) $courier->branch_id;
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, [
            'store_id' => $store->id,
            'branch_id' => $branchId,
            'courier_id' => $courier->id,
            'payment_method' => 'click',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PAID->value,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'merchant_scope' => Payment::merchantScopeOf($order),
            'provider' => 'click',
            'provider_transaction_id' => 'click-' . Str::random(12),
            'amount' => $order->total_price,
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
        ]);

        return $order;
    }

    public function test_two_verified_deliveries_at_once_grant_the_bonus_once(): void
    {
        $secondCourier = $this->makeStaff(UserRole::COURIER, $this->makeBranch($this->second, 'Ikkinchi filial'), $this->second);
        $deliveries = [
            [$this->paidOrderOnItsWay($this->store, $this->courier), $this->courier->createToken('t')->plainTextToken],
            [$this->paidOrderOnItsWay($this->second, $secondCourier), $secondCourier->createToken('t')->plainTextToken],
        ];

        // The parent holds the referrer's row: both deliveries get as far as crediting the referrer before either commits
        $pids = [];
        DB::beginTransaction();
        User::whereKey($this->referrer->id)->lockForUpdate()->first();

        foreach ($deliveries as $i => [$order, $token]) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');
            if ($pid === 0) {
                $this->runChild($i, fn () => $this->bearerJson('POST', "/api/v1/orders/{$order->uuid}/complete", $token));
            }
            $pids[] = $pid;
        }

        usleep(900_000);
        DB::commit();
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $statuses = array_map(fn (int $i) => (int) @file_get_contents("{$this->dir}/status-{$i}"), array_keys($deliveries));
        $this->assertSame([200, 200], $statuses, (string) @file_get_contents("{$this->dir}/error-0") . @file_get_contents("{$this->dir}/error-1"));
        foreach ($deliveries as [$order]) {
            $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        }

        $rewards = StoreTransaction::query()->withoutGlobalScopes()->where('type', StoreTransactionType::REFERRAL_BONUS->value)
            ->whereIn('store_id', [$this->store->id, $this->second->id])->count();
        $this->assertSame(1, $rewards);
        // The platform pays it: each store's own 5 000 commission was paid from its 50 000 (netting), nothing else
        foreach ([$this->store, $this->second] as $store) {
            $this->assertSame(['0.00', '45000.00'], [(string) $store->fresh()->debt_balance, (string) $store->fresh()->wallet_balance]);
        }
        $this->assertEquals(5000, (float) $this->referrer->fresh()->balance);
    }

    /**
     * In the child: a connection of its own, the request, the status written to a file, and an exit that skips
     * PHPUnit's shutdown (StoreRatingConcurrencyTest::runChild).
     */
    private function runChild(int $i, callable $request): never
    {
        static $inherited = [];

        try {
            $inherited[] = DB::connection()->getPdo();
            DB::purge();
            $this->app['auth']->forgetGuards();

            $response = $request();
            file_put_contents("{$this->dir}/status-{$i}", (string) $response->getStatusCode());
            if ($response->getStatusCode() !== 200) {
                file_put_contents("{$this->dir}/error-{$i}", (string) $response->getContent());
            }
        } catch (\Throwable $e) {
            file_put_contents("{$this->dir}/status-{$i}", '0');
            file_put_contents("{$this->dir}/error-{$i}", (string) $e);
        }

        DB::disconnect();
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }
}
