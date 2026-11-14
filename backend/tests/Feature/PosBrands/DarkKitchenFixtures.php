<?php

namespace Tests\Feature\PosBrands;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use Tests\Feature\MenuFixtures;
use Tests\Feature\OrderLifecycleFixtures;

/**
 * P6-55: a dark kitchen store (OrderLifecycleFixtures' store with its branches on the `dark_kitchen` profile: brands
 * and the ingredient stop-list on), three brands and their dishes.
 */
trait DarkKitchenFixtures
{
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUpDarkKitchen(): void
    {
        $this->setUpLifecycleStore();
        $this->useDarkKitchen($this->branch);
        $this->useDarkKitchen($this->otherBranch);
    }

    /**
     * @param  array<string, bool>  $modules  owner overrides on top of the profile
     */
    protected function useDarkKitchen(Branch $branch, array $modules = []): void
    {
        $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->pos_settings, FormatProfile::DARK_KITCHEN, $modules)])->save();
    }

    protected function makeBrand(string $name, string $code, array $attributes = []): Brand
    {
        return Brand::create(array_merge(['store_id' => $this->store->id, 'name' => $name, 'short_code' => $code, 'color' => '#E11D48'], $attributes));
    }

    /**
     * A dish of the brand (null: the house brand) with one variant at $price.
     *
     * @return array{0: Product, 1: ProductVariant}
     */
    protected function brandDish(?Brand $brand, string $name, string $price = '40000'): array
    {
        $product = $this->makeProduct($name, ['brand_id' => $brand?->id]);
        $variant = $this->makeVariant($product, $price, ['name' => 'Porsiya', 'stock' => 1000]);

        return [$product, $variant];
    }

    protected function makeIngredient(string $name): Ingredient
    {
        return Ingredient::create(['store_id' => $this->store->id, 'name' => $name]);
    }
}
