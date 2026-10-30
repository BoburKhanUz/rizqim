<?php

namespace Tests\Feature\PosStaffPay;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\ShiftStatus;
use App\Models\Branch;
use App\Models\OrderBill;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;

/**
 * P6-23 helpers: a restoran branch (staff_pay on by its profile), closed till shifts on chosen business days, paid
 * bills of POS orders, and owner-API requests as a given person.
 */
trait StaffPayFixtures
{
    use PosBillingFixtures;

    protected function setUpStaffPay(): void
    {
        $this->setUpPosBilling();
        $this->profile(FormatProfile::RESTORAN);
    }

    /**
     * @param  array<string, bool>  $modules
     */
    protected function profile(FormatProfile $profile, array $modules = [], ?Branch $branch = null): void
    {
        $branch ??= $this->branch;
        $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->fresh()->pos_settings, $profile, $modules)])->save();
    }

    /**
     * A closed till shift of the branch on a business day, opened by $by (UTC moments).
     */
    protected function closedShift(string $businessDate, User $by, string $openedAt, string $closedAt): PosShift
    {
        return PosShift::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'device_id' => $this->till['device']->id,
            'business_date' => $businessDate,
            'status' => ShiftStatus::CLOSED->value,
            'opened_by' => $by->id,
            'opened_at' => Carbon::parse($openedAt, 'UTC'),
            'closed_by' => $by->id,
            'closed_at' => Carbon::parse($closedAt, 'UTC'),
            'opening_cash' => '0',
        ]);
    }

    /**
     * A paid bill of a POS order of $waiter (null: an order without a waiter, the bill made by $madeBy).
     */
    protected function paidBill(?User $waiter, PosShift $shift, string $subtotal, string $discount = '0', string $service = '0', ?User $madeBy = null, ?string $paidAt = null): OrderBill
    {
        $order = $this->posOrder([['Taom', '1', $subtotal]], ['waiter_id' => $waiter?->id]);
        $total = Money::of($subtotal)->minus($discount)->plus($service);

        return OrderBill::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'number' => 1,
            'status' => 'paid',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'service_charge_amount' => $service,
            'total' => $total->toString(),
            'basis' => Str::random(16),
            'paid_at' => Carbon::parse($paidAt ?? $shift->opened_at->copy()->addHour(), 'UTC'),
            'shift_id' => $shift->id,
            'created_by' => ($madeBy ?? $this->cashier)->id,
        ]);
    }

    protected function as(User $user, string $method, string $uri, array $data = [], ?string $key = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $request = $this->actingAs($user, 'sanctum');
        if ($key !== null) {
            $request = $request->withHeader('Idempotency-Key', $key);
        }

        return $request->json($method, '/api/v1' . $uri, $data);
    }
}
