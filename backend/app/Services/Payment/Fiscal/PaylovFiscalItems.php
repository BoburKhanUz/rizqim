<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalLine;
use Brick\Math\BigDecimal;

/**
 * Wave P: the `items` of Paylov's fiscalization/register (developer.paylov.uz/subscribe/ofd/register, "Item Object"):
 * title, price ("before discounts, expressed in tiyn"), discount ("cannot exceed the price"), count (at least 1), code
 * (the MXIK / IKPU), vat_percent (integer), package_code, and pinfl or tin, never both (the restaurant as komitent).
 *
 * The page does not say whether `price` is per unit or for the whole position, only that the items' total must equal
 * the transaction ("invalid_amount: The total price of items does not match the transaction amount"). Its fields are
 * Payme's receipt `detail` items one for one (subscribe/ofd/register vs Payme's CheckPerformTransaction), so they are
 * read the same way: price per unit, the line is price × count − discount, a weighed quantity is count 1 at the line
 * amount (PaymeReceiptDetail::item). Paylov has no `shipping`: the delivery fee is a line with its own MXIK, as Click's.
 */
class PaylovFiscalItems
{
    /**
     * @return array{items: list<array<string, int|string>>, problems: list<string>}
     */
    public static function from(OnlineFiscalReceipt $receipt): array
    {
        $items = [];
        $problems = [];
        foreach ($receipt->linesWithDelivery() as $line) {
            $vat = BigDecimal::of(trim($line->vatRate) === '' ? '0' : trim($line->vatRate));
            if ($vat->hasNonZeroFractionalPart()) {
                // vat_percent is an integer on Paylov's page: a 6.5% line cannot be sent as it is
                $problems[] = 'paylov_vat_not_whole';
            }
            $items[] = self::item($line, $receipt->commissionerTin, $vat->toScale(0, \Brick\Math\RoundingMode::Down)->toInt());
        }

        return ['items' => $items, 'problems' => array_values(array_unique($problems))];
    }

    /**
     * @return array<string, int|string>
     */
    public static function item(FiscalLine $line, ?string $tin, int $vatPercent): array
    {
        $payme = PaymeReceiptDetail::item($line);

        $item = [
            'title' => (string) $payme['title'],
            'price' => (int) $payme['price'],
            'discount' => (int) ($payme['discount'] ?? 0),
            'count' => (int) $payme['count'],
            'code' => (string) $payme['code'],
            'vat_percent' => $vatPercent,
            'package_code' => (string) $payme['package_code'],
        ];

        // "pinfl or tin must be provided, but not both": 14 digits a sole proprietor's PINFL, 9 a company's TIN
        $commissioner = $line->commissionerTin ?? $tin;
        if ($commissioner !== null) {
            $item[strlen($commissioner) === 14 ? 'pinfl' : 'tin'] = $commissioner;
        }

        return $item;
    }
}
