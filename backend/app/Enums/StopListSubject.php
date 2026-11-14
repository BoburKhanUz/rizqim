<?php

namespace App\Enums;

/**
 * What a branch stop-list entry switches off: a whole product, one of its variants, or a modifier. P6-55 / B9: or an
 * ingredient (module ingredient_stoplist): every dish critically linked to it closes on the branch (MenuAvailability,
 * OrderLineBuilder::stopList resolve it into products; App\Domain\Pos\Ingredients\IngredientStops).
 */
enum StopListSubject: string
{
    case PRODUCT = 'product';
    case VARIANT = 'variant';
    case MODIFIER = 'modifier';
    case INGREDIENT = 'ingredient';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $subject) => $subject->value, self::cases());
    }
}
