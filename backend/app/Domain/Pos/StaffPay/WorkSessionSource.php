<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23: where a work session came from: the person's own clock-in on a terminal (`pin`), or a manager in the panel.
 */
enum WorkSessionSource: string
{
    case PIN = 'pin';
    case MANUAL = 'manual';
}
