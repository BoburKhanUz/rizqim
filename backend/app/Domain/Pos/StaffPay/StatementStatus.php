<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23: a pay statement's life: draft (computed, recomputed on demand, adjustable) → closed (frozen; lines can be
 * paid; reopened to a draft while nothing is paid) → paid (every line with money to pay is paid).
 */
enum StatementStatus: string
{
    case DRAFT = 'draft';
    case CLOSED = 'closed';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Qoralama',
            self::CLOSED => 'Yopilgan',
            self::PAID => "To'langan",
        };
    }
}
