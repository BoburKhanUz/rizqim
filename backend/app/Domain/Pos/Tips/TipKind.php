<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: a pos_tips row: the tip as taken with a payment, or a manager's correction of one (signed, with a reason).
 */
enum TipKind: string
{
    case TIP = 'tip';
    case CORRECTION = 'correction';
}
