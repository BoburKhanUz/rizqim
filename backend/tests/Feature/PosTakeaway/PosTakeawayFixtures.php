<?php

namespace Tests\Feature\PosTakeaway;

use App\Models\BranchPackagingRule;
use App\Models\Category;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosQueue\PosQueueFixtures;

/**
 * P6-34 helpers: the P6-13 fast food branch (two tills, order numbers and calling; the fast_food profile also has the
 * packaging and scheduled_pickup modules) with a soup in its own category, the store's fiscal packaging code, a bag per
 * takeaway (1 000) and a bowl per soup portion (3 000).
 */
trait PosTakeawayFixtures
{
    use PosQueueFixtures;

    protected ProductVariant $soup;

    protected Category $soups;

    protected BranchPackagingRule $bag;

    protected BranchPackagingRule $bowl;

    protected const PACKAGING_MXIK = '10202002009000001';

    protected const BOWL_MXIK = '03924000001000001';

    protected function setUpPosTakeaway(string $profile = 'fast_food'): void
    {
        $this->setUpPosQueue($profile);

        $settings = $this->store->fiscal_settings;
        $settings['charges']['packaging'] = ['mxik' => self::PACKAGING_MXIK, 'package_code' => '1500001'];
        $this->store->forceFill(['fiscal_settings' => $settings])->save();

        $this->soups = Category::create(['name' => "Sho'rvalar", 'slug' => 'shorva-' . Str::random(5), 'is_active' => true]);
        $this->soup = $this->makeVariant($this->makeProduct("Sho'rva", ['category_id' => $this->soups->id, 'mxik_code' => '10202001009000001']), '25000', ['name' => 'Kosa', 'stock' => 1000]);

        $this->bag = BranchPackagingRule::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Paket', 'basis' => 'order',
            'quantity' => 1, 'unit_price' => '1000', 'unit' => 'dona', 'order_types' => ['takeaway', 'pickup', 'delivery'], 'sort_order' => 1,
        ]);
        $this->bowl = BranchPackagingRule::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => "Sho'rva idishi", 'basis' => 'item',
            'category_id' => $this->soups->id, 'quantity' => 1, 'unit_price' => '3000', 'mxik' => self::BOWL_MXIK, 'package_code' => '2500002',
            'unit' => 'dona', 'order_types' => ['takeaway', 'pickup'], 'sort_order' => 2,
        ]);
    }

    /**
     * A takeaway at the till with $items ([variant, quantity]), not sent unless asked.
     *
     * @param  list<array{0: ProductVariant, 1: int|string}>  $items
     * @param  array<string, mixed>  $extra
     */
    protected function takeaway(array $items, array $extra = [], string $channel = 'pos_takeaway'): TestResponse
    {
        return $this->tillAs('POST', '/pos/orders', $extra + [
            'channel' => $channel,
            'items' => array_map(fn (array $item) => ['variant_id' => $item[0]->uuid, 'quantity' => $item[1]], $items),
        ]);
    }

    /**
     * The order's single bill, paid in cash at its total. Returns the bill.
     *
     * @return array<string, mixed>
     */
    protected function payAll(string $uuid, int $version): array
    {
        $bill = $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $version, 'mode' => 'single'])->assertSuccessful()->json('data.bills.0');
        $this->tillAs('POST', '/pos/bills/' . $bill['uuid'] . '/payments', [
            'shift_uuid' => $this->shift->uuid,
            'tenders' => [['tender' => 'cash', 'amount' => $bill['total']]],
        ])->assertSuccessful();

        return $bill;
    }

    /**
     * The kitchen display's tickets as the cook sees them.
     *
     * @return list<array<string, mixed>>
     */
    protected function kitchenTickets(): array
    {
        $this->withBearer(null);
        $tickets = (array) $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data');
        $this->app['auth']->forgetGuards();

        return $tickets;
    }
}
