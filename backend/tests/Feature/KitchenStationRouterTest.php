<?php

namespace Tests\Feature;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Setup\KitchenStationRouter;
use App\Enums\KitchenStationKind;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-13: the KitchenStationResolver implementation: a product route beats the route of its category, no route means
 * no station, and only active stations of the order's own branch count.
 */
class KitchenStationRouterTest extends TestCase
{
    use RefreshDatabase;
    use PosSetupFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function resolver(): KitchenStationResolver
    {
        return app(KitchenStationResolver::class);
    }

    public function test_the_contract_is_bound_to_the_branch_routing(): void
    {
        $this->assertInstanceOf(KitchenStationRouter::class, $this->resolver());
    }

    public function test_a_line_without_any_route_has_no_station(): void
    {
        $line = $this->lineOf($this->branch, $this->variant);
        $this->assertNull($this->resolver()->stationIdFor($line));

        // A branch with stations but no route for this dish: still none (the default ticket printer)
        $this->makeStation($this->branch, 'Mangal', KitchenStationKind::MANGAL);
        $this->assertNull($this->resolver()->stationIdFor($line->fresh()));
    }

    public function test_the_category_route_applies_to_its_products(): void
    {
        $salad = $this->makeStation($this->branch, 'Salat va bar', KitchenStationKind::SALAD_BAR);
        $this->route($this->branch, $salad, categoryId: $this->product->category_id);

        $this->assertSame($salad->id, $this->resolver()->stationIdFor($this->lineOf($this->branch, $this->variant)));
    }

    public function test_a_product_route_beats_the_category_route(): void
    {
        $salad = $this->makeStation($this->branch, 'Salat va bar', KitchenStationKind::SALAD_BAR);
        $mangal = $this->makeStation($this->branch, 'Mangal', KitchenStationKind::MANGAL);
        $this->route($this->branch, $salad, categoryId: $this->product->category_id);
        $this->route($this->branch, $mangal, productId: $this->product->id);

        $this->assertSame($mangal->id, $this->resolver()->stationIdFor($this->lineOf($this->branch, $this->variant)));

        // Another product of the same category still follows the category
        $kebab = Product::create(['store_id' => $this->store->id, 'category_id' => $this->product->category_id, 'name' => 'Salat', 'slug' => 'salat-' . Str::random(5), 'status' => 'active']);
        $kebabVariant = $this->makeVariant($kebab, '30000');
        $this->assertSame($salad->id, $this->resolver()->stationIdFor($this->lineOf($this->branch, $kebabVariant)));
    }

    public function test_routes_of_another_branch_do_not_apply(): void
    {
        $other = $this->makeStation($this->otherBranch, 'Osh', KitchenStationKind::OSH);
        $this->route($this->otherBranch, $other, productId: $this->product->id);

        $this->assertNull($this->resolver()->stationIdFor($this->lineOf($this->branch, $this->variant)));
        $this->assertSame($other->id, $this->resolver()->stationIdFor($this->lineOf($this->otherBranch, $this->variant)));
    }

    public function test_an_inactive_station_is_skipped_and_the_route_change_is_seen_at_once(): void
    {
        $category = $this->makeStation($this->branch, 'Oshxona');
        $product = $this->makeStation($this->branch, 'Mangal', KitchenStationKind::MANGAL);
        $this->route($this->branch, $category, categoryId: $this->product->category_id);
        $this->route($this->branch, $product, productId: $this->product->id);
        $line = $this->lineOf($this->branch, $this->variant);

        $this->assertSame($product->id, $this->resolver()->stationIdFor($line));

        // Same resolver instance: switching the station off drops its routes (the product falls back to its category)
        $product->update(['is_active' => false]);
        $this->assertSame($category->id, $this->resolver()->stationIdFor($line));

        $category->delete();
        $this->assertNull($this->resolver()->stationIdFor($line));
    }

    public function test_a_line_whose_variant_was_deleted_since_still_routes(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh', KitchenStationKind::OSH);
        $this->route($this->branch, $osh, categoryId: $this->product->category_id);
        $line = $this->lineOf($this->branch, $this->variant);

        $this->variant->delete();

        $this->assertSame($osh->id, $this->resolver()->stationIdFor($line->fresh()));
    }

    public function test_a_line_without_a_variant_has_no_station(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh', KitchenStationKind::OSH);
        $this->route($this->branch, $osh, categoryId: $this->product->category_id);
        $line = $this->lineOf($this->branch, $this->variant);
        $line->update(['product_variant_id' => null]);

        $this->assertNull($this->resolver()->stationIdFor($line->fresh()));
        $this->assertInstanceOf(Category::class, Category::find($this->product->category_id));
    }

    public function test_a_product_or_category_has_one_route_per_branch_and_one_subject(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh');
        $this->route($this->branch, $osh, productId: $this->product->id);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->route($this->branch, $osh, productId: $this->product->id);
    }

    public function test_a_route_needs_exactly_one_subject(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->route($this->branch, $osh, categoryId: $this->product->category_id, productId: $this->product->id);
    }
}
