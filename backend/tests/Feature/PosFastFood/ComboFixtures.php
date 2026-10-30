<?php

namespace Tests\Feature\PosFastFood;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\Combo;
use App\Models\ComboSlotOption;
use App\Models\ModifierGroup;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosSync\PosSyncFixtures;

/**
 * P6-31 helpers on the P3-31 till fixtures (real POS and fiscal, a paired till, the cashier's PIN session, an open
 * shift): a fast food branch (module `combo`), a lavash combo "Lavash kombo" (45 000) with three slots and a kitchen
 * routed by dish (station = 1000 + product id, faked like the other POS tests fake it).
 *
 *   Asosiy (1, buyer may change modifiers): Lavash (Katta, preset "Mol go'shtli", default) | Tovuq lavash +5 000
 *   Ichimlik (1): Pepsi 0,4 l (default, label "Ichimlik — 0,4 l Pepsi") | Fanta | Ayran +2 000
 *   Sous (0–2): Achchiq | Sarimsoqli
 */
trait ComboFixtures
{
    use PosSyncFixtures;

    protected Product $comboProduct;

    protected ProductVariant $comboVariant;

    protected ProductVariant $lavash;

    protected ProductVariant $chickenLavash;

    protected ProductVariant $pepsi;

    protected ProductVariant $fanta;

    protected ProductVariant $ayran;

    protected ProductVariant $hotSauce;

    protected ProductVariant $garlicSauce;

    protected ModifierGroup $meat;

    protected function setUpCombo(): void
    {
        $this->setUpPosSync();
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->pos_settings, FormatProfile::FAST_FOOD, [])])->save();

        $dish = fn (string $name, string $price, string $variant, string $mxik) => $this->makeVariant(
            $this->makeProduct($name, ['mxik_code' => $mxik]), $price, ['name' => $variant, 'stock' => 1000],
        );
        $this->comboVariant = $dish('Lavash kombo', '45000', 'Kombo', '10202001009000001');
        $this->comboProduct = $this->comboVariant->product;
        $this->lavash = $dish('Lavash', '32000', 'Katta', '10202001011000001');
        $this->chickenLavash = $dish('Tovuq lavash', '30000', 'Katta', '10202001012000001');
        $this->pepsi = $dish('Pepsi', '8000', '0,4 l', '10202001013000001');
        $this->fanta = $dish('Fanta', '8000', '0,4 l', '10202001014000001');
        $this->ayran = $dish('Ayran', '6000', '0,5 l', '10202001015000001');
        $this->hotSauce = $dish('Achchiq sous', '2000', 'Dona', '10202001016000001');
        $this->garlicSauce = $dish('Sarimsoqli sous', '2000', 'Dona', '10202001017000001');
        $this->meat = $this->makeGroup("Go'sht", ["Mol go'shtli" => '0', 'Pishloq' => '3000'], [], [], $this->lavash->product);

        // Every dish has its own station: the kitchen split is visible
        $this->app->instance(KitchenStationResolver::class, new class implements KitchenStationResolver
        {
            public function stationIdFor(OrderItem $item): ?int
            {
                $productId = ProductVariant::withTrashed()->whereKey($item->product_variant_id)->value('product_id');

                return $productId !== null ? 1000 + (int) $productId : null;
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function comboSlots(): array
    {
        return [
            ['name' => 'Asosiy', 'min_picks' => 1, 'max_picks' => 1, 'allow_modifiers' => true, 'options' => [
                ['variant_id' => $this->lavash->id, 'is_default' => true, 'modifiers' => [['modifier_id' => $this->modifier($this->meat, "Mol go'shtli")->id]]],
                ['variant_id' => $this->chickenLavash->id, 'price_delta' => '5000'],
            ]],
            ['name' => 'Ichimlik', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $this->pepsi->id, 'is_default' => true, 'label' => 'Ichimlik — 0,4 l Pepsi'],
                ['variant_id' => $this->fanta->id],
                ['variant_id' => $this->ayran->id, 'price_delta' => '2000'],
            ]],
            ['name' => 'Sous', 'min_picks' => 0, 'max_picks' => 2, 'options' => [
                ['variant_id' => $this->hotSauce->id],
                ['variant_id' => $this->garlicSauce->id],
            ]],
        ];
    }

    protected function saveCombo(?array $slots = null, ?Product $product = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/combos', [
            'product_id' => ($product ?? $this->comboProduct)->id,
            'slots' => $slots ?? $this->comboSlots(),
        ]);
    }

    protected function makeCombo(): Combo
    {
        $this->saveCombo()->assertCreated();

        return Combo::query()->where('product_id', $this->comboProduct->id)->firstOrFail();
    }

    /**
     * The uuid of the option of $variant in the combo.
     */
    protected function option(ProductVariant $variant): string
    {
        return (string) ComboSlotOption::query()->where('product_variant_id', $variant->id)->value('uuid');
    }

    /**
     * A combo line for a POS or Telegram order.
     *
     * @param  list<array<string, mixed>>  $picks
     * @return array<string, mixed>
     */
    protected function comboLine(array $picks = [], int $quantity = 1): array
    {
        return ['variant_id' => $this->comboVariant->uuid, 'quantity' => $quantity, 'combo' => $picks];
    }

    /**
     * POST /pos/orders as the till's cashier: a takeaway with these lines, sent to the kitchen.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function counterOrder(array $items, bool $send = true): TestResponse
    {
        return $this->tillAs('POST', '/pos/orders', [
            'branch_id' => $this->branch->id,
            'channel' => 'pos_takeaway',
            'items' => $items,
            'send' => $send,
        ]);
    }
}
