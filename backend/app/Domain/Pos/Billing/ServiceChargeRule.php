<?php

namespace App\Domain\Pos\Billing;

/**
 * P6-22 (C1): which rule set an order's service charge percent (PosCharges::$serviceChargeRule): the row's own
 * percent (`base`), the percent of the order's type (`order_type`) or the large-group rule (`large_group`); `none`:
 * no service charge (none set, the type takes none, waived, or a stolovaya's 0% lock).
 */
enum ServiceChargeRule: string
{
    case NONE = 'none';
    case BASE = 'base';
    case ORDER_TYPE = 'order_type';
    case LARGE_GROUP = 'large_group';
}
