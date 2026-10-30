<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolPeriod;
use App\Domain\Pos\Tips\TipPoolRule;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosTipSetting;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;

/**
 * P6-22 helpers: the P3-18 till (cashier, waiter, manager with a PIN) on a `restoran` branch (tips and service charge
 * rules on), a second waiter, the branch's tip settings, an order served by the waiter and owner API requests.
 */
trait PosTipsFixtures
{
    use PosBillingFixtures;

    protected User $waiter2;

    protected function setUpTips(string $profile = 'restoran'): void
    {
        $this->setUpPosBilling();
        $this->profile($this->branch, $profile);
        $this->waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
    }

    protected function profile(Branch $branch, string $profile, array $modules = []): void
    {
        $settings = is_array($branch->fresh()->pos_settings) ? $branch->fresh()->pos_settings : [];
        Branch::whereKey($branch->id)->update(['pos_settings' => json_encode(['profile' => $profile, 'modules' => $modules] + $settings)]);
        app()->forgetScopedInstances();
    }

    protected function tipSettings(TipMode $mode, TipPoolRule $rule = TipPoolRule::EQUAL, array $roles = ['waiter'], array $weights = [], TipPoolPeriod $period = TipPoolPeriod::DAY): void
    {
        PosTipSetting::updateOrCreate(['branch_id' => $this->branch->id], [
            'store_id' => $this->store->id,
            'mode' => $mode,
            'pool_rule' => $rule,
            'pool_period' => $period,
            'roles' => $roles,
            'role_weights' => $weights,
        ]);
    }

    /**
     * A hall order served by `$waiter` (default the first waiter), billed once: [order, bill uuid].
     *
     * @return array{0: Order, 1: string}
     */
    protected function billed(array $lines = [['Osh', '1', '143000']], ?User $waiter = null, array $attributes = []): array
    {
        $order = $this->posOrder($lines, $attributes + ['waiter_id' => ($waiter ?? $this->waiter)->id]);

        return [$order, (string) $this->bill($order)->assertCreated()->json('data.bills.0.uuid')];
    }

    protected function owner(string $method, string $uri, array $data = [], ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user ?? $this->owner, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }
}
