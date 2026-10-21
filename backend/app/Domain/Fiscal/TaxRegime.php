<?php

namespace App\Domain\Fiscal;

/**
 * The store's tax regime (stores.tax_regime), which sets the VAT on its receipts: `vat` the standard 12% (a product
 * may carry its own rate, products.vat_rate), `vat_simplified` the optional 6% for catering (PF-100, until
 * 01.01.2030), `turnover` (aylanma solig'i) no VAT at all: rate "0", amount 0 and the line marked `no_vat`.
 */
enum TaxRegime: string
{
    case VAT = 'vat';
    case VAT_SIMPLIFIED = 'vat_simplified';
    case TURNOVER = 'turnover';

    /**
     * The regime of a store: its own, or config('fiscal.default_tax_regime') while it has not chosen one.
     */
    public static function ofStore(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::tryFrom((string) config('fiscal.default_tax_regime', 'vat')) ?? self::VAT;
    }

    /**
     * The VAT rate of a line, as FiscalLine::$vatRate holds it ("12", "6", "0").
     */
    public function defaultVatRate(): string
    {
        return match ($this) {
            self::VAT => '12',
            self::VAT_SIMPLIFIED => '6',
            self::TURNOVER => '0',
        };
    }

    public function chargesVat(): bool
    {
        return $this !== self::TURNOVER;
    }

    public function label(): string
    {
        return match ($this) {
            self::VAT => 'QQS 12%',
            self::VAT_SIMPLIFIED => 'Soddalashtirilgan QQS 6%',
            self::TURNOVER => "Aylanma solig'i (QQSsiz)",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $regime) => $regime->value, self::cases());
    }
}
