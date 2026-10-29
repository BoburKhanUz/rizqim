<?php

namespace App\Domain\Pos\Setup;

use App\Enums\KitchenStationKind;
use App\Models\Branch;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P6-11 (B10): seeds a profile's sample stations (on the branch) and menu (in the store) on the owner's request,
 * never over what exists:
 *  - a station is skipped when the branch has one of that name, or (osh, mangal, salat va bar, choy) of that kind;
 *  - a dish is skipped when the store has a product of that name; it is created switched off with a 0 price in the
 *    global category of its name (created when missing: categories are one list for every store).
 * Running it again adds nothing new.
 */
final class FormatProfileTemplateApplier
{
    /**
     * What applying would add, and what is already there.
     *
     * @return array{profile: string, stations: list<array{name: string, kind: string, kind_label: string, exists: bool}>, dishes: list<array{category: string, name: string, variants: list<string>, exists: bool}>}
     */
    public function preview(Branch $branch, FormatProfile $profile): array
    {
        $stations = $this->existingStations($branch);
        $products = $this->existingProducts((int) $branch->store_id);

        return [
            'profile' => $profile->value,
            'stations' => array_map(fn (array $station) => [
                'name' => $station['name'],
                'kind' => $station['kind']->value,
                'kind_label' => $station['kind']->label(),
                'exists' => $this->stationExists($stations, $station['name'], $station['kind']),
            ], FormatProfileTemplates::stations($profile)),
            'dishes' => array_map(fn (array $dish) => [
                'category' => $dish['category'],
                'name' => $dish['name'],
                'variants' => array_map(fn (array $variant) => $variant['name'], $dish['variants']),
                'exists' => isset($products[self::key($dish['name'])]),
            ], FormatProfileTemplates::dishes($profile)),
        ];
    }

    /**
     * @return array{stations_created: list<string>, stations_skipped: list<string>, categories_created: list<string>, dishes_created: list<string>, dishes_skipped: list<string>}
     */
    public function apply(Branch $branch, FormatProfile $profile, bool $withStations, bool $withMenu): array
    {
        return DB::transaction(function () use ($branch, $profile, $withStations, $withMenu) {
            $result = ['stations_created' => [], 'stations_skipped' => [], 'categories_created' => [], 'dishes_created' => [], 'dishes_skipped' => []];
            // One template run per branch at a time (a double click adds nothing twice)
            Branch::query()->whereKey($branch->id)->lockForUpdate()->first();

            if ($withStations) {
                $existing = $this->existingStations($branch);
                $order = (int) KitchenStation::where('branch_id', $branch->id)->max('sort_order');
                foreach (FormatProfileTemplates::stations($profile) as $station) {
                    if ($this->stationExists($existing, $station['name'], $station['kind'])) {
                        $result['stations_skipped'][] = $station['name'];
                        continue;
                    }
                    KitchenStation::create([
                        'store_id' => $branch->store_id,
                        'branch_id' => $branch->id,
                        'name' => $station['name'],
                        'kind' => $station['kind']->value,
                        'sort_order' => ++$order,
                        'is_active' => true,
                        'show_on_kds' => true,
                    ]);
                    $existing[] = ['name' => self::key($station['name']), 'kind' => $station['kind']];
                    $result['stations_created'][] = $station['name'];
                }
            }

            if ($withMenu) {
                $products = $this->existingProducts((int) $branch->store_id);
                $categories = [];
                foreach (FormatProfileTemplates::dishes($profile) as $dish) {
                    if (isset($products[self::key($dish['name'])])) {
                        $result['dishes_skipped'][] = $dish['name'];
                        continue;
                    }

                    $category = $categories[self::key($dish['category'])] ??= $this->category($dish['category'], $result['categories_created']);
                    $product = Product::create([
                        'store_id' => $branch->store_id,
                        'category_id' => $category->id,
                        'name' => $dish['name'],
                        'slug' => Str::slug($dish['name']) . '-' . Str::random(6),
                        'status' => 'inactive',
                        'type' => 'physical',
                        'has_variants' => true,
                    ]);
                    foreach ($dish['variants'] as $variant) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $variant['name'],
                            'price' => '0.00',
                            'stock' => 999,
                            'is_active' => true,
                            'unit' => $variant['unit'],
                        ]);
                    }
                    $products[self::key($dish['name'])] = true;
                    $result['dishes_created'][] = $dish['name'];
                }
            }

            return $result;
        });
    }

    /**
     * @param  list<string>  $created
     */
    private function category(string $name, array &$created): Category
    {
        $category = Category::query()->whereRaw('lower(name) = ?', [self::key($name)])->orderBy('id')->first();
        if ($category instanceof Category) {
            return $category;
        }

        $created[] = $name;

        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'is_active' => true,
        ]);
    }

    /**
     * @return list<array{name: string, kind: KitchenStationKind}>
     */
    private function existingStations(Branch $branch): array
    {
        return KitchenStation::where('branch_id', $branch->id)->get(['name', 'kind'])
            ->map(fn (KitchenStation $station) => ['name' => self::key((string) $station->name), 'kind' => $station->kind])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, kind: KitchenStationKind}>  $existing
     */
    private function stationExists(array $existing, string $name, KitchenStationKind $kind): bool
    {
        $specific = !in_array($kind, [KitchenStationKind::KITCHEN, KitchenStationKind::OTHER], true);
        foreach ($existing as $station) {
            if ($station['name'] === self::key($name) || ($specific && $station['kind'] === $kind)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true> the store's product names (lower case), switched off ones too
     */
    private function existingProducts(int $storeId): array
    {
        $names = [];
        foreach (Product::where('store_id', $storeId)->pluck('name') as $name) {
            $names[self::key((string) $name)] = true;
        }

        return $names;
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
