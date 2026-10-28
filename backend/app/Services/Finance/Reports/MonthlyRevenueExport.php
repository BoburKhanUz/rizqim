<?php

namespace App\Services\Finance\Reports;

use App\Support\Money;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P4-23: the monthly revenue report as an Excel workbook (openspout, already in the dependency tree via Filament):
 * "Umumiy" — totals, commission by channel, subscriptions; "Ovqatxonalar" — one row per store with a column per
 * channel. Amounts are numbers (so'm, two decimals) so the sheet sums them.
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
        $writer->addRow(Row::fromValues(["RIZQIM daromadi — {$report['period']} ({$report['timezone']})"], $bold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Jami daromad', $number($report['totals']['total'])], $bold));
        $writer->addRow(Row::fromValues(['Komissiya', $number($report['totals']['commission'])]));
        $writer->addRow(Row::fromValues(['Obuna', $number($report['totals']['subscription'])]));
        $writer->addRow(Row::fromValues(['Qaytarishlarda ayirilgan komissiya', $number($report['totals']['commission_refunded'])]));
        $writer->addRow(Row::fromValues(['Buyurtmalar', (int) $report['totals']['orders']]));
        $writer->addRow(Row::fromValues(['GMV', $number($report['totals']['gmv'])]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Kanal', 'Buyurtmalar', 'GMV', 'Komissiya', 'Qaytarishlarda ayirilgan'], $bold));
        foreach ($report['channels'] as $channel) {
            $writer->addRow(Row::fromValues([$channel['label'], (int) $channel['orders'], $number($channel['gmv']), $number($channel['commission']), $number($channel['commission_refunded'])]));
        }
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Obuna hisob-fakturalari', 'Soni', 'Hisoblangan', "To'langan", 'Qolgan'], $bold));
        $writer->addRow(Row::fromValues([
            $report['period'],
            (int) $report['subscriptions']['invoices'],
            $number($report['subscriptions']['invoiced']),
            $number($report['subscriptions']['paid']),
            $number($report['subscriptions']['outstanding']),
        ]));

        $writer->addNewSheetAndMakeItCurrent()->setName('Ovqatxonalar');
        $channels = array_column($report['channels'], 'label', 'channel');
        $writer->addRow(Row::fromValues(['ID', 'Ovqatxona', 'Buyurtmalar', 'GMV', ...array_values($channels), 'Komissiya', 'Obuna', 'Jami'], $bold));
        foreach ($report['stores'] as $store) {
            $byChannel = array_map(fn (string $channel) => $number($store['commission_by_channel'][$channel] ?? Money::zero()), array_keys($channels));
            $writer->addRow(Row::fromValues([
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
}
