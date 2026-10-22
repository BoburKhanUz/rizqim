<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

/**
 * P3-14 tests only: FiscalLineBuilder (P3-11 implements it) from an in-memory MXIK catalogue keyed by product name.
 * Like the real one, a dish or charge without MXIK is refused.
 */
class FakeOnlineFiscalLineBuilder implements FiscalLineBuilder
{
    /** @var array<string, array{mxik: string, package: ?string, unit?: string}> */
    public array $catalog = [];

    /** @var array<string, array{mxik: string, package: ?string}> */
    public array $charges = [];

    public string $vatRate = '12';

    public function forOrderItem(OrderItem $item, Quantity $quantity, Money $amount): FiscalLine
    {
        $entry = $this->catalog[$item->product_name] ?? throw new DomainException("MXIK yo'q: {$item->product_name}");

        return new FiscalLine($item->product_name, $entry['mxik'], $entry['package'], $entry['unit'] ?? '241092', $quantity,
            Money::of($item->unit_price), $amount, $this->vatRate, $this->vat($amount));
    }

    public function forCharge(Store $store, string $charge, Money $amount): FiscalLine
    {
        $entry = $this->charges[$charge] ?? throw new DomainException("MXIK yo'q: {$charge}");

        return new FiscalLine(ucfirst(str_replace('_', ' ', $charge)), $entry['mxik'], $entry['package'], '1546',
            Quantity::of(1), $amount, $amount, $this->vatRate, $this->vat($amount));
    }

    private function vat(Money $amount): Money
    {
        $rate = BigDecimal::of($this->vatRate);

        return Money::of(BigDecimal::of($amount->toString())->multipliedBy($rate)->dividedBy($rate->plus(100), 2, RoundingMode::HalfUp));
    }
}
