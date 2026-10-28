<?php

namespace App\Services\Finance\Reports;

use App\Support\Money;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P4-23: the monthly revenue report as an Excel workbook (openspout, already in the dependency tree via Filament):
 * "Umumiy" — totals, commission by channel, subscriptions; "Ovqatxonalar" — one row per store with a column per
 * channel. Amounts are numbers (so'm, two decimals) so the sheet sums them. Review W2: every text is written as a
 * string cell (self::row), never through Cell::fromValue, which turns a string starting with "=" into a formula — a
 * store's name is the owner's text (formula injection: =HYPERLINK(...) leaking the sheet on a click).
 */
class MonthlyRevenueExport
{
    /**
     * Writes the workbook to $path.
     *
     * @param  array<string, mixed>  $report  MonthlyRevenueReport::build()
     */
    public function write(array $report, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $number = fn (Money $amount) => $amount->toFloat();

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Umumiy');
        $writer->addRow(self::row(["RIZQIM daromadi — {$report['period']} ({$report['timezone']})"], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Jami daromad', $number($report['totals']['total'])], $bold));
        $writer->addRow(self::row(['Komissiya', $number($report['totals']['commission'])]));
        $writer->addRow(self::row(['Obuna', $number($report['totals']['subscription'])]));
        $writer->addRow(self::row(['Qaytarishlarda ayirilgan komissiya', $number($report['totals']['commission_refunded'])]));
        $writer->addRow(self::row(['Buyurtmalar', (int) $report['totals']['orders']]));
        $writer->addRow(self::row(['GMV', $number($report['totals']['gmv'])]));
        $writer->addRow(self::row([]));

        $writer->addRow(self::row(['Kanal', 'Buyurtmalar', 'GMV', 'Komissiya', 'Qaytarishlarda ayirilgan'], $bold));
        foreach ($report['channels'] as $channel) {
            $writer->addRow(self::row([$channel['label'], (int) $channel['orders'], $number($channel['gmv']), $number($channel['commission']), $number($channel['commission_refunded'])]));
        }
        $writer->addRow(self::row([]));

        $writer->addRow(self::row(['Obuna hisob-fakturalari', 'Soni', 'Hisoblangan', "To'langan", 'Qolgan'], $bold));
        $writer->addRow(self::row([
            $report['period'],
            (int) $report['subscriptions']['invoices'],
            $number($report['subscriptions']['invoiced']),
            $number($report['subscriptions']['paid']),
            $number($report['subscriptions']['outstanding']),
        ]));

        $writer->addNewSheetAndMakeItCurrent()->setName('Ovqatxonalar');
        $channels = array_column($report['channels'], 'label', 'channel');
        $writer->addRow(self::row(['ID', 'Ovqatxona', 'Buyurtmalar', 'GMV', ...array_values($channels), 'Komissiya', 'Obuna', 'Jami'], $bold));
        foreach ($report['stores'] as $store) {
            $byChannel = array_map(fn (string $channel) => $number($store['commission_by_channel'][$channel] ?? Money::zero()), array_keys($channels));
            $writer->addRow(self::row([
                (int) $store['store_id'],
                (string) $store['store_name'],
                (int) $store['orders'],
                $number($store['gmv']),
                ...$byChannel,
                $number($store['commission']),
                $number($store['subscription']),
                $number($store['total']),
            ]));
        }

        $writer->close();
    }

    /**
     * A row whose strings are always text (StringCell), numbers numbers.
     *
     * @param  list<float|int|string|null>  $values
     */
    private static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (float|int|string|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }
}
