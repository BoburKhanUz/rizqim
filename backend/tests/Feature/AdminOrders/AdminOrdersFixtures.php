<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Tests\Feature\AdminFixtures;
use Tests\Feature\OrderLifecycleFixtures;

/**
 * P4-14: the admin API fixtures (members, sign-in, step-up) over the order lifecycle store, plus orders placed at a
 * given time with the status history the SLA reads.
 */
trait AdminOrdersFixtures
{
    use AdminFixtures;
    use OrderLifecycleFixtures;

    /**
     * An order whose history says it was created $createdMinutesAgo minutes ago and reached each status in $steps
     * (status => minutes ago), ending in the last one.
     *
     * @param  array<string, int>  $steps
     * @param  array<string, mixed>  $attributes
     */
    protected function orderWithHistory(int $createdMinutesAgo, array $steps = [], array $attributes = [], ?Store $store = null): Order
    {
        $createdAt = now()->subMinutes($createdMinutesAgo);
        $final = $steps === [] ? OrderStatus::NEW : OrderStatus::from((string) array_key_last($steps));

        $order = $this->makeOrder($final, array_merge([
            'store_id' => ($store ?? $this->store)->id,
            'branch_id' => $store === null ? $this->branch->id : null,
        ], $attributes));
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        $this->history($order, OrderStatus::NEW, null, $createdAt);
        $from = OrderStatus::NEW;
        foreach ($steps as $status => $minutesAgo) {
            $to = OrderStatus::from($status);
            $this->history($order, $to, $from, now()->subMinutes($minutesAgo));
            $from = $to;
        }

        return $order->refresh();
    }

    protected function history(Order $order, OrderStatus $status, ?OrderStatus $from, Carbon $at, ?int $userId = null): void
    {
        $row = new OrderStatusHistory();
        $row->forceFill([
            'order_id' => $order->id,
            'user_id' => $userId,
            'from_status' => $from?->value,
            'status' => $status->value,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    protected function paidPayment(Order $order, string $provider, string $amount = '50000.00', array $payload = []): Payment
    {
        $order->forceFill(['payment_method' => $provider, 'payment_status' => 'paid'])->saveQuietly();

        return Payment::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => now(),
            'payload' => $payload ?: null,
        ]);
    }

    protected function region(string $name): Region
    {
        $region = new Region();
        $region->forceFill(['name_uz' => $name, 'is_active' => true])->save();

        return $region;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function confirmedJson(string $method, string $uri, string $token, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->withHeaders($headers)->json($method, '/api/v1/admin' . $uri, $data);
    }
}
