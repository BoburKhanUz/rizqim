<?php

namespace App\Enums;

/**
 * When a POS line goes to the kitchen (order_items.fire_mode, P3-12, docs/POS_SPEC.md §2.6): with the next "send"
 * (`now`), or only when it is sent by name (`hold`: a later course, a pre-order with its due time).
 */
enum FireMode: string
{
    case NOW = 'now';
    case HOLD = 'hold';
}
