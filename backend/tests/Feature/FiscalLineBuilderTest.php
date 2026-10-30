<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalReadiness;
use App\Models\Product;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * P3-11: receipt lines from order lines and charges (MXIK, unit, VAT per tax regime, rounding) and "MXIK'siz
 * sotilmaydi" (FiscalReadiness).
 */
class FiscalLineBuilderTest extends TestCase
{
    use RefreshDatabase;
    use FiscalFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFiscal();
    }

    private function catchFiscal(callable $call): FiscalException
    {
        try {
            $call();
        } catch (FiscalException $e) {
            return $e;
        }
        $this->fail('Expected a FiscalException');
    }

    public function test_an_order_line_takes_its_mxik_unit_and_12_percent_vat_from_the_menu(): void
    {
        $line = $this->lines()->forOrderItem($this->orderItem('2', '50000'), Quantity::of(2), Money::of(100000));

        $this->assertSame("Osh (To'y oshi)", $line->name);
        $this->assertSame(self::OSH_MXIK, $line->mxikCode);
        $this->assertSame('1234567', $line->packageCode);
        $this->assertSame('dona', $line->unit);
        $this->assertSame('2.000', $line->quantity->toString());
        $this->assertSame('50000.00', $line->unitPrice->toString());
        $this->assertSame('100000.00', $line->amount->toString());
        $this->assertSame('12', $line->vatRate);
        $this->assertSame('10714.29', $line->vatAmount->toString()); // 100 000 × 12 / 112 = 10 714.2857…

        // A split-bill share after a discount: VAT of the share, rounded half-up to the tiyin
        $share = $this->lines()->forOrderItem($this->orderItem('2', '50000'), Quantity::of(1), Money::of('33333.33'));
        $this->assertSame('3571.43', $share->vatAmount->toString());
    }

    public function test_the_vat_rate_follows_the_tax_regime(): void
    {
        $item = $this->orderItem('2', '50000');

        // vat: the product's own rate wins over 12%
        $this->product->forceFill(['vat_rate' => '0'])->save();
        $this->assertSame(['0', '0.00'], $this->rateAndVat($item));
        $this->product->forceFill(['vat_rate' => '15.00'])->save();
        $this->assertSame(['15', '13043.48'], $this->rateAndVat($item));

        // vat_simplified: always 6%
        $this->store->forceFill(['tax_regime' => 'vat_simplified'])->save();
        $this->assertSame(['6', '5660.38'], $this->rateAndVat($item));

        // turnover: no VAT
        $this->store->forceFill(['tax_regime' => 'turnover'])->save();
        $this->assertSame(['0', '0.00'], $this->rateAndVat($item));

        // a store that has not chosen yet: the standard regime (config fiscal.default_tax_regime)
        $this->store->forceFill(['tax_regime' => null])->save();
        $this->product->forceFill(['vat_rate' => null])->save();
        $this->assertSame(['12', '10714.29'], $this->rateAndVat($item));
    }

    /**
     * @return array{string, string}
     */
    private function rateAndVat(\App\Models\OrderItem $item): array
    {
        $line = $this->lines()->forOrderItem($item, Quantity::of(2), Money::of(100000));

        return [$line->vatRate, $line->vatAmount->toString()];
    }

    public function test_a_weighed_line_uses_the_variants_receipt_unit_and_the_price_per_unit_with_modifiers(): void
    {
        $this->variant->forceFill(['unit' => 'kg', 'unit_label' => 'kg (guruch)'])->save();
        $item = $this->orderItem('1.5', '40000');
        $item->forceFill(['unit' => 'kg', 'total_price' => '63000.00'])->save(); // 1.5 kg × 40 000 + 3 000 of modifiers

        $line = $this->lines()->forOrderItem($item, Quantity::of('1.5'), Money::of(63000));

        $this->assertSame('kg (guruch)', $line->unit);
        $this->assertSame('42000.00', $line->unitPrice->toString());

        $this->variant->forceFill(['unit_label' => null])->save();
        $this->assertSame('kg', $this->lines()->forOrderItem($item, Quantity::of('1.5'), Money::of(63000))->unit);
    }

    public function test_a_line_without_mxik_is_refused_in_uzbek(): void
    {
        $item = $this->orderItem();
        $this->product->forceFill(['mxik_code' => null])->save();

        $e = $this->catchFiscal(fn () => $this->lines()->forOrderItem($item, Quantity::of(2), Money::of(100000)));
        $this->assertSame(FiscalException::MXIK_MISSING, $e->reason);
        $this->assertSame("\"Osh (To'y oshi)\" uchun MXIK kodi kiritilmagan. Fiskal rejimda MXIK'siz taom yoki xizmat sotilmaydi: menyuda MXIK kodini kiriting.", $e->getMessage());
        $this->assertSame($this->product->id, $e->context['product_id']);

        // A line whose product was deleted later still has its menu data
        $this->product->forceFill(['mxik_code' => self::OSH_MXIK])->save();
        $this->product->delete();
        $this->assertSame(self::OSH_MXIK, $this->lines()->forOrderItem($item->fresh(), Quantity::of(2), Money::of(100000))->mxikCode);

        $item->forceFill(['product_variant_id' => null])->save();
        $this->assertSame(FiscalException::MXIK_MISSING, $this->catchFiscal(fn () => $this->lines()->forOrderItem($item->fresh(), Quantity::of(2), Money::of(100000)))->reason);
    }

    public function test_charges_take_their_codes_from_the_store_settings(): void
    {
        $deposit = $this->lines()->forCharge($this->store, 'deposit', Money::of(1000000));
        $this->assertSame(['Avans (bron)', '10202002004000001', 'xizmat', '1.000', '1000000.00', '12', '107142.86'], [
            $deposit->name, $deposit->mxikCode, $deposit->unit, $deposit->quantity->toString(), $deposit->unitPrice->toString(), $deposit->vatRate, $deposit->vatAmount->toString(),
        ]);
        $this->assertSame('10202002003000002', $this->lines()->forCharge($this->store, 'booking_fee', Money::of(50000))->mxikCode);

        // No published code for a room fee: the owner sets it, until then it cannot be put on a receipt
        $e = $this->catchFiscal(fn () => $this->lines()->forCharge($this->store, 'room_fee', Money::of(300000)));
        $this->assertSame([FiscalException::MXIK_MISSING, 'room_fee'], [$e->reason, $e->context['charge']]);
        $this->assertStringContainsString('"Xona puli" uchun MXIK kodi sozlanmagan', $e->getMessage());

        $this->fiscalMode($this->store, ['charges' => ['room_fee' => ['mxik' => self::ROOM_MXIK, 'package_code' => '1500', 'name' => 'Kabina puli', 'unit' => 'soat']]], 'vat_simplified');
        $room = $this->lines()->forCharge($this->store->fresh(), 'room_fee', Money::of(300000));
        $this->assertSame(['Kabina puli', self::ROOM_MXIK, '1500', 'soat', '6', '16981.13'], [$room->name, $room->mxikCode, $room->packageCode, $room->unit, $room->vatRate, $room->vatAmount->toString()]);

        // Delivery of an online order (P3-14): no published code, so the store sets it like the room fee
        $this->assertSame('delivery', $this->catchFiscal(fn () => $this->lines()->forCharge($this->store, 'delivery', Money::of(15000)))->context['charge']);
        $this->fiscalMode($this->store, ['charges' => ['delivery' => ['mxik' => '10202002002000001', 'unit' => 'xizmat']]]);
        $delivery = $this->lines()->forCharge($this->store->fresh(), 'delivery', Money::of(15000));
        $this->assertSame(['Yetkazib berish', '10202002002000001', 'xizmat', '12', '1607.14'], [$delivery->name, $delivery->mxikCode, $delivery->unit, $delivery->vatRate, $delivery->vatAmount->toString()]);

        $this->assertSame(FiscalException::UNKNOWN_CHARGE, $this->catchFiscal(fn () => $this->lines()->forCharge($this->store, 'tips', Money::of(1)))->reason);
    }

    public function test_a_fiscal_store_sells_nothing_without_mxik(): void
    {
        $readiness = app(FiscalReadiness::class);
        $noCode = Product::create([
            'store_id' => $this->store->id, 'category_id' => $this->product->category_id, 'name' => 'Qozon ijarasi',
            'slug' => 'qozon-' . uniqid(), 'status' => 'active', 'kind' => 'service',
        ]);
        $badCode = Product::create([
            'store_id' => $this->store->id, 'category_id' => $this->product->category_id, 'name' => 'Non',
            'slug' => 'non-' . uniqid(), 'status' => 'active', 'mxik_code' => '1020200',
        ]);

        $readiness->assertSellable($this->product); // has a code
        $e = $this->catchFiscal(fn () => $readiness->assertSellable($noCode));
        $this->assertSame(['mxik_missing', $noCode->id, 'Qozon ijarasi'], [$e->reason, $e->context['product_id'], $e->context['name']]);
        $this->catchFiscal(fn () => $readiness->assertSellable($badCode));

        $this->assertSame([$noCode->id, $badCode->id], array_column($readiness->missingMxik($this->store), 'id'));
        $this->assertSame('service', $readiness->missingMxik($this->store)[0]['kind']);
        $this->assertSame(['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'delivery', 'packaging'], $readiness->chargesWithoutMxik($this->store));

        // In an API request the refusal is a 422 in the standard error shape
        $response = $e->render(Request::create('/api/v1/pos/orders', 'POST'));
        $this->assertSame(422, $response?->getStatusCode());
        $this->assertSame(['success' => false, 'message' => $e->getMessage(), 'data' => ['reason' => 'mxik_missing', 'product_id' => $noCode->id, 'name' => 'Qozon ijarasi']], $response?->getData(true));

        // Outside fiscal mode anything may be sold
        $this->store->forceFill(['fiscal_settings' => ['enabled' => false]])->save();
        $readiness->assertSellable($noCode->fresh());
        $this->assertFalse($readiness->enabled($this->store));
    }
}
