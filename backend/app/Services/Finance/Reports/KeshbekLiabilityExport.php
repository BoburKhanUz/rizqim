<?php

namespace App\Services\Finance\Reports;

use App\Support\Money;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The keshbek report (KeshbekLiabilityReport) as an Excel workbook, one sheet "Keshbek": the month's movements, the
 * reconciliation and the balances now. Amounts are numbers (so'm, two decimals); texts are string cells, as in
 * MonthlyRevenueExport (no text is ever written as a formula).
 */
class KeshbekLiabilityExport
{
    /**
     * @param  array<string, mixed>  $report  KeshbekLiabilityReport::build()
     */
    public function write(array $report, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $number = fn (Money $amount) => $amount->toFloat();
        $line = fn (string $label, array $source) => self::row([$label, $number($source['amount']), (int) $source['count']]);
        $reconciliation = $report['reconciliation'];
        $outstanding = $report['outstanding'];

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Keshbek');

        $writer->addRow(self::row(["Keshbek — {$report['period']} ({$report['timezone']})"], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Oy harakatlari', "So'm", 'Soni'], $bold));
        $writer->addRow($line('Berilgan bonuslar (RIZQIM)', $report['issued']));
        $writer->addRow($line('Qaytarib olingan bonuslar', $report['clawed_back']));
        $writer->addRow($line("Eski bonuslar (ovqatxona to'lagan)", $report['legacy_store_paid']));
        $writer->addRow($line('Buyurtmalarga ishlatilgan', $report['spent']));
        $writer->addRow($line('Qaytarishlarda balansga qaytgan', $report['returned']));
        $writer->addRow(self::row([]));

        $writer->addRow(self::row(['Solishtirish', "So'm"], $bold));
        $writer->addRow(self::row(['Boshlang\'ich qoldiq (harakatlardan)', $number($reconciliation['opening'])]));
        $writer->addRow(self::row(['Oy harakati', $number($reconciliation['movement'])]));
        $writer->addRow(self::row(['Yakuniy qoldiq (harakatlardan)', $number($reconciliation['closing'])], $bold));
        $writer->addRow(self::row(['Oydan keyingi harakatlar', $number($reconciliation['after_period'])]));
        $writer->addRow(self::row(['Hozir kutilgan jami', $number($reconciliation['expected_now'])]));
        $writer->addRow(self::row(['Hozirgi balanslar jami', $number($reconciliation['live'])]));
        $writer->addRow(self::row(['Farq', $number($reconciliation['difference'])], $bold));
        $writer->addRow(self::row(["Keshbek to'lovi yozuvi yo'q buyurtmalar", (int) $report['unrecorded']['keshbek_orders_without_payment']]));
        $writer->addRow(self::row([]));

        $writer->addRow(self::row(["Hozirgi holat ({$report['as_of']})", "So'm", 'Mijozlar'], $bold));
        $writer->addRow(self::row(["Mijozlar qo'lidagi keshbek (majburiyat)", $number($outstanding['positive']), (int) $outstanding['holders']]));
        $writer->addRow(self::row(['Manfiy balanslar (qarz)', $number($outstanding['negative']), (int) $outstanding['debtors']]));
        $writer->addRow(self::row(['Jami', $number($outstanding['net'])]));

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
