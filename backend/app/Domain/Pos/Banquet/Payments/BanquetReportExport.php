<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Support\Money;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P6-53: the "Tadbirlar" report as an Excel workbook (openspout, as VoucherExport): "Tadbirlar" — one row per event
 * (the phone as the report gives it: masked for a report-only reader), "Jami" — the period totals. Amounts are
 * numbers; every text is a string cell (never a formula: a customer's name is typed by staff).
 */
final class BanquetReportExport
{
    public const HEADERS = ['Sana', 'Vaqt', 'Turi', 'Nomi', 'Mijoz', 'Telefon', 'Holat', 'Mehmonlar', 'Jami', "To'langan", 'Qolgan', "Muddati o'tgan", 'Ushlab qolingan', 'Qaytarilgan'];

    /**
     * @param  array<string, mixed>  $report  BanquetReport::build()
     */
    public function write(array $report, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $number = fn (mixed $amount) => Money::of((string) $amount)->toFloat();

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Tadbirlar');
        $writer->addRow(self::row(self::HEADERS, $bold));
        foreach ($report['rows'] as $event) {
            $writer->addRow(self::row([
                (string) $event['event_date'],
                (string) $event['slot'],
                (string) $event['event_type'],
                (string) ($event['title'] ?? ''),
                (string) $event['customer_name'],
                (string) ($event['customer_phone'] ?? ''),
                (string) $event['status_label'],
                (int) $event['guests'],
                $number($event['total']),
                $number($event['paid']),
                $number($event['outstanding']),
                $number($event['overdue']),
                $number($event['retained']),
                $number($event['refunded']),
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Jami');
        $writer->addRow(self::row(["Tadbirlar hisoboti: {$report['from']} — {$report['to']}"], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Tadbirlar', (int) $report['events']]));
        $writer->addRow(self::row(['Bekor qilingan', (int) $report['cancelled']]));
        $writer->addRow(self::row(['Mehmonlar', (int) $report['guests']]));
        $writer->addRow(self::row(['Jami summa', $number($report['total'])]));
        $writer->addRow(self::row(["To'langan", $number($report['paid'])]));
        $writer->addRow(self::row(["Qolgan to'lov", $number($report['outstanding'])]));
        $writer->addRow(self::row(["Muddati o'tgan", $number($report['overdue']['amount'])]));
        $writer->addRow(self::row(['Ushlab qolingan', $number($report['retained'])]));
        $writer->addRow(self::row(['Qaytarilgan', $number($report['refunded'])]));
        $writer->addRow(self::row(["O'tkazma bilan qaytariladi", $number($report['transfer_pending'])]));

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
