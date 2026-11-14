<?php

namespace App\Domain\Pos\Banquet\Ops;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P6-54: the production sheet and the shopping list as Excel workbooks (openspout, as VoucherExport). Quantities are
 * numbers; every text is a string cell (never a formula).
 */
final class BanquetOpsExport
{
    public const UNITS = ['portion' => 'porsiya', 'piece' => 'dona', 'kg' => 'kg', 'l' => 'l', 'dona' => 'dona'];

    /**
     * @param  array<string, mixed>  $sheet  BanquetProduction::SHEET
     */
    public function production(array $sheet, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName("Ishlab chiqarish");
        $event = $sheet['event'];
        $writer->addRow(self::row(["Ishlab chiqarish varag'i: " . ($event['title'] ?: $event['event_type_label']) . ", {$event['event_date']} {$event['local_time']}"], $bold));
        $writer->addRow(self::row(['Zal: ' . implode(', ', $event['halls'])]));
        $guests = $sheet['guests'];
        $writer->addRow(self::row(['Mehmonlar', (int) $guests['base'], "Zaxira {$guests['reserve_percent']}%", (int) $guests['planned']]));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Sex', 'Taom', 'Kishi boshiga', 'Miqdor', 'Birlik', 'Kg'], $bold));
        foreach ($sheet['stations'] as $station) {
            foreach ($station['dishes'] as $dish) {
                $writer->addRow(self::row([
                    (string) $station['name'],
                    (string) $dish['name'],
                    $dish['quantity_per_person'] !== null ? (float) $dish['quantity_per_person'] : '',
                    (float) $dish['quantity'],
                    self::UNITS[$dish['unit']] ?? (string) $dish['unit'],
                    $dish['kg'] !== null ? (float) $dish['kg'] : '',
                ]));
            }
        }
        if ($sheet['osh'] !== null) {
            $this->osh($writer, $sheet['osh'], $bold);
        }
        foreach ($sheet['warnings'] as $warning) {
            $writer->addRow(self::row(['Ogohlantirish', (string) $warning['message']]));
        }
        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $list  BanquetProduction::SHOPPING
     */
    public function shopping(array $list, string $path): void
    {
        $bold = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Bozorlik');
        $writer->addRow(self::row(["Bozorlik ro'yxati: {$list['from']} — {$list['to']}"], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Mahsulot', 'Miqdor', 'Birlik'], $bold));
        foreach ($list['osh']['ingredients'] as $ingredient) {
            $writer->addRow(self::row([(string) $ingredient['name'], (float) $ingredient['quantity'], self::UNITS[$ingredient['unit']] ?? (string) $ingredient['unit']]));
        }
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(['Taom', 'Miqdor', 'Birlik', 'Kg'], $bold));
        foreach ($list['dishes'] as $dish) {
            $writer->addRow(self::row([(string) $dish['name'], (float) $dish['quantity'], self::UNITS[$dish['unit']] ?? (string) $dish['unit'], $dish['kg'] !== null ? (float) $dish['kg'] : '']));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Tadbirlar');
        $writer->addRow(self::row(['Sana', 'Vaqt', 'Nomi', 'Holat', 'Mehmonlar (zaxira bilan)'], $bold));
        foreach ($list['events'] as $event) {
            $writer->addRow(self::row([(string) $event['event_date'], (string) $event['slot'], (string) ($event['title'] ?? $event['event_type']), (string) $event['status'], (int) $event['planned_guests']]));
        }
        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $osh  OshCalculator::RESULT
     */
    private function osh(Writer $writer, array $osh, Style $bold): void
    {
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(["Osh: {$osh['persons']} kishi, {$osh['osh_kg']} kg"], $bold));
        $writer->addRow(self::row(['Qozonlar', implode(', ', array_map(fn (array $kazan) => "{$kazan['count']} × {$kazan['size_kg']} kg", $osh['kazans']))]));
        foreach ($osh['ingredients'] as $ingredient) {
            $writer->addRow(self::row([(string) $ingredient['name'], (float) $ingredient['quantity'], self::UNITS[$ingredient['unit']] ?? (string) $ingredient['unit']]));
        }
    }

    /**
     * @param  list<float|int|string|null>  $values
     */
    private static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (float|int|string|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }
}
