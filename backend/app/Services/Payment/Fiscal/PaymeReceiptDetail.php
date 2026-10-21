<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The `detail` object of Payme's CheckPerformTransaction answer, from which Payme registers the fiscal receipt
 * (https://developer.help.paycom.uz/metody-merchant-api/checkperformtransaction, "Добавление объекта detail").
 * Amounts are tiyin and add up exactly to the transaction amount: sum(price × count − discount) + shipping.price.
 */
class PaymeReceiptDetail
{
    /** receipt_type: "Продажа/Возврат = 0" */
    public const RECEIPT_TYPE_SALE = 0;

    public const SHIPPING_TITLE = 'Yetkazib berish';

    /**
     * @return array{receipt_type: int, shipping?: array{title: string, price: int}, items: list<array<string, int|float|string>>}
     */
    public static function from(OnlineFiscalReceipt $receipt): array
    {
        $detail = ['receipt_type' => self::RECEIPT_TYPE_SALE];

        if ($receipt->delivery->isPositive()) {
            $detail['shipping'] = ['title' => self::SHIPPING_TITLE, 'price' => $receipt->delivery->toTiyin()];
        }

        $detail['items'] = array_map(fn (FiscalLine $line) => self::item($line), $receipt->lines);

        return $detail;
    }

    /**
     * A whole quantity keeps its count and unit price; the line's share of the order discount, and any tiyin the unit
     * price cannot split evenly, goes to `discount`. A weighed quantity (1.5 kg) is sent as count 1 at the line amount,
     * with the quantity in the title, so no fractional count or rounding reaches the receipt.
     *
     * @return array<string, int|float|string>
     */
    public static function item(FiscalLine $line): array
    {
        $amount = $line->amount->toTiyin();
        $title = $line->name;

        if ($line->quantity->isWhole() && $line->quantity->isPositive()) {
            $count = (int) $line->quantity->toNumber();
            $price = max($line->unitPrice->toTiyin(), intdiv($amount + $count - 1, $count));
            $discount = $price * $count - $amount;
        } else {
            $count = 1;
            $price = $amount;
            $discount = 0;
            $title .= ' (' . $line->quantity->format() . ')';
        }

        $item = [];
        if ($discount > 0) {
            $item['discount'] = $discount;
        }
        $item += [
            'title' => $title,
            'price' => $price,
            'count' => $count,
            'code' => $line->mxikCode,
        ];
        if (($units = self::unitCode($line->unit)) !== null) {
            $item['units'] = $units;
        }
        $item['vat_percent'] = self::vatPercent($line->vatRate);
        $item['package_code'] = (string) $line->packageCode;

        return $item;
    }

    /**
     * The numeric unit code of the MXIK catalogue ("241092"); a unit given only as a label is left out (optional).
     */
    public static function unitCode(string $unit): ?int
    {
        return preg_match('/^\d{1,18}$/', trim($unit)) === 1 ? (int) trim($unit) : null;
    }

    /**
     * "12.00" → 12, "6.5" → 6.5.
     */
    public static function vatPercent(string $rate): int|float
    {
        $rate = BigDecimal::of(trim($rate) === '' ? '0' : trim($rate));

        return $rate->hasNonZeroFractionalPart()
            ? $rate->toScale(2, RoundingMode::HalfUp)->toFloat()
            : $rate->toScale(0)->toInt();
    }
}
