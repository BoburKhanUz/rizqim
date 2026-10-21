<?php

namespace App\Domain\Fiscal;

use App\Enums\MenuUnit;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * FiscalLineBuilder from the menu (P3-11). An order line takes the name it was sold under (product and variant
 * snapshot), the variant's receipt unit (unit_label, else "dona", "kg", "l"), the product's MXIK and package code,
 * and the VAT of the store's regime: `vat` 12% or the product's own vat_rate, `vat_simplified` 6%, `turnover` none.
 * Its unit price is the line's price per unit before discounts (modifiers included); its amount is what the caller
 * passes (the line's or a split-bill share, after discounts). A charge takes its MXIK, name and unit from the
 * store's fiscal settings (FiscalSettings::charge). No MXIK: FiscalException::MXIK_MISSING.
 */
class MenuFiscalLineBuilder implements FiscalLineBuilder
{
    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine
    {
        $name = trim((string) $item->product_name) . (filled($item->variant_name) ? " ({$item->variant_name})" : '');
        $this->checkShare($name, $quantity, $amount);

        $variant = $item->product_variant_id !== null
            ? ProductVariant::withTrashed()->find($item->product_variant_id)
            : null;
        $product = $variant !== null ? Product::withTrashed()->find($variant->product_id) : null;
        if (!$product instanceof Product || blank($product->mxik_code)) {
            throw FiscalException::missingMxik($name, $product?->id);
        }

        $order = $item->order()->first();
        $store = Store::find($order->store_id ?? $product->store_id);
        $regime = TaxRegime::ofStore($store?->tax_regime);
        $rate = $this->rateFor($regime, $product->vat_rate);

        $unit = filled($variant->unit_label) ? (string) $variant->unit_label : ($item->unit ?? MenuUnit::PIECE)->label();

        return new FiscalLine(
            name: mb_substr($name, 0, 255),
            mxikCode: (string) $product->mxik_code,
            packageCode: filled($product->package_code) ? (string) $product->package_code : null,
            unit: $unit,
            quantity: $quantity,
            unitPrice: $this->unitPrice($item),
            amount: $amount,
            vatRate: $rate,
            vatAmount: FiscalVat::included($amount, $rate),
        );
    }

    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine
    {
        $settings = FiscalSettings::of($store)->charge($charge);
        if (blank($settings['mxik'])) {
            throw FiscalException::chargeWithoutMxik($charge, $settings['name']);
        }
        $this->checkShare($settings['name'], Quantity::of(1), $amount);

        $rate = TaxRegime::ofStore($store->tax_regime)->defaultVatRate();

        return new FiscalLine(
            name: $settings['name'],
            mxikCode: (string) $settings['mxik'],
            packageCode: $settings['package_code'],
            unit: $settings['unit'],
            quantity: Quantity::of(1),
            unitPrice: $amount,
            amount: $amount,
            vatRate: $rate,
            vatAmount: FiscalVat::included($amount, $rate),
        );
    }

    private function checkShare(string $name, Quantity $quantity, Money $amount): void
    {
        if (!$quantity->isPositive()) {
            throw FiscalException::invalid("\"{$name}\": miqdor noldan katta bo'lishi kerak.");
        }
        if ($amount->isNegative()) {
            throw FiscalException::invalid("\"{$name}\": summa manfiy bo'lishi mumkin emas.");
        }
    }

    /**
     * `vat`: the product's own rate when it has one, else 12; the other regimes ignore product rates.
     */
    private function rateFor(TaxRegime $regime, mixed $productRate): string
    {
        if ($regime === TaxRegime::VAT && $productRate !== null && $productRate !== '') {
            return FiscalVat::normalizeRate((string) $productRate);
        }

        return $regime->defaultVatRate();
    }

    /**
     * The line's total before discounts over its quantity: the menu price with the modifiers, per unit.
     */
    private function unitPrice(OrderItem $item): Money
    {
        $quantity = Quantity::of((string) ($item->getRawOriginal('quantity') ?? $item->getAttributes()['quantity'] ?? '1'));
        if (!$quantity->isPositive()) {
            return Money::of($item->unit_price);
        }

        return Money::of(BigDecimal::of(Money::of($item->total_price)->toString())
            ->dividedBy($quantity->toBigDecimal(), Money::SCALE, RoundingMode::HalfUp));
    }
}
