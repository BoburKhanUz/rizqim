<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: where a tip came from: an amount on top of the tender (`tender`), or the change the guest left (`change`).
 */
enum TipSource: string
{
    case TENDER = 'tender';
    case CHANGE = 'change';
}
