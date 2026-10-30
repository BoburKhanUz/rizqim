<?php

namespace App\Domain\Pos\Contracts;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * P6-33 (module `kds_expo`): what the order core (KitchenService) and the pickup queue ask the expo (assembly) station.
 * Bound by KdsServiceProvider to App\Domain\Pos\Kitchen\KitchenAssembly; unbound, the core behaves as before (every
 * station ready → the order is ready).
 */
interface AssemblyTracker
{
    /**
     * Whether the order must still be assembled on the expo station before it is ready: the branch has the module and
     * an active expo station, the order is one that becomes ready for a customer or a courier (not a hall order at a
     * table) and its lines were cooked on several stations, and it was not assembled since its last line was ready.
     */
    public function awaitsAssembly(Order $order): bool;

    /**
     * A line of the order was bumped or recalled in the kitchen (inside the transaction, the order locked): an
     * assembly done before no longer holds (a line went back, or a new one came out of the kitchen).
     */
    public function kitchenChanged(Order $order, OrderItem $item): void;
}
