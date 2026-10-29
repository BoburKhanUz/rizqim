<?php

namespace Tests\Feature\PosMenu;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\Branch;
use App\Models\BranchProductRotation;
use App\Models\Product;
use Tests\Feature\MenuFixtures;

/**
 * P6-15: the daily menu / kompleks / write-off modules switched on by the owner's overrides on a choyxona branch, and a
 * dish with one variant (optionally in the daily rotation). Needs the test's `branch` and MenuFixtures.
 */
trait PosMenuModules
{
    use MenuFixtures;

    protected function enableMenuModules(?Branch $branch = null, array $modules = ['daily_menu' => true, 'combo_meal' => true, 'batch_writeoff' => true, 'batches' => true], FormatProfile $profile = FormatProfile::CHOYXONA): void
    {
        $branch ??= $this->branch;
        $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->pos_settings, $profile, $modules)])->save();
    }

    /**
     * A new dish with one variant, optionally already rotating in the test's `branch`.
     */
    protected function dish(string $name, string $price = '30000', bool $rotating = false, array $variant = []): Product
    {
        $product = $this->makeProduct($name);
        $this->makeVariant($product, $price, $variant + ['name' => $name . ' porsiya', 'stock' => 1000]);
        if ($rotating) {
            $this->rotate($product);
        }

        return $product;
    }

    /**
     * Puts a dish in the daily rotation of one branch (default: the test's `branch`). Rotation is per branch.
     */
    protected function rotate(Product $product, ?Branch $branch = null): void
    {
        BranchProductRotation::query()->firstOrCreate(['branch_id' => ($branch ?? $this->branch)->id, 'product_id' => $product->id]);
    }
}
