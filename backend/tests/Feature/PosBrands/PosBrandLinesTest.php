<?php

namespace Tests\Feature\PosBrands;

use App\Models\Brand;
use App\Models\BrandChannel;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * P6-55 at the till: a line sold online and one synced from offline carry the dish's brand (the till's order and the
 * kitchen see `brand`), and the brand's channel percent prices the till's line; a house-brand line has no `brand`.
 */
class PosBrandLinesTest extends TestCase
{
    use RefreshDatabase;
    use PosQueueFixtures;

    public function test_till_lines_carry_the_brand_and_its_channel_price(): void
    {
        $this->setUpPosQueue('dark_kitchen');
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Shashlik House', 'short_code' => 'SH', 'color' => '#7C3AED']);
        $this->shashlik->product->update(['brand_id' => $brand->id]);
        BrandChannel::create(['store_id' => $this->store->id, 'brand_id' => $brand->id, 'channel' => 'pos_takeaway', 'price_percent' => '10']);
        $price = \App\Support\Money::of($this->shashlik->price);

        $order = $this->openTakeaway()->assertCreated()->json('data');
        $line = $order['items'][0];
        $this->assertSame(['id' => $brand->id, 'name' => 'Shashlik House', 'code' => 'SH', 'color' => '#7C3AED'], $line['brand']);
        $this->assertSame($price->plus($price->percent('10'))->roundTo(0)->toString(), $line['unit_price']);

        // The offline sale of a house-brand dish: no brand; once the tea is the brand's, a synced sale carries it
        $this->sync([$this->offlineOpen('T1-0001', null)])->assertOk();
        $offline = Order::where('local_number', 'T1-0001')->firstOrFail();
        $this->assertNull($offline->items()->first()->brand);
        $this->assertArrayNotHasKey('brand', app(\App\Domain\Pos\Orders\PosOrderPresenter::class)->item($offline->items()->first()));
        $this->tea->product->update(['brand_id' => $brand->id]);
        $this->sync([$this->offlineOpen('T1-0002', null)])->assertOk();
        $this->assertSame('SH', Order::where('local_number', 'T1-0002')->firstOrFail()->items()->first()->brand['code']);
    }
}
