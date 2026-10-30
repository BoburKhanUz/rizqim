<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-17 under real concurrency (forked processes, each with its own connection, as MenuConcurrencyTest): two bills
 * offsetting one deposit at the same moment never take the same money twice, and two cashiers booking one room for
 * the same evening get one booking. Fixtures are committed and removed in tearDown.
 */
class PosBookingConcurrencyTest extends TestCase
{
    use PosBookingFixtures;
    use \Tests\Feature\AuditLogCleanup;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->setUpBookings();
        $this->dir = sys_get_temp_dir() . '/rizqim-bookings-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            $storeIds = [$this->store->id, $this->foreignStore->id];
            $userIds = User::whereIn('store_id', $storeIds)->pluck('id')->push($this->customer->id)->all();

            $this->withoutAuditLogGuard(fn () => AuditLog::whereIn('store_id', $storeIds)->orWhereIn('user_id', $userIds)->delete());
            IdempotencyKey::where('actor_type', 'user')->whereIn('actor_id', $userIds)->delete();
            Booking::whereIn('store_id', $storeIds)->delete();
            Order::withTrashed()->whereIn('store_id', $storeIds)->forceDelete();
            $categoryId = $this->product->category_id;
            Store::withTrashed()->whereIn('id', $storeIds)->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
        }

        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function test_two_bills_racing_for_one_deposit_take_it_once(): void
    {
        $uuid = $this->booked();
        $this->takeDeposit($uuid, '1000000', 'qr')->assertCreated();
        $order = Order::where('uuid', $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive")->assertOk()->json('data.order.uuid'))->firstOrFail();
        $bookingId = (int) $order->booking_id;

        // Each wants 700 000 of the 1 000 000: only one can have it
        $results = $this->race(3, function (int $i) use ($order) {
            app(AdvanceLedger::class)->apply(Order::findOrFail($order->id), Money::of('700000'), (string) Str::uuid());

            return 'ok';
        }, fn () => Booking::whereKey($bookingId)->lockForUpdate()->first());

        sort($results);
        $this->assertSame(['insufficient_advance', 'insufficient_advance', 'ok'], $results);
        $payment = BookingPayment::firstOrFail();
        $this->assertSame('700000.00', $payment->applied_amount);
        $this->assertSame('300000.00', app(AdvanceLedger::class)->available($order)->toString());
    }

    public function test_two_cashiers_booking_one_room_at_once_get_one_booking(): void
    {
        $at = microtime(true) + 0.4;

        $results = $this->race(4, function (int $i) use ($at) {
            time_sleep_until(max($at, microtime(true) + 0.001));

            return (string) $this->createBooking(['contact_name' => "Guruh {$i}"])->getStatusCode();
        }, fn () => null);

        sort($results);
        $this->assertSame(['201', '409', '409', '409'], $results);
        $this->assertSame(1, Booking::where('branch_id', $this->branch->id)->count());
    }

    /**
     * Forks `$count` children that each run `$work` on a connection of their own once the parent lets go of the row
     * `$hold` locked; returns what each returned, or the `reason` of the booking exception it threw.
     *
     * @param  callable(int): string  $work
     * @param  callable(): mixed  $hold
     * @return list<string>
     */
    private function race(int $count, callable $work, callable $hold): array
    {
        $pids = [];
        DB::beginTransaction();
        $hold();

        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');

            if ($pid === 0) {
                static $inherited = [];
                try {
                    $inherited[] = DB::connection()->getPdo();
                    DB::purge();
                    $this->app['auth']->forgetGuards();
                    $result = $work($i);
                } catch (\App\Domain\Pos\Booking\BookingException $e) {
                    $result = $e->reason;
                } catch (\Throwable $e) {
                    $result = 'error: ' . $e->getMessage();
                }
                file_put_contents("{$this->dir}/result-{$i}", $result);
                DB::disconnect();
                posix_kill(getmypid(), SIGKILL);
                exit(0);
            }

            $pids[] = $pid;
        }

        usleep(700_000);
        DB::commit();

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        return array_map(fn (int $i) => (string) @file_get_contents("{$this->dir}/result-{$i}"), range(0, $count - 1));
    }
}
