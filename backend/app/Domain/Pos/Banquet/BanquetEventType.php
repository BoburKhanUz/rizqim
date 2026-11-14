<?php

namespace App\Domain\Pos\Banquet;

enum BanquetEventType: string
{
    case TOY = 'toy';
    case OSH = 'osh';
    case BIRTHDAY = 'birthday';
    case CORPORATE = 'corporate';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TOY => "To'y",
            self::OSH => 'Osh (nahor oshi)',
            self::BIRTHDAY => "Tug'ilgan kun",
            self::CORPORATE => 'Korporativ',
            self::OTHER => 'Boshqa',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
