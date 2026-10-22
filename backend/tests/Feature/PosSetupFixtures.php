<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\KitchenStationKind;
use App\Models\Branch;
use App\Models\Device;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\User;

/**
 * Helpers of the P3-13 tests on top of ManagementFixtures: stations, printers, routes and a terminal PIN session.
 */
trait PosSetupFixtures
{
    use ManagementFixtures;
    use MenuFixtures;

    protected function makeStation(Branch $branch, string $name, KitchenStationKind $kind = KitchenStationKind::KITCHEN, array $attributes = []): KitchenStation
    {
        return KitchenStation::create(array_merge([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'name' => $name,
            'kind' => $kind->value,
        ], $attributes));
    }

    protected function route(Branch $branch, KitchenStation $station, ?int $categoryId = null, ?int $productId = null): KitchenStationRoute
    {
        return KitchenStationRoute::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'station_id' => $station->id,
            'category_id' => $categoryId,
            'product_id' => $productId,
        ]);
    }

    protected function makePrinter(Branch $branch, string $name, array $attributes = []): Printer
    {
        return Printer::create(array_merge([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'name' => $name,
            'purpose' => 'receipt',
            'connection' => 'lan',
            'address' => '192.168.1.50',
            'port' => 9100,
        ], $attributes));
    }

    /**
     * A staff PIN session of $user on a freshly paired terminal of the branch.
     *
     * @return array{0: string, 1: Device} the session token and the terminal
     */
    protected function terminalSession(User $user, ?Branch $branch = null, DeviceType $type = DeviceType::POS): array
    {
        $this->givePin($user, '5926');
        $terminal = $this->pairTerminal($this->owner, $branch ?? $this->branch, $type);
        $token = (string) $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $user->id, 'pin' => '5926'])
            ->assertOk()
            ->json('data.token');

        return [$token, $terminal['device']];
    }

    protected function lineOf(Branch $branch, \App\Models\ProductVariant $variant): OrderItem
    {
        $order = Order::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'user_id' => $this->customer->id,
            'status' => \App\Enums\OrderStatus::NEW,
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 0,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Taom',
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
        ]);
    }
}
