<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;

/**
 * P2-11 helpers: orders with lines (a piece line with modifiers, a weighed line), and their status history.
 */
trait StaffOrderFixtures
{
    use AccessFixtures;

    /**
     * An order with two lines: 2 x Cheeseburger (Regular) with a sauce, and 1.5 kg Osh. The status history has the
     * creation row and, when the order is past it, an accepted row.
     */
    protected function orderWithItems(OrderStatus $status, array $attributes = []): Order
    {
        $order = $this->makeOrder($status, $attributes + [
            'notes' => 'Piyozsiz. Qo\'ng\'iroq: +998901234567',
            'order_type' => 'pickup',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Cheeseburger',
            'variant_name' => 'Regular',
            'quantity' => 2,
            'unit' => 'piece',
            'unit_price' => 50000,
            'modifiers' => [['id' => 7, 'uuid' => 'a1', 'group_id' => 3, 'group_name' => 'Sous', 'name' => 'Achchiq sous', 'price_delta' => '3000.00', 'quantity' => 1, 'total' => '3000.00']],
            'modifiers_total' => 3000,
            'total_price' => 106000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => null,
            'product_name' => 'Osh',
            'variant_name' => null,
            'quantity' => '1.5',
            'unit' => 'kg',
            'unit_price' => 80000,
            'total_price' => 120000,
        ]);

        OrderStatusHistory::create(['order_id' => $order->id, 'status' => OrderStatus::NEW->value, 'notes' => 'created']);
        if ($status !== OrderStatus::NEW) {
            OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'new', 'status' => OrderStatus::ACCEPTED->value]);
        }

        return $order->refresh();
    }

    /**
     * A staff PIN session token of $user on $terminal.
     *
     * @param  array{device: \App\Models\Device, token: string}  $terminal
     */
    protected function pinSession(array $terminal, \App\Models\User $user, string $pin = '4831'): string
    {
        $this->givePin($user, $pin);

        return (string) $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], [
            'user_id' => $user->id,
            'pin' => $pin,
        ])->assertOk()->json('data.token');
    }
}
