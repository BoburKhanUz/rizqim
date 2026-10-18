<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

final class OrderTransitionResult
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly bool $changed,
        public readonly ?User $assignedCourier = null,
        public readonly bool $manualRefundRequired = false,
    ) {}
}
