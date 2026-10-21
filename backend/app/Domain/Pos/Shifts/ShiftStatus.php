<?php

namespace App\Domain\Pos\Shifts;

enum ShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
