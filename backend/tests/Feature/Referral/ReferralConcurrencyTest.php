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
            // The keshbek checkout's notification rows for the store's staff (committed; ON DELETE SET NULL would keep them)
            DB::table('notification_logs')->whereIn('user_id', $userIds)
                ->orWhereIn('order_id', Order::withoutGlobalScopes()->withTrashed()->whereIn('store_id', $stores)->select('id'))->delete();
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
    private function paidOrderOnItsWay(Store $store, User $courier, ?User $customer = null): Order
    {
        $branchId = $store->is($this->store) ? $this->branch->id : (int) $courier->branch_id;
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, [
            'user_id' => ($customer ?? $this->customer)->id,
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
     * Owner's rule B under real concurrency: the referrer has 9 bonuses this month; two OTHER customers they invited
     * deliver at the same moment at two stores. Only one may take the 10th slot: LedgerService::grantReferralReward
     * counts the month under the referrer's users row lock, so the second waits and then sees 10. The parent holds the
     * referrer's row meanwhile, so both deliveries have counted (or wait to count) before either commits.
     */
    public function test_two_friends_delivering_at_once_never_take_the_last_monthly_slot_twice(): void
    {
        $this->assertSame(10, \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER);
        // 9 bonuses booked this month for 9 earlier friends
        for ($i = 0; $i < 9; $i++) {
            $friend = $this->users[] = $this->makeCustomer();
            $friend->forceFill(['referred_by' => $this->referrer->id])->save();
            $earlier = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $friend->id]);
            StoreTransaction::create([
                'store_id' => $this->store->id, 'type' => StoreTransactionType::REFERRAL_BONUS->value, 'amount' => '5000.00',
                'balance_before' => '0.00', 'balance_after' => '0.00', 'reference_id' => $earlier->uuid, 'order_id' => $earlier->id,
                'notes' => \App\Services\Finance\LedgerService::referralBonusNote((int) $friend->id),
            ]);
        }
        $this->referrer->forceFill(['balance' => 45000])->save();
        $this->assertSame(9, \App\Services\Auth\ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));

        $other = $this->users[] = $this->makeCustomer();
        $other->forceFill(['referred_by' => $this->referrer->id])->save();
        $secondCourier = $this->makeStaff(UserRole::COURIER, $this->makeBranch($this->second, 'Ikkinchi filial'), $this->second);
        $deliveries = [
            [$this->paidOrderOnItsWay($this->store, $this->courier), $this->courier->createToken('t')->plainTextToken],
            [$this->paidOrderOnItsWay($this->second, $secondCourier, $other), $secondCourier->createToken('t')->plainTextToken],
        ];

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
            $this->assertNull($order->fresh()->settlement_failed_at);
        }

        $this->assertSame(10, \App\Services\Auth\ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));
        $this->assertEquals(50000, (float) $this->referrer->fresh()->balance);
    }

    /**
     * Review H1: a keshbek checkout locks the customer's users row FOR UPDATE and then inserts the order, whose foreign
     * key check takes FOR KEY SHARE on the store row; a delivery's grant (like its commission and settlement before it)
     * locks the store row and then the customer's and the referrer's users rows. With the store taken FOR UPDATE the two
     * waited on each other (40P01). The ledger takes the store FOR NO KEY UPDATE, which a foreign key check does not wait
     * on: the checkout commits, then the grant goes on. The parent is the checkout (its own connection): it holds the
     * customer's row, as checkout does first, while the child's delivery reaches the grant and waits on it, and only
     * then runs the real checkout in the same transaction.
     */
    public function test_a_keshbek_checkout_and_a_delivery_grant_of_the_same_customer_at_the_same_store_do_not_deadlock(): void
    {
        $this->customer->forceFill(['balance' => 100000])->save();
        $delivery = $this->paidOrderOnItsWay($this->store, $this->courier);
        $token = $this->courier->createToken('t')->plainTextToken;

        DB::beginTransaction();
        User::whereKey($this->customer->id)->lockForUpdate()->first();

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'fork failed');
        if ($pid === 0) {
            $this->runChild(0, fn () => $this->bearerJson('POST', "/api/v1/orders/{$delivery->uuid}/complete", $token));
        }

        usleep(900_000);
        $checkoutError = null;
        try {
            $keshbek = $this->checkout($this->customer, 1, 'keshbek');
            DB::commit();
        } catch (\Throwable $e) {
            $checkoutError = $e;
            DB::rollBack();
        }
        pcntl_waitpid($pid, $status);

        $this->assertNull($checkoutError, (string) $checkoutError);
        $this->assertSame(200, (int) @file_get_contents("{$this->dir}/status-0"), (string) @file_get_contents("{$this->dir}/error-0"));
        // Both committed whole: the keshbek order paid from the balance, the delivery booked with its bonus
        $this->assertSame('keshbek', $keshbek->fresh()->payment_method);
        $this->assertEquals(50000, (float) $this->customer->fresh()->balance);
        $delivery->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $delivery->status);
        $this->assertNull($delivery->settlement_failed_at, (string) $delivery->settlement_error);
        $this->assertSame(1, StoreTransaction::query()->withoutGlobalScopes()->where('type', StoreTransactionType::REFERRAL_BONUS->value)->where('order_id', $delivery->id)->count());
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
