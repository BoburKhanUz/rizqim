<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\User;
use App\Services\Auth\ReferralService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * The owner's two further rules for the referral bonus (ReferralService::qualifiesForReferralBonus, one place):
 *  - A: the bonus never exceeds what the platform earns on the order: the order's commission as booked
 *    (orders.commission_amount) is at least the bonus (5 000);
 *  - B: at most ReferralService::MONTHLY_CAP_PER_REFERRER (10) bonuses per referrer per Asia/Tashkent month, net of
 *    clawbacks, counted under the referrer's row lock.
 * An order that fails either neither earns it nor uses the referral up: it stays pending for a later order. Keshbek
 * orders never count as verified (keshbek itself only comes from this bonus).
 */
class ReferralRulesTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use ReferralOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['credit_limit' => -1000000])->save();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_commission_must_cover_the_bonus(): void
    {
        $cases = [
            '50 000 at 10 %' => [50000, 5000, true],
            '50 000 at 9 %' => [50000, 4500, false],
            '100 000 at 5 %' => [100000, 5000, true],
            'commission exactly 5 000' => [62500, 5000, true],
            '0 %' => [200000, 0, false],
        ];

        foreach ($cases as $case => [$total, $commission, $grants]) {
            $this->assertSame($grants, ReferralService::qualifiesForReferralBonus(
                $this->paidOrder('platform_click', ['total_price' => $total, 'commission_amount' => $commission])
            ), $case);
        }
    }

    public function test_an_order_whose_commission_is_below_the_bonus_neither_earns_it_nor_uses_it_up(): void
    {
        $this->deliveredOrder('platform_click', ['commission_amount' => 4500]); // 50 000 at 9 %
        $this->deliveredOrder('platform_click', ['total_price' => 200000, 'commission_amount' => 0]); // 0 %
        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());

        $order = $this->deliveredOrder('platform_click'); // 50 000 at 10 %
        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
    }

    public function test_keshbek_orders_never_count_as_verified(): void
    {
        $keshbek = $this->paidOrder('keshbek');
        $this->assertFalse(ReferralService::paymentVerifiedByPlatform($keshbek));

        $this->deliveredOrder('keshbek');
        $this->assertSame([], $this->rewards());

        // ...and never use the referral up
        $this->deliveredOrder('platform_click');
        $this->assertCount(1, $this->rewards());
    }

    /**
     * $count referred customers of the referrer, each delivering one qualifying order now.
     *
     * @return list<User>
     */
    private function friendsDeliver(int $count): array
    {
        $friends = [];
        for ($i = 0; $i < $count; $i++) {
            $friend = $this->makeCustomer();
            $friend->forceFill(['referred_by' => $this->referrer->id])->save();
            $this->friendDelivers($friend);
            $friends[] = $friend;
        }

        return $friends;
    }

    private function friendDelivers(User $friend): \App\Models\Order
    {
        $previous = $this->customer;
        $this->customer = $friend;
        try {
            return $this->deliveredOrder('platform_click');
        } finally {
            $this->customer = $previous;
        }
    }

    public function test_at_most_ten_bonuses_per_referrer_in_a_month_then_the_next_month_grants(): void
    {
        $this->assertSame(10, ReferralService::MONTHLY_CAP_PER_REFERRER);
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Tashkent'));
        $this->friendsDeliver(10);
        $this->assertCount(10, $this->rewards());
        $this->assertSame(50000.0, $this->referrerBalance());

        // The 11th friend this month: refused, still pending
        [$eleventh] = $this->friendsDeliver(1);
        $this->assertCount(10, $this->rewards());
        $this->assertSame(50000.0, $this->referrerBalance());
        $this->assertSame(10, ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));

        // Next month (Asia/Tashkent: 1 December 00:30 there is still 30 November in UTC) the friend's next order earns it
        Carbon::setTestNow(Carbon::parse('2026-12-01 00:30:00', 'Asia/Tashkent'));
        $order = $this->friendDelivers($eleventh);
        $this->assertCount(11, $this->rewards());
        $this->assertSame($order->id, $this->rewards()[10]['order_id']);
        $this->assertSame(55000.0, $this->referrerBalance());
        $this->assertSame(1, ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));
    }

    public function test_a_clawback_in_the_month_frees_a_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Tashkent'));
        $first = $this->deliveredOrder('platform_click');
        $this->friendsDeliver(9);
        [$waiting] = $this->friendsDeliver(1);
        $this->assertCount(10, $this->rewards());

        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        app(OrderStatusService::class)->refund($first, $admin, 'Mijoz shikoyati');
        $this->assertSame(9, ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));

        $this->friendDelivers($waiting);
        $this->assertSame(10, ReferralService::monthlyBonusesOf((int) $this->referrer->id, now()));
        $this->assertSame(OrderStatus::REFUNDED, $first->fresh()->status);
        $this->assertSame(50000.0, $this->referrerBalance());
    }
}
