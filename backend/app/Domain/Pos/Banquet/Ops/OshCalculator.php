<?php

namespace App\Domain\Pos\Banquet\Ops;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * P6-54 (E4): the osh calculator. "1 kg osh" is the rice of one kg cooked with its meat, carrot and oil, enough for
 * `persons_per_kg` guests (5–6, editable). For N persons:
 *
 *   osh kg      = N / persons_per_kg, rounded UP to the next 0.5 kg
 *   ingredient  = osh kg × its per-kg ratio (rice 1 kg, meat 1 kg, carrot 1 kg, oil 0.25 l… — the store edits them),
 *                 3 decimals, half-up
 *   kazans      = the store's kazan sizes (kg of osh one kazan cooks): the largest while the rest is at least that
 *                 big, then the smallest kazan that holds what is left (fewest kazans, least spare room).
 */
final class OshCalculator
{
    public const SETTINGS = 'array{persons_per_kg: string, product_ids: list<int>, ingredients: list<array{key: string, name: string, unit: string, per_kg: string}>, kazans: list<int>}';

    public const RESULT = 'array{persons: int, persons_per_kg: string, osh_kg: string, ingredients: list<array{key: string, name: string, unit: string, per_kg: string, quantity: string}>, kazans: list<array{size_kg: int, count: int}>, kazan_capacity_kg: string}';

    public const UNITS = ['kg', 'l', 'dona'];

    /**
     * @return array{persons_per_kg: string, product_ids: list<int>, ingredients: list<array{key: string, name: string, unit: string, per_kg: string}>, kazans: list<int>}
     */
    public static function defaults(): array
    {
        return [
            'persons_per_kg' => '5.0',
            'product_ids' => [],
            'ingredients' => [
                ['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1.000'],
                ['key' => 'meat', 'name' => "Go'sht", 'unit' => 'kg', 'per_kg' => '1.000'],
                ['key' => 'carrot', 'name' => 'Sabzi', 'unit' => 'kg', 'per_kg' => '1.000'],
                ['key' => 'oil', 'name' => "Yog'", 'unit' => 'l', 'per_kg' => '0.250'],
                ['key' => 'onion', 'name' => 'Piyoz', 'unit' => 'kg', 'per_kg' => '0.200'],
                ['key' => 'chickpea', 'name' => "No'xat", 'unit' => 'kg', 'per_kg' => '0.100'],
                ['key' => 'garlic', 'name' => 'Sarimsoq', 'unit' => 'dona', 'per_kg' => '1.000'],
                ['key' => 'salt', 'name' => 'Tuz', 'unit' => 'kg', 'per_kg' => '0.030'],
                ['key' => 'cumin', 'name' => 'Zira', 'unit' => 'kg', 'per_kg' => '0.010'],
            ],
            'kazans' => [10, 20, 30, 50],
        ];
    }

    /**
     * The stored settings read back over the defaults (missing or broken parts take the default).
     *
     * @param  array<string, mixed>|null  $stored
     * @return array{persons_per_kg: string, product_ids: list<int>, ingredients: list<array{key: string, name: string, unit: string, per_kg: string}>, kazans: list<int>}
     */
    public static function normalize(?array $stored): array
    {
        $defaults = self::defaults();
        $stored ??= [];

        $persons = is_numeric($stored['persons_per_kg'] ?? null) && (float) $stored['persons_per_kg'] > 0
            ? BigDecimal::of((string) $stored['persons_per_kg'])->toScale(1, RoundingMode::HalfUp)->__toString()
            : $defaults['persons_per_kg'];

        $ingredients = [];
        if (is_array($stored['ingredients'] ?? null)) {
            foreach ($stored['ingredients'] as $i => $ingredient) {
                if (!is_array($ingredient) || blank($ingredient['name'] ?? null) || !is_numeric($ingredient['per_kg'] ?? null)) {
                    continue;
                }
                $unit = in_array($ingredient['unit'] ?? null, self::UNITS, true) ? (string) $ingredient['unit'] : 'kg';
                $key = filled($ingredient['key'] ?? null) ? mb_substr((string) $ingredient['key'], 0, 32) : 'item' . ($i + 1);
                $ingredients[] = [
                    'key' => $key,
                    'name' => mb_substr(trim((string) $ingredient['name']), 0, 60),
                    'unit' => $unit,
                    'per_kg' => BigDecimal::of((string) $ingredient['per_kg'])->toScale(3, RoundingMode::HalfUp)->__toString(),
                ];
            }
        }

        $kazans = [];
        if (is_array($stored['kazans'] ?? null)) {
            foreach ($stored['kazans'] as $size) {
                if (is_numeric($size) && (int) $size > 0) {
                    $kazans[(int) $size] = (int) $size;
                }
            }
            sort($kazans);
        }

        $products = is_array($stored['product_ids'] ?? null) ? array_values(array_unique(array_map('intval', array_filter($stored['product_ids'], 'is_numeric')))) : [];

        return [
            'persons_per_kg' => $persons,
            'product_ids' => $products,
            'ingredients' => array_key_exists('ingredients', $stored) && is_array($stored['ingredients']) ? $ingredients : $defaults['ingredients'],
            'kazans' => $kazans !== [] ? $kazans : $defaults['kazans'],
        ];
    }

    /**
     * @param  array{persons_per_kg: string, product_ids: list<int>, ingredients: list<array{key: string, name: string, unit: string, per_kg: string}>, kazans: list<int>}  $settings
     * @return array<string, mixed> RESULT
     */
    public static function calculate(int $persons, array $settings): array
    {
        $persons = max(0, $persons);
        $perKg = BigDecimal::of($settings['persons_per_kg']);
        $kg = $persons > 0
            ? BigDecimal::of($persons)->multipliedBy(2)->dividedBy($perKg, 0, RoundingMode::Ceiling)->dividedBy(2, 1)
            : BigDecimal::zero()->toScale(1);

        $ingredients = array_map(fn (array $ingredient) => $ingredient + [
            'quantity' => $kg->multipliedBy($ingredient['per_kg'])->toScale(3, RoundingMode::HalfUp)->__toString(),
        ], $settings['ingredients']);

        $kazans = self::kazans($kg, $settings['kazans']);
        $capacity = array_sum(array_map(fn (array $kazan) => $kazan['size_kg'] * $kazan['count'], $kazans));

        return [
            'persons' => $persons,
            'persons_per_kg' => $settings['persons_per_kg'],
            'osh_kg' => $kg->__toString(),
            'ingredients' => $ingredients,
            'kazans' => $kazans,
            'kazan_capacity_kg' => BigDecimal::of($capacity)->toScale(1)->__toString(),
        ];
    }

    /**
     * @param  list<int>  $sizes  ascending
     * @return list<array{size_kg: int, count: int}> largest first
     */
    public static function kazans(BigDecimal $kg, array $sizes): array
    {
        if ($sizes === [] || !$kg->isPositive()) {
            return [];
        }
        sort($sizes);
        $largest = $sizes[count($sizes) - 1];
        $counts = [];
        $left = $kg;
        while ($left->isPositive()) {
            if ($left->isGreaterThanOrEqualTo($largest)) {
                $counts[$largest] = ($counts[$largest] ?? 0) + 1;
                $left = $left->minus($largest);
                continue;
            }
            foreach ($sizes as $size) {
                if ($left->isLessThanOrEqualTo($size)) {
                    $counts[$size] = ($counts[$size] ?? 0) + 1;
                    break;
                }
            }
            $left = BigDecimal::zero();
        }
        krsort($counts);

        return array_map(fn (int $size, int $count) => ['size_kg' => $size, 'count' => $count], array_keys($counts), array_values($counts));
    }

    /**
     * Sums the ingredient quantities of several calculations (the shopping list), by ingredient key.
     *
     * @param  list<array{key: string, name: string, unit: string, per_kg: string, quantity: string}>  $into
     * @param  list<array{key: string, name: string, unit: string, per_kg: string, quantity: string}>  $add
     * @return list<array{key: string, name: string, unit: string, per_kg: string, quantity: string}>
     */
    public static function sumIngredients(array $into, array $add): array
    {
        $byKey = [];
        foreach ($into as $ingredient) {
            $byKey[$ingredient['key']] = $ingredient;
        }
        foreach ($add as $ingredient) {
            if (isset($byKey[$ingredient['key']])) {
                $byKey[$ingredient['key']]['quantity'] = BigDecimal::of($byKey[$ingredient['key']]['quantity'])->plus($ingredient['quantity'])->toScale(3)->__toString();
            } else {
                $byKey[$ingredient['key']] = $ingredient;
            }
        }

        return array_values($byKey);
    }
}
