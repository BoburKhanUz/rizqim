<?php

namespace App\Services\Admin\Export;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * P4-31: one exportable list. It takes the SAME filters as the list endpoint (rules() are the list's, without the
 * paging), builds the same query (query()), and says how a record becomes a row (row()). XlsxExporter does the rest:
 * streaming, the row cap, text-only strings, masking via ExportCells, the audit entry. Records come newest first, by
 * the key column, in pages (the keyset, so a long export never uses OFFSET).
 */
abstract class ExportDataset
{
    /** The name in the audit action (admin.exports.<key>) and the file name */
    abstract public function key(): string;

    /** The name of the data sheet */
    abstract public function sheet(): string;

    /** A readable list name for the info sheet */
    abstract public function label(): string;

    /**
     * The list's filters, validated like the list does (no paging).
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * The list's query under the filters, without the order and the limit.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<*>
     */
    abstract public function query(array $filters, User $viewer): Builder;

    /**
     * The unique column the records are ordered and paged by, newest first (table-qualified).
     */
    abstract public function keyColumn(): string;

    /**
     * Relations to load for every page (what row() reads, so a page costs a few queries, not one per row).
     *
     * @return array<int|string, mixed>
     */
    public function with(): array
    {
        return [];
    }

    /**
     * Called with each page before its rows are built: load whatever the rows need for the whole page at once.
     *
     * @param  Collection<int, Model>  $records
     */
    public function prepare(Collection $records): void {}

    /**
     * @return list<float|int|string|null>
     */
    abstract public function row(Model $record, ExportCells $cells): array;

    /**
     * The filters in words for the info sheet: label => value, only those that were given.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function describe(array $filters): array
    {
        $described = [];
        foreach ($filters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $described[$name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $described;
    }
}
