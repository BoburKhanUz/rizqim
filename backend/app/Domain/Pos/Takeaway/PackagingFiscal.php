<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\FiscalVat;
use App\Domain\Fiscal\TaxRegime;
use App\Models\Order;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;

/**
 * P6-34 (B8): the receipt lines of an order's packaging. Every packaging line is its own fiscal line with its own MXIK:
 * the rule's (snapshotted on the order), else the store's fiscal "packaging" charge code (FiscalSettings, owner's
 * settings), its package code and unit the same way; VAT of the store's regime. A bill carrying part of the packaging
 * (a split bill, a partial refund) shares that amount over the lines in proportion to their amounts (largest remainder,
 * Allocation), each line's quantity in the same proportion (at least 1). No MXIK in fiscal mode: MXIK_MISSING.
 */
final class PackagingFiscal
{
    public const CHARGE = 'packaging';

    /**
     * The receipt parts of $share tiyin of the order's packaging (BillFiscalizer::parts): one per packaging line.
     *
     * @return list<array{amount: int, quantity: Quantity, line: Closure(Money, Quantity): FiscalLine}>
     */
    public static function parts(Store $store, Order $order, int $share): array
    {
        if ($share <= 0) {
            return [];
        }
        $lines = PackagingRules::shareLines($order, $share);
        if ($lines === []) {
            // No snapshot to read (an older order): one line with the store's packaging code
            return [[
                'amount' => $share,
                'quantity' => Quantity::of(1),
                'line' => fn (Money $money, Quantity $quantity) => self::line($store, [], $money, $quantity),
            ]];
        }

        return array_map(fn (array $line) => [
            'amount' => Money::of((string) $line['amount'])->toTiyin(),
            'quantity' => Quantity::of((int) $line['quantity']),
            'line' => fn (Money $money, Quantity $quantity) => self::line($store, $line, $money, $quantity),
        ], $lines);
    }

    /**
     * One packaging receipt line: $amount for $quantity of the packaging line.
     *
     * @param  array<string, mixed>  $line  PackagingRules::LINE (name, mxik, package_code, unit)
     *
     * @throws FiscalException MXIK_MISSING in fiscal mode
     */
    public static function line(Store $store, array $line, Money $amount, Quantity $quantity): FiscalLine
    {
        $fiscal = FiscalSettings::of($store);
        $defaults = $fiscal->charge(self::CHARGE);
        $name = filled($line['name'] ?? null) ? (string) $line['name'] : $defaults['name'];
        $mxik = filled($line['mxik'] ?? null) ? (string) $line['mxik'] : $defaults['mxik'];
        if (blank($mxik) && $fiscal->enabled()) {
            throw FiscalException::chargeWithoutMxik(self::CHARGE, $name);
        }
        if ($amount->isNegative()) {
            throw FiscalException::invalid("\"{$name}\": summa manfiy bo'lishi mumkin emas.");
        }
        $packageCode = filled($line['mxik'] ?? null) ? ($line['package_code'] ?? null) : $defaults['package_code'];
        $quantity = $quantity->isPositive() ? $quantity : Quantity::of(1);
        $rate = TaxRegime::ofStore($store->tax_regime)->defaultVatRate();

        return new FiscalLine(
            name: mb_substr($name, 0, 255),
            mxikCode: (string) ($mxik ?? ''),
            packageCode: filled($mxik) && filled($packageCode) ? (string) $packageCode : null,
            unit: filled($line['unit'] ?? null) ? (string) $line['unit'] : $defaults['unit'],
            quantity: $quantity,
            unitPrice: Money::of(BigDecimal::of($amount->toString())->dividedBy($quantity->toBigDecimal(), Money::SCALE, RoundingMode::HalfUp)),
            amount: $amount,
            vatRate: $rate,
            vatAmount: FiscalVat::included($amount, $rate),
        );
    }

    /**
     * The receipt lines of an online order's packaging, in full (OnlineFiscalReceiptBuilder).
     *
     * @return list<FiscalLine>
     */
    public static function linesOf(Store $store, Order $order): array
    {
        $amount = Money::of($order->getAttribute('packaging_amount'))->toTiyin();

        return array_map(fn (array $part) => ($part['line'])(Money::fromTiyin($part['amount']), $part['quantity']), self::parts($store, $order, $amount));
    }
}
