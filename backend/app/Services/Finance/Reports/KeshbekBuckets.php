<?php

namespace App\Services\Finance\Reports;

use App\Support\Money;
use Illuminate\Database\Query\Builder;

/**
 * The sums of one query's sources before the report's month, in it (and how many rows) and after it, as FILTERed
 * aggregates of one SELECT: add() a source ($at a timestamp in the app's time zone, $amount the row's positive effect
 * on the balances or NULL when the row is not one of it, both SQL), select() the query, read() each source.
 *
 * @internal KeshbekLiabilityReport
 */
final class KeshbekBuckets
{
    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $bindings = [];

    public function __construct(private readonly string $start, private readonly string $end) {}

    public function add(string $name, string $at, string $amount): void
    {
        $this->columns[] = "COALESCE(SUM({$amount}) FILTER (WHERE {$at} < ?), 0) AS {$name}_before";
        $this->columns[] = "COALESCE(SUM({$amount}) FILTER (WHERE {$at} >= ? AND {$at} < ?), 0) AS {$name}_within";
        $this->columns[] = "COUNT({$amount}) FILTER (WHERE {$at} >= ? AND {$at} < ?) AS {$name}_count";
        $this->columns[] = "COALESCE(SUM({$amount}) FILTER (WHERE {$at} >= ?), 0) AS {$name}_after";
        array_push($this->bindings, $this->start, $this->start, $this->end, $this->start, $this->end, $this->end);
    }

    /**
     * @param  array<string, string>  $extra  further columns, name => SQL (no bindings)
     */
    public function select(Builder $query, array $extra = []): Builder
    {
        $columns = $this->columns;
        foreach ($extra as $name => $sql) {
            $columns[] = "{$sql} AS {$name}";
        }

        return $query->selectRaw(implode(', ', $columns), $this->bindings);
    }

    /**
     * @return array{before: Money, within: Money, count: int, after: Money}
     */
    public function read(?object $row, string $name): array
    {
        $value = fn (string $column): string => (string) ($row->{"{$name}_{$column}"} ?? '0');

        return [
            'before' => Money::of($value('before')),
            'within' => Money::of($value('within')),
            'count' => (int) $value('count'),
            'after' => Money::of($value('after')),
        ];
    }
}
