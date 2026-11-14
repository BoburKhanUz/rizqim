<?php

namespace App\Domain\Pos\Brands;

use App\Support\Money;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P6-55: the channel net revenue report as an Excel workbook (openspout, as VoucherExport): "Kanal va brend" — one row
 * per channel × brand, "Kanallar" and "Brendlar" — the subtotals, each with the totals row. Amounts are numbers, texts
 * string cells (a brand name is typed by the owner: never a formula).
 */
final class ChannelRevenueExport
{
    public const HEADERS = ['Kanal', 'Brend', 'Buyurtmalar', 'Yalpi tushum', 'Chegirmalar', 'Qaytarishlar', 'Komissiya', 'Komissiya %', 'Sof tushum'];

    /**
     * @param  array<string, mixed>  $report  ChannelRevenueReport::build()
     */
    public function write(array $report, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToFile($path);

        $title = "Kanal bo'yicha sof tushum: {$report['period']['from']} — {$report['period']['to']} (so'm)";
        $writer->getCurrentSheet()->setName('Kanal va brend');
        $writer->addRow(self::row([$title], $bold));
        $writer->addRow(self::row(self::HEADERS, $bold));
        foreach ($report['rows'] as $row) {
            $writer->addRow(self::row(self::line((string) $row['channel_label'], self::brand($row), $row)));
        }
        $writer->addRow(self::row(self::line('Jami', '', $report['totals'] + ['commission_percent' => null]), $bold));

        $writer->addNewSheetAndMakeItCurrent()->setName('Kanallar');
        $writer->addRow(self::row(self::HEADERS, $bold));
        foreach ($report['by_channel'] as $row) {
            $writer->addRow(self::row(self::line((string) $row['channel_label'], '', $row)));
        }
        $writer->addRow(self::row(self::line('Jami', '', $report['totals'] + ['commission_percent' => null]), $bold));

        $writer->addNewSheetAndMakeItCurrent()->setName('Brendlar');
        $writer->addRow(self::row(self::HEADERS, $bold));
        foreach ($report['by_brand'] as $row) {
            $writer->addRow(self::row(self::line('', self::brand($row), $row + ['commission_percent' => null])));
        }
        $writer->addRow(self::row(self::line('Jami', '', $report['totals'] + ['commission_percent' => null]), $bold));

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function brand(array $row): string
    {
        return $row['brand_id'] === null ? 'Asosiy brend' : trim(($row['brand_code'] ? "[{$row['brand_code']}] " : '') . (string) $row['brand_name']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<float|int|string|null>
     */
    private static function line(string $channel, string $brand, array $row): array
    {
        $number = fn (mixed $amount) => Money::of((string) $amount)->toFloat();

        return [
            $channel,
            $brand,
            (int) $row['orders'],
            $number($row['gross']),
            $number($row['discounts']),
            $number($row['refunds']),
            $number($row['commission']),
            $row['commission_percent'] !== null ? (float) $row['commission_percent'] : '',
            $number($row['net']),
        ];
    }

    /**
     * @param  list<float|int|string|null>  $values
     */
    private static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (float|int|string|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }
}
