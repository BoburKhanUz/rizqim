<?php

namespace App\Enums;

enum StoreStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REJECTED = 'rejected';
    case PAYMENT_REQUIRED = 'payment_required';
    case FINANCE_HOLD = 'finance_hold';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::UNDER_REVIEW => 'Under Review',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::REJECTED => 'Rejected',
            self::PAYMENT_REQUIRED => 'Payment Required',
            self::FINANCE_HOLD => 'Finance Hold',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING, self::UNDER_REVIEW => 'warning',
            self::ACTIVE => 'success',
            self::SUSPENDED, self::REJECTED, self::FINANCE_HOLD => 'danger',
            self::PAYMENT_REQUIRED => 'info',
        };
    }
}
