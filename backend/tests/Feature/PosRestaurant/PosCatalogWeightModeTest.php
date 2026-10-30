<?php

namespace Tests\Feature\PosRestaurant;

use App\Domain\Pos\Setup\FormatProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6 wave 2 review C6: a dish's weight rule (`weight_mode`) reaches the till only while the branch's `weight_later`
 * module is on; off, the POS catalog carries null (the till sells it by the quantity typed, offline too), as the
 * server does (PosWeights).
 */
class PosCatalogWeightModeTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant(FormatProfile::BALIQXONA);
    }

    private function fishRow(): array
    {
        $catalog = $this->pos($this->cashier, 'GET', '/pos/catalog')->assertOk()->json('data.products');

        return collect($catalog)->firstWhere('id', $this->fish->product_id);
    }

    public function test_the_weight_rule_is_in_the_pos_catalog_only_with_the_module(): void
    {
        $this->assertSame('raw', $this->fishRow()['weight_mode']);

        $this->profile(FormatProfile::BALIQXONA, ['weight_later' => false]);
        $row = $this->fishRow();
        $this->assertArrayHasKey('weight_mode', $row);
        $this->assertNull($row['weight_mode']);

        // The counter's menu too
        $products = $this->pos($this->cashier, 'GET', '/pos/catalog?channel=pos_takeaway')->assertOk()->json('data.products');
        $this->assertNull(collect($products)->firstWhere('id', $this->fish->product_id)['weight_mode']);

        $this->profile(FormatProfile::BALIQXONA, ['weight_later' => true]);
        $this->assertSame('raw', $this->fishRow()['weight_mode']);
    }
}
