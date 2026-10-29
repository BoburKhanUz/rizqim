<?php

namespace App\Services\Admin\Export;

use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPii;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditRedactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * P4-31: the one place that writes a list of the Boshqaruv markazi as an Excel workbook (openspout). Every export:
 *
 *  - streams: the records come in pages by the key column and go straight to the file, so memory does not grow with
 *    the list;
 *  - is capped: at config('admin.export.max_rows') rows (50 000); the file says so (a note under the data and on the
 *    info sheet) when the list was longer;
 *  - writes every text as a string cell, never through Cell::fromValue, which would turn "=HYPERLINK(...)" typed into
 *    a store name into a formula (MonthlyRevenueExport, review W2); amounts and counts are numbers;
 *  - shows phones as ExportCells says (masked unless admin.pii.view) and records ONE reveal when it showed any;
 *  - is audited (admin.exports.<list>): who, which list, the filters, the row count, whether it was cut.
 *
 * Sheet 1 is the data (the headers in bold), sheet 2 "Ma'lumot" says what the file is.
 */
class XlsxExporter
{
    private const PAGE = 1000;

    public const INFO_SHEET = "Ma'lumot";

    public function __construct(
        private readonly AdminPii $pii,
        private readonly AuditLogger $audit,
    ) {}

    public static function maxRows(): int
    {
        return max(1, (int) config('admin.export.max_rows', 50000));
    }

    /**
     * @param  array<string, mixed>  $filters  validated by the dataset's rules()
     */
    public function export(ExportDataset $dataset, array $filters, User $viewer, Request $request): ExportResult
    {
        // A big file takes longer than a web request's usual limit. Not in the console (the test suite is one PHP process: the
        // limit would stay armed for everything that runs after the export)
        if (!app()->runningInConsole()) {
            @set_time_limit(300);
        }

        $path = tempnam(sys_get_temp_dir(), 'rizqim-export-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }

        try {
            return $this->write($dataset, $filters, $viewer, $request, $path);
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function write(ExportDataset $dataset, array $filters, User $viewer, Request $request, string $path): ExportResult
    {
        $cells = new ExportCells($this->pii, $viewer);
        $bold = (new Style())->setFontBold();
        $cap = self::maxRows();
        $key = $dataset->keyColumn();
        $attribute = Str::afterLast($key, '.');
        $query = $dataset->query($filters, $viewer);

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($dataset->sheet());
        $writer->addRow(self::row($dataset->headers(), $bold));

        $written = 0;
        $truncated = false;
        $after = null;
        while (true) {
            $limit = min(self::PAGE, $cap - $written + 1);
            /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $page */
            $page = (clone $query)
                ->when($after !== null, fn ($builder) => $builder->where($key, '<', $after))
                ->orderByDesc($key)
                ->limit($limit)
                ->with($dataset->with())
                ->get();
            if ($page->isEmpty()) {
                break;
            }

            $room = $cap - $written;
            $truncated = $page->count() > $room;
            $records = $page->take($room)->values();
            $dataset->prepare($records);
            foreach ($records as $record) {
                $writer->addRow(self::row($dataset->row($record, $cells)));
            }
            $written += $records->count();

            if ($truncated || $page->count() < $limit) {
                break;
            }
            $after = $page->last()->getAttribute($attribute);
        }

        $note = "Ro'yxat " . number_format($cap, 0, '.', ' ') . " qatordan keyin kesildi: filtrni toraytiring va qayta eksport qiling.";
        if ($truncated) {
            $writer->addRow(self::row([]));
            $writer->addRow(self::row([$note], $bold));
        }

        $now = Carbon::now(ExportCells::TIMEZONE);
        $this->writeInfo($writer, $dataset, $filters, $viewer, $cells, $now, $written, $truncated ? $note : null);
        $writer->close();

        $this->record($dataset, $filters, $viewer, $request, $written, $truncated, $cells, $now);

        return new ExportResult(
            $path,
            'rizqim-' . $dataset->key() . '-' . $now->format('Ymd-Hi') . '.xlsx',
            $written,
            $truncated,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function writeInfo(Writer $writer, ExportDataset $dataset, array $filters, User $viewer, ExportCells $cells, Carbon $now, int $rows, ?string $note): void
    {
        $bold = (new Style())->setFontBold();
        $writer->addNewSheetAndMakeItCurrent()->setName(self::INFO_SHEET);
        $writer->addRow(self::row(['RIZQIM boshqaruv markazi — eksport'], $bold));
        $writer->addRow(self::row([]));
        $writer->addRow(self::row(["Ro'yxat", $dataset->label()]));
        $writer->addRow(self::row(['Eksport vaqti', $now->format('Y-m-d H:i:s') . ' (' . ExportCells::TIMEZONE . ')']));
        $writer->addRow(self::row(['Eksport qilgan', (string) $viewer->name]));
        $writer->addRow(self::row(['Qatorlar soni', $rows]));
        $writer->addRow(self::row(['Telefon raqamlari', $cells->revealedPersonalData() ? "To'liq (ruxsat bor)" : "Yashirilgan (to'liq ko'rish ruxsati yo'q yoki raqam yo'q)"]));
        $writer->addRow(self::row(['Karta raqamlari', 'Hamisha yashirilgan']));
        $writer->addRow(self::row([]));

        $described = $dataset->describe($filters);
        $writer->addRow(self::row(['Filtrlar'], $bold));
        if ($described === []) {
            $writer->addRow(self::row(['Filtrsiz: butun ro\'yxat']));
        }
        foreach ($described as $name => $value) {
            $writer->addRow(self::row([(string) $name, $value]));
        }

        if ($note !== null) {
            $writer->addRow(self::row([]));
            $writer->addRow(self::row([$note], $bold));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function record(ExportDataset $dataset, array $filters, User $viewer, Request $request, int $rows, bool $truncated, ExportCells $cells, Carbon $now): void
    {
        $this->audit->record('admin.exports.' . $dataset->key(), null, [], [], [
            'list' => $dataset->key(),
            'filters' => array_map(fn (string $value) => $this->withoutPhones($value), $dataset->describe($filters)),
            'rows' => $rows,
            'truncated' => $truncated,
            'phones_shown' => $cells->revealedPersonalData(),
        ], [
            'actor' => $viewer,
            'device' => null,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        if ($cells->revealedPersonalData()) {
            // One entry for the whole file (RecordAdminAudit writes admin.pii.revealed), not one per row
            AdminAudit::for($request)->revealed('export:' . $dataset->key(), null, 'phone');
        }
    }

    /**
     * A search text may be a phone number typed in full ("+998901234567" is a number to the audit redactor): it is kept
     * out of the log, which only masks phones it recognises as text.
     */
    private function withoutPhones(string $value): string
    {
        return (string) preg_replace_callback('/\+?\d[\d\s\-]{6,}\d/', fn (array $match) => (string) AuditRedactor::maskPhone($match[0]), $value);
    }

    /**
     * A row whose strings are always text (StringCell), numbers numbers.
     *
     * @param  list<float|int|string|null>  $values
     */
    public static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn (float|int|string|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value),
            $values,
        ), $style);
    }
}
