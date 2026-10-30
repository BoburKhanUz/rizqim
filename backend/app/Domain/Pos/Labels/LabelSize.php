<?php

namespace App\Domain\Pos\Labels;

/**
 * P6-32 (B7): the label roll in the branch's label printer (branch_label_settings.size, width x height in mm). The
 * cashier app lays the sticker out for this size (pos_core `LabelSize`, 203 dpi) and the TSPL driver sends it.
 */
enum LabelSize: string
{
    case MM_40X30 = '40x30';
    case MM_58X40 = '58x40';

    public function label(): string
    {
        return match ($this) {
            self::MM_40X30 => '40 × 30 mm',
            self::MM_58X40 => '58 × 40 mm',
        };
    }

    public function widthMm(): int
    {
        return match ($this) {
            self::MM_40X30 => 40,
            self::MM_58X40 => 58,
        };
    }

    public function heightMm(): int
    {
        return match ($this) {
            self::MM_40X30 => 30,
            self::MM_58X40 => 40,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $size) => $size->value, self::cases());
    }
}
