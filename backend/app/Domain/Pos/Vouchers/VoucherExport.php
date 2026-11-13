<?php

namespace App\Domain\Pos\Vouchers;

use App\Models\Voucher;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P6-43: the certificates as an Excel workbook (openspout, as App\Services\Finance\Reports\MonthlyRevenueExport):
 * "Sertifikatlar" — one row per certificate (the buyer's phone masked; the code masked with `$maskCodes`, review S1), "Hisobot" — the period report (VoucherReport).
 * Amounts are numbers; every text is a string cell (never a formula: a buyer's name is typed by a cashier).
 */
final class VoucherExport
{
    public const HEADERS = ['Kod', 'Holat', 'Qiymat', 'Qoldiq', 'Sotilgan', 'Filial', "To'lov turi", 'Amal qiladi', 'Xaridor', 'Telefon', 'Izoh'];

    /**
     * @param  Collection<int, Voucher>  $vouchers
     * @param  array<string, mixed>  $report  VoucherReport::build()
     * @param  array<int, string>  $branches  id => name
     */
    public function write(Collection $vouchers, array $report, array $branches, string $path, bool $maskCodes = false): void
    {
        $bold = (new Style())->setFontBold();
        $number = fn (mixed $amount) => Money::of((string) $amount)->toFloat();

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Sertifikatlar');
        $writer->addRow(self::row(self::HEADERS, $bold));
        foreach ($vouchers as $voucher) {
            $writer->addRow(self::row([
                $maskCodes ? VoucherCode::mask((string) $voucher->code) : (string) $voucher->code,
                VoucherState::of($voucher)->label(),
                $number($voucher->face_value),
                $number($voucher->balance),
                $voucher->sold_at?->format('d.m.Y H:i') ?? '',
                $voucher->branch_id !== null ? ($branches[(int) $voucher->branch_id] ?? '') : '',
                (string) $voucher->sale_tender,
                $voucher->valid_until?->format('d.m.Y') ?? '',
                (string) ($voucher->buyer_name ?? ''),
                (string) (AuditRedactor::maskPhone($voucher->buyer_phone) ?? ''),
                (string) ($voucher->note ?? ''),
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Hisobot');
        $writer->addRow(self::row(["Sertifikatlar hisoboti: {$report['from']} — {$report['to']}"], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['', 'Soni', 'Summa'], $bold));
        $writer->addRow(self::row(['Sotildi (avans)', (int) $report['sold']['count'], $number($report['sold']['amount'])]));
        foreach ($report['sold']['by_tender'] as $row) {
            $writer->addRow(self::row(['  ' . $row['tender'], (int) $row['count'], $number($row['amount'])]));
        }
        $writer->addRow(self::row(["To'lovda ishlatildi", (int) $report['redeemed']['count'], $number($report['redeemed']['amount'])]));
        $writer->addRow(self::row(['Qaytarishdan qaytdi', (int) $report['refunded']['count'], $number($report['refunded']['amount'])]));
        $writer->addRow(self::row(["Davrdagi o'zgarish", '', $number($report['net_change'])]));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(["Qolgan majburiyat ({$report['as_of']})", (int) $report['outstanding']['count'], $number($report['outstanding']['amount'])], $bold));
        $writer->addRow(self::row(['Bloklangan', (int) $report['blocked']['count'], $number($report['blocked']['amount'])]));
        $writer->addRow(self::row(["Muddati o'tgan", (int) $report['expired']['count'], $number($report['expired']['amount'])]));

        $writer->close();
    }

    /**
     * @param  list<float|int|string|null>  $values
     */
    private static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (float|int|string|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }
}
