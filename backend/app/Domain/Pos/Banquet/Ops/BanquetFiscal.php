<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\FiscalVat;
use App\Domain\Fiscal\TaxRegime;
use App\Domain\Pos\Billing\Allocation;
use App\Models\BanquetOpsSetting;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;

/**
 * P6-54 (E5): the final sale receipt's banquet lines and its prepayment part.
 *
 * The event's package, hall rent and extras are the order's `banquet` charge (orders.banquet_amount, OrderBill::CHARGES)
 * with its parts snapshotted on the order (`banquet_lines`, BanquetSettlementService). Each part is its own receipt
 * line — "Banket paketi" × the billed guests, each hall's rent, each extra — with the store's MXIK for that kind
 * (banquet_ops_settings: package, hall, extra; accountant to confirm) and the VAT of the store's regime. A bill
 * carrying part of the charge (a partial refund) shares it over the parts in proportion (largest remainder, Allocation).
 * No MXIK in fiscal mode: MXIK_MISSING (checked before any money is taken, BillFiscalizer::lines).
 *
 * The advances offset on the bill (PosTender::PREPAYMENT) are `prepayment` in the forms they were taken in, oldest
 * first (cash, card, QR; a bank transfer as card — accountant to confirm), so the ONE sale receipt shows the full event
 * total with the advances as its prepayment part.
 */
final class BanquetFiscal
{
    public const CHARGE = 'banquet';

    public const LINE = 'array{kind: string, name: string, quantity: string, unit: string, amount: string}';

    /**
     * @return list<array{amount: int, quantity: Quantity, line: Closure(Money, Quantity): FiscalLine}>
     */
    public static function parts(Store $store, Order $order, int $share): array
    {
        if ($share <= 0) {
            return [];
        }
        $lines = array_values(array_filter(self::linesOf($order), fn (array $line) => Money::of((string) ($line['amount'] ?? '0'))->isPositive()));
        if ($lines === []) {
            $lines = [['kind' => 'package', 'name' => 'Banket xizmati', 'quantity' => '1', 'unit' => 'xizmat', 'amount' => Money::fromTiyin($share)->toString()]];
        }
        $amounts = array_map(fn (array $line) => Money::of((string) $line['amount'])->toTiyin(), $lines);
        $total = array_sum($amounts);
        $shares = $share === $total ? $amounts : Allocation::proportional($share, $amounts);

        $parts = [];
        foreach ($lines as $k => $line) {
            if ($shares[$k] <= 0) {
                continue;
            }
            $quantity = Quantity::of((string) ($line['quantity'] ?? '1'));
            if ($shares[$k] !== $amounts[$k] && $amounts[$k] > 0) {
                // A share of the line: its quantity in the same proportion (at least 0.001)
                $quantity = Quantity::of(BigDecimal::of($quantity->toString())->multipliedBy($shares[$k])->dividedBy($amounts[$k], 3, RoundingMode::HalfUp));
                if (!$quantity->isPositive()) {
                    $quantity = Quantity::of('0.001');
                }
            }
            $parts[] = [
                'amount' => $shares[$k],
                'quantity' => $quantity->isPositive() ? $quantity : Quantity::of(1),
                'line' => fn (Money $money, Quantity $qty) => self::line($store, $line, $money, $qty),
            ];
        }

        return $parts;
    }

    /**
     * The order's banquet lines (orders.banquet_lines, a JSON column the Order model leaves uncast).
     *
     * @return list<array<string, mixed>>
     */
    public static function linesOf(?Order $order): array
    {
        $raw = $order?->getAttribute('banquet_lines');
        $lines = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($lines) ? array_values(array_filter($lines, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $line  LINE
     *
     * @throws FiscalException MXIK_MISSING in fiscal mode
     */
    public static function line(Store $store, array $line, Money $amount, Quantity $quantity): FiscalLine
    {
        $settings = BanquetOpsSetting::where('store_id', $store->id)->first();
        $part = match ((string) ($line['kind'] ?? 'package')) {
            'hall_rent' => 'hall',
            'extra' => 'extra',
            default => 'package',
        };
        $mxik = $settings?->getAttribute("{$part}_mxik");
        $packageCode = $settings?->getAttribute("{$part}_package_code");
        $name = mb_substr(trim((string) ($line['name'] ?? '')) ?: 'Banket xizmati', 0, 255);
        $fiscal = FiscalSettings::of($store);
        if (blank($mxik) && $fiscal->enabled()) {
            throw FiscalException::chargeWithoutMxik(self::CHARGE . ".{$part}", $name);
        }
        if ($amount->isNegative()) {
            throw FiscalException::invalid("\"{$name}\": summa manfiy bo'lishi mumkin emas.");
        }
        $quantity = $quantity->isPositive() ? $quantity : Quantity::of(1);
        $rate = TaxRegime::ofStore($store->tax_regime)->defaultVatRate();

        return new FiscalLine(
            name: $name,
            mxikCode: (string) ($mxik ?? ''),
            packageCode: filled($mxik) && filled($packageCode) ? (string) $packageCode : null,
            unit: filled($line['unit'] ?? null) ? mb_substr((string) $line['unit'], 0, 64) : 'xizmat',
            quantity: $quantity,
            unitPrice: Money::of(BigDecimal::of($amount->toString())->dividedBy($quantity->toBigDecimal(), Money::SCALE, RoundingMode::HalfUp)),
            amount: $amount,
            vatRate: $rate,
            vatAmount: FiscalVat::included($amount, $rate),
        );
    }

    /**
     * The prepayment part of a bill of an event's order in the advances' forms; null for any other order (the caller
     * keeps its own form).
     *
     * @return list<FiscalPayment>|null
     */
    public static function prepayments(OrderBill $bill, Money $amount): ?array
    {
        $order = Order::find($bill->order_id);
        if ($order === null || $order->getAttribute('booking_id') !== null) {
            return null;
        }
        $event = BanquetPrepaymentLedger::eventOf($order);
        if ($event === null) {
            return null;
        }

        return array_map(fn (array $part) => new FiscalPayment($part['form'], $part['amount'], prepayment: true), BanquetAdvances::forms($event, $amount));
    }
}
