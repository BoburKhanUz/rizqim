<?php

namespace App\Services\Admin\Pos;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * P4-24: the page envelope every POS va obunalar list answers with (`page`, `per_page` 1-100, default 30), like the
 * Ovqatxonalar list.
 */
final class PosPage
{
    public const SHAPE = 'current_page: int, last_page: int, per_page: int, total: int';

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function of(LengthAwarePaginator $page, array $rows, array $extra = []): array
    {
        return [
            'data' => $rows,
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            ...$extra,
        ];
    }

    public static function perPage(mixed $value): int
    {
        return max(1, min(100, (int) ($value ?: 30)));
    }
}
