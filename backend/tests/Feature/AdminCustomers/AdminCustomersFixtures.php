<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Tests\Feature\AdminFixtures;
use Tests\Feature\OrderLifecycleFixtures;

/**
 * P4-31: the admin API fixtures (members, sign-in, step-up) over the order lifecycle store (owner, manager, courier, a
 * customer), plus complaints and orders of a given customer placed some time ago.
 */
trait AdminCustomersFixtures
{
    use AdminFixtures;
    use OrderLifecycleFixtures;

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function orderOf(User $customer, OrderStatus $status = OrderStatus::DELIVERED, array $attributes = [], int $minutesAgo = 60): Order
    {
        $order = $this->makeOrder($status, array_merge(['user_id' => $customer->id], $attributes));
        $at = now()->subMinutes($minutesAgo);
        $order->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function complaintOf(User $customer, ?Order $order = null, array $attributes = [], int $hoursAgo = 1): Complaint
    {
        $order ??= $this->orderOf($customer);
        $complaint = Complaint::create(array_merge([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'QUALITY_ISSUE',
            'description' => 'Ovqat sovuq keldi',
            'status' => 'pending',
        ], $attributes));
        $at = now()->subHours($hoursAgo);
        $complaint->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $complaint->refresh();
    }
}
