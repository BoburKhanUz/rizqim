<?php

namespace App\Enums;

enum BranchStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case TEMPORARILY_CLOSED = 'temporarily_closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::TEMPORARILY_CLOSED => 'Temporarily Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
            self::TEMPORARILY_CLOSED => 'warning',
        };
    }
}
