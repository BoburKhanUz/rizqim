<?php

namespace App\Domain\Pos\Setup;

use App\Enums\KitchenStationKind;
use App\Enums\MenuUnit;

/**
 * P6-11 (B10): the sample kitchen stations and menu of each format profile, seeded on the owner's request "apply
 * template" (FormatTemplateApplier) — never over what the branch or store already has. Dishes are created switched
 * off with a 0 price: the owner sets the price and switches each on. MXIK codes are left empty (the fiscal check asks
 * for them before the first receipt, FiscalReadiness).
 */
final class FormatProfileTemplates
{
    /**
     * @return list<array{name: string, kind: KitchenStationKind}>
     */
    public static function stations(FormatProfile $profile): array
    {
        $station = fn (string $name, KitchenStationKind $kind) => ['name' => $name, 'kind' => $kind];

        return match ($profile) {
            FormatProfile::CHOYXONA => [
                $station('Osh va qozon', KitchenStationKind::OSH), $station('Mangal', KitchenStationKind::MANGAL),
                $station('Salat va bar', KitchenStationKind::SALAD_BAR), $station('Choy', KitchenStationKind::TEA),
            ],
            FormatProfile::OSH_MARKAZI => [$station('Osh tarqatish', KitchenStationKind::OSH)],
            FormatProfile::LAGMONXONA, FormatProfile::TOYXONA, FormatProfile::DARK_KITCHEN => [$station('Oshxona', KitchenStationKind::KITCHEN)],
            FormatProfile::KAFE, FormatProfile::RESTORAN => [$station('Oshxona', KitchenStationKind::KITCHEN), $station('Bar', KitchenStationKind::SALAD_BAR)],
            FormatProfile::KABOBXONA => [$station('Mangal', KitchenStationKind::MANGAL), $station('Oshxona', KitchenStationKind::KITCHEN), $station('Choy', KitchenStationKind::TEA)],
            FormatProfile::BALIQXONA => [$station('Mangal', KitchenStationKind::MANGAL), $station('Oshxona', KitchenStationKind::KITCHEN)],
            // P6-33: the universal kinds of KDS v2 (an expo station assembles what the others cooked)
            FormatProfile::FAST_FOOD => [
                $station('Grill', KitchenStationKind::GRILL), $station('Fritur', KitchenStationKind::FRYER),
                $station('Ichimliklar', KitchenStationKind::DRINKS), $station("Yig'ish", KitchenStationKind::EXPO),
            ],
            FormatProfile::QAHVAXONA => [$station('Barista', KitchenStationKind::BARISTA)],
            FormatProfile::SOMSAXONA, FormatProfile::TAMADDIXONA, FormatProfile::STOLOVAYA => [],
        };
    }

    /**
     * @return list<array{category: string, name: string, variants: list<array{name: string, unit: string}>}>
     */
    public static function dishes(FormatProfile $profile): array
    {
        $piece = fn (string ...$names) => array_map(fn (string $name) => ['name' => $name, 'unit' => MenuUnit::PIECE->value], $names === [] ? ['1 porsiya'] : $names);
        $kg = fn (string $name = '1 kg') => [['name' => $name, 'unit' => MenuUnit::KG->value]];
        $dish = fn (string $category, string $name, array $variants) => ['category' => $category, 'name' => $name, 'variants' => $variants];

        $tea = $dish('Ichimliklar', "Ko'k choy", $piece('Choynak'));
        $bread = $dish('Non', 'Non', $piece('Dona'));
        $salad = $dish('Salatlar', 'Achchiq-chuchuk', $piece());

        return match ($profile) {
            FormatProfile::CHOYXONA => [
                $dish('Milliy taomlar', 'Osh', $kg()), $dish('Milliy taomlar', 'Qozon kabob', $kg()),
                $dish('Mangal', 'Qiyma kabob', $piece('Six')), $dish('Mangal', 'Jigar kabob', $piece('Six')),
                $salad, $tea, $bread,
            ],
            FormatProfile::OSH_MARKAZI => [
                $dish('Osh', 'Osh', [...$piece('1 porsiya', '0,7 porsiya', '2 porsiya'), ...$kg('Olib ketish, kg')]),
                $salad, $tea, $bread,
            ],
            FormatProfile::LAGMONXONA => [
                $dish("Lag'mon", "Qovurma lag'mon", $piece()), $dish("Lag'mon", "Suyuq lag'mon", $piece()),
                $dish('Milliy taomlar', 'Manti', $piece('Dona')), $salad, $tea, $bread,
            ],
            FormatProfile::SOMSAXONA => [
                $dish('Somsa', 'Tandir somsa', $piece('Dona')), $dish('Somsa', "Go'shtli somsa", [...$piece('Dona'), ...$kg()]),
                $dish('Somsa', 'Qovoqli somsa', $piece('Dona')), $tea, $bread,
            ],
            FormatProfile::TAMADDIXONA => [
                $dish('Tamaddilar', 'Hot-dog', $piece('Dona')), $dish('Tamaddilar', 'Lavash', $piece('Dona')),
                $dish('Tamaddilar', 'Somsa', $piece('Dona')), $tea, $dish('Ichimliklar', 'Gazli ichimlik', $piece('0,5 l')),
            ],
            FormatProfile::STOLOVAYA => [
                $dish('Birinchi taomlar', "Sho'rva", $piece('1 porsiya', 'Yarim porsiya')),
                $dish('Ikkinchi taomlar', 'Osh', $piece('1 porsiya', 'Yarim porsiya')),
                $dish('Ikkinchi taomlar', 'Grechka go\'sht bilan', $piece('1 porsiya', 'Yarim porsiya')),
                $salad, $dish('Ichimliklar', 'Kompot', $piece('Stakan')), $tea, $bread,
            ],
            FormatProfile::KAFE => [
                $dish('Issiq taomlar', 'Qovurma', $piece()), $dish('Issiq taomlar', 'Manti', $piece('Dona')),
                $salad, $dish('Desertlar', 'Tort', $piece('Bo\'lak')), $tea, $dish('Ichimliklar', 'Kofe', $piece('Stakan')),
            ],
            FormatProfile::RESTORAN => [
                $dish('Salatlar', 'Sezar', $piece()), $dish('Issiq taomlar', 'Steyk', $piece()),
                $dish('Issiq taomlar', 'Baliq (grill)', $piece()), $dish('Desertlar', 'Tort', $piece('Bo\'lak')), $tea,
            ],
            FormatProfile::KABOBXONA => [
                $dish('Mangal', 'Qiyma kabob', $piece('Six')), $dish('Mangal', 'Jigar kabob', $piece('Six')),
                $dish('Mangal', "Qo'y go'shti kabob", $piece('Six')), $dish('Mangal', 'Kabob assorti', $piece()),
                $salad, $tea, $bread,
            ],
            FormatProfile::BALIQXONA => [
                $dish('Baliq', 'Sazan (mangal)', $kg()), $dish('Baliq', 'Laqqa (qovurilgan)', $kg()),
                $salad, $tea, $bread,
            ],
            FormatProfile::FAST_FOOD => [
                $dish('Burgerlar', 'Burger', $piece('Dona')), $dish('Burgerlar', 'Chizburger', $piece('Dona')),
                $dish('Lavash', 'Lavash', $piece('Oddiy', 'Katta')), $dish('Garnir', 'Kartoshka fri', $piece('Kichik', 'Katta')),
                $dish('Ichimliklar', 'Gazli ichimlik', $piece('0,5 l', '1 l')),
            ],
            FormatProfile::QAHVAXONA => [
                $dish('Qahva', 'Espresso', $piece('Stakan')), $dish('Qahva', 'Amerikano', $piece('250 ml', '350 ml')),
                $dish('Qahva', 'Kapuchino', $piece('250 ml', '350 ml')), $dish('Qahva', 'Latte', $piece('250 ml', '350 ml')),
                $dish('Desertlar', 'Kruassan', $piece('Dona')), $tea,
            ],
            FormatProfile::TOYXONA => [
                $dish('Banket', "To'y oshi", $kg()), $dish('Banket', 'Salatlar assorti', $piece('Stol')),
                $dish('Banket', 'Mevalar', $piece('Stol')), $tea, $bread,
            ],
            FormatProfile::DARK_KITCHEN => [
                $dish('Taomlar', 'Lavash', $piece('Dona')), $dish('Taomlar', 'Burger', $piece('Dona')),
                $dish('Taomlar', 'Osh', $piece()), $dish('Ichimliklar', 'Gazli ichimlik', $piece('0,5 l')),
            ],
        };
    }
}
