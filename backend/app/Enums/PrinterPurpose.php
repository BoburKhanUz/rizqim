<?php

namespace App\Enums;

/**
 * What a printer prints (printers.purpose): bills and receipts, kitchen/bar tickets, or labels.
 */
enum PrinterPurpose: string
{
    case RECEIPT = 'receipt';
    case KITCHEN = 'kitchen';
    case LABEL = 'label';

    public function label(): string
    {
        return match ($this) {
            self::RECEIPT => 'Chek va schyot',
            self::KITCHEN => 'Oshxona markasi',
            self::LABEL => 'Yorliq',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $purpose) => $purpose->value, self::cases());
    }
}
