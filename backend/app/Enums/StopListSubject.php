<?php

namespace App\Enums;

/**
 * What a branch stop-list entry switches off: a whole product, one of its variants, or a modifier.
 */
enum StopListSubject: string
{
    case PRODUCT = 'product';
    case VARIANT = 'variant';
    case MODIFIER = 'modifier';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $subject) => $subject->value, self::cases());
    }
}
