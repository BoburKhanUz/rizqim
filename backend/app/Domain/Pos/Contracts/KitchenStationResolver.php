<?php

namespace App\Domain\Pos\Contracts;

use App\Models\OrderItem;

/**
 * Which kitchen station (osh, mangal, salat/bar, choy) prepares a line, from the branch's routing by product or
 * category (P3-13). `null` when the branch has no stations configured: the line goes to the default ticket printer.
 */
interface KitchenStationResolver
{
    public function stationIdFor(OrderItem $item): ?int;
}
