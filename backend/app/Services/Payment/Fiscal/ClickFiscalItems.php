<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalLine;
use Brick\Math\RoundingMode;

/**
 * The `items` of Click's "submit_items" call (https://docs.click.uz/merchant-api/fiscalization, "Структура Items"):
 * Name, SPIC (MXIK), PackageCode, Price (the position total in tiyin), Amount (quantity), VAT (tiyin), VATPercent
 * and CommissionInfo (the restaurant's TIN or PINFL as komitent) are required.
 */
class ClickFiscalItems
{
    /** Name is string(63) */
    public const NAME_LENGTH = 63;

    /**
     * @return list<array<string, mixed>>
     */
    public static function from(OnlineFiscalReceipt $receipt): array
    {
        return array_map(fn (FiscalLine $line) => self::item($line, $receipt->commissionerTin), $receipt->linesWithDelivery());
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(FiscalLine $line, ?string $tin): array
    {
        $price = $line->amount->toTiyin();
        // Click passes the items to the OFD receipt format, where the quantity is counted in thousandths
        // (Amount 1000 = 1 piece, 1500 = 1.5 kg); decimal(12,3) quantities convert exactly.
        $amount = $line->quantity->toBigDecimal()->multipliedBy(1000)->toScale(0, RoundingMode::HalfUp)->toInt();

        $item = [
            'Name' => mb_substr($line->name, 0, self::NAME_LENGTH),
            'SPIC' => $line->mxikCode,
            'PackageCode' => (string) $line->packageCode,
        ];
        if (($units = PaymeReceiptDetail::unitCode($line->unit)) !== null) {
            $item['Units'] = $units;
        }
        // The unit price only when it multiplies out to the position total (no discount share in it): Price is
        // what the customer paid for the line, so the positions add up to the money received.
        $goodPrice = $line->unitPrice->toTiyin();
        if ($line->quantity->toBigDecimal()->multipliedBy($goodPrice)->isEqualTo($price)) {
            $item['GoodPrice'] = $goodPrice;
        }
        $item += [
            'Price' => $price,
            'Amount' => $amount,
            'VAT' => $line->vatAmount->toTiyin(),
            'VATPercent' => (int) round((float) $line->vatRate),
        ];
        if ($line->markingCode !== null && $line->markingCode !== '') {
            $item['Labels'] = [$line->markingCode];
        }

        $commissioner = $line->commissionerTin ?? $tin;
        if ($commissioner !== null) {
            $item['CommissionInfo'] = strlen($commissioner) === 14 ? ['PINFL' => $commissioner] : ['TIN' => $commissioner];
        }

        return $item;
    }
}
