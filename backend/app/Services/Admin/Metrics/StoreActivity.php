<?php

namespace App\Services\Admin\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-31 Ko'rsatkichlar: which stores were active (stores.status = active) at given moments, from the status history
 * (store_status_logs): the status at t is the `to_status` of the last change by t; a store never changed before t had
 * the `from_status` of its first change after t, or its current status when it never changed. A store counts from its
 * creation until it is deleted.
 */
class StoreActivity
{
    /**
     * @param  list<CarbonImmutable>  $instants
     * @return list<array<int, true>> per instant the ids of the active stores
     */
    public function at(array $instants): array
    {
        if ($instants === []) {
            return [];
        }

        $values = [];
        $bindings = [];
        foreach ($instants as $index => $instant) {
            $values[] = '(?::integer, ?::timestamp)';
            $bindings[] = $index;
            $bindings[] = $instant->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
        }

        $rows = DB::select(
            'SELECT m.i AS i, s.id AS id FROM (VALUES ' . implode(', ', $values) . ') AS m(i, t) '
            . 'JOIN stores s ON s.created_at <= m.t AND (s.deleted_at IS NULL OR s.deleted_at > m.t) '
            . "WHERE COALESCE("
            . '(SELECT l.to_status FROM store_status_logs l WHERE l.store_id = s.id AND l.created_at <= m.t ORDER BY l.id DESC LIMIT 1), '
            . '(SELECT n.from_status FROM store_status_logs n WHERE n.store_id = s.id AND n.created_at > m.t ORDER BY n.id ASC LIMIT 1), '
            . "s.status) = 'active'",
            $bindings,
        );

        $active = array_fill(0, count($instants), []);
        foreach ($rows as $row) {
            $active[(int) $row->i][(int) $row->id] = true;
        }

        return $active;
    }
}
