<?php

namespace App\Domain\Realtime;

use App\Domain\Realtime\Http\CachedChannelAuth;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Wave M2: the query builder of the application's Postgres connection (RevisionPostgresConnection). Every write to a
 * table that RevisionMap tracks resolves the scopes of the rows it writes and touches them (after commit), whether it
 * comes from an Eloquent save, a mass update, a relation, a pivot sync or DB::table(). This is the one place the
 * touches happen, so a new code path cannot forget them.
 *
 * Inserts resolve from the inserted values. Updates and deletes read the affected rows' scope columns first (one
 * small select), except for an Eloquent single-row write whose model event already announced the row.
 */
class RevisionQueryBuilder extends Builder
{
    public function insert(array $values)
    {
        $this->forgetInsertedGrants($values);
        $result = parent::insert($values);
        $this->touchInserted($values);

        return $result;
    }

    public function insertOrIgnore(array $values)
    {
        $this->forgetInsertedGrants($values);
        $result = parent::insertOrIgnore($values);
        $this->touchInserted($values);

        return $result;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->forgetInsertedGrants($values);
        $id = parent::insertGetId($values, $sequence);
        $this->touchInserted($values); // a rule column left out of an insert takes its default: null

        return $id;
    }

    public function insertUsing(array $columns, $query)
    {
        $result = parent::insertUsing($columns, $query);
        $this->touchUnknown();

        return $result;
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        $result = parent::insertOrIgnoreUsing($columns, $query);
        $this->touchUnknown();

        return $result;
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null)
    {
        if ($update === []) {
            return parent::upsert($values, $uniqueBy, $update); // a plain insert(), resolved there
        }

        $this->forgetInsertedGrants($values);
        $result = parent::upsert($values, $uniqueBy, $update);
        $this->touchInserted($values, (array) $uniqueBy);

        return $result;
    }

    public function update(array $values)
    {
        $this->forgetChannelGrants($values);
        $narrow = $this->narrowBeforeWrite($values);
        $rows = $narrow === null ? $this->rowsBeforeWrite($values) : null;
        $result = parent::update($values);
        if ($narrow !== null) {
            $this->touchNarrow($narrow);
        } else {
            $this->touchWritten($rows, $values);
        }

        return $result;
    }

    /**
     * A write of RevisionMap::NARROW_COLUMNS only (a stock decrement, a ledger balance): the rows' NARROW_INPUTS before
     * it, and after it when the values already say so (an Eloquent save of plain values). Null for any other write.
     *
     * @return array{table: string, before: list<array<string, mixed>>, after: array<int, array<string, mixed>>|null}|null
     */
    private function narrowBeforeWrite(array $values): ?array
    {
        $table = $this->revisionTable();
        $hooks = app(RevisionHooks::class);
        if ($table === null || !$hooks->isNarrow($table, $values) || ($this->joins !== null && $this->joins !== [])) {
            return null;
        }
        $inputs = RevisionMap::NARROW_INPUTS[$table];

        // An Eloquent save of one row: its model event announced the row before and after
        if (count($this->wheres) === 1 && ($this->wheres[0]['type'] ?? null) === 'Basic'
            && ($this->wheres[0]['operator'] ?? null) === '=' && is_string($this->wheres[0]['column'] ?? null)) {
            $column = (string) $this->wheres[0]['column'];
            $column = substr($column, (int) strrpos('.'.$column, '.'));
            $expected = $hooks->takeExpected($table, $column, $this->wheres[0]['value'] ?? null);
            if ($expected !== null) {
                [$original, $merged] = $expected;
                $keys = array_flip($inputs);
                if (array_diff_key($keys, $original) === []) {
                    $after = null;
                    $plain = array_filter(array_intersect_key($merged, $keys), fn ($v) => $v === null || is_scalar($v));
                    if (count($plain) === count($keys) && ($id = (int) ($original['id'] ?? 0)) > 0) {
                        $after = [$id => $plain];
                    }

                    return ['table' => $table, 'before' => [array_intersect_key($original, $keys)], 'after' => $after];
                }
            }
        }

        $alias = $this->revisionAlias($table);
        $select = clone $this;
        $select->orders = null;
        $select->limit = null;
        $select->offset = null;
        $select->unions = null;
        $select->lock = null;
        $select->aggregate = null;
        $select->bindings['order'] = [];
        $select->bindings['union'] = [];
        $select->bindings['unionOrder'] = [];
        $select->columns = null;
        $select->select(array_map(fn (string $c) => new Expression(
            $this->grammar->wrap($alias.'.'.$c).' as '.$this->grammar->wrap($c)
        ), $inputs));

        return ['table' => $table, 'before' => array_map(fn ($row) => (array) $row, $select->get()->all()), 'after' => null];
    }

    /** @param array{table: string, before: list<array<string, mixed>>, after: array<int, array<string, mixed>>|null} $narrow */
    private function touchNarrow(array $narrow): void
    {
        if ($narrow['before'] === []) {
            return; // matched no row
        }
        $after = $narrow['after'];
        if ($after === null) {
            $after = [];
            $ids = array_values(array_filter(array_map(fn (array $row) => (int) ($row['id'] ?? 0), $narrow['before'])));
            foreach (array_chunk($ids, 500) as $chunk) {
                foreach ($this->newQuery()->from($narrow['table'])->whereIn('id', $chunk)->get(RevisionMap::NARROW_INPUTS[$narrow['table']]) as $row) {
                    $after[(int) $row->id] = (array) $row;
                }
            }
        }

        app(RevisionHooks::class)->touchNarrow($narrow['table'], $narrow['before'], $after);
    }

    public function updateFrom(array $values)
    {
        $this->forgetChannelGrants($values);
        $rows = $this->rowsBeforeWrite($values);
        $result = parent::updateFrom($values);
        $this->touchWritten($rows, $values);

        return $result;
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            $this->where($this->from.'.id', '=', $id);
            $id = null;
        }

        $this->forgetChannelGrants(null);
        $rows = $this->rowsBeforeWrite(null);
        $result = parent::delete($id);
        $this->touchWritten($rows, []);

        return $result;
    }

    public function truncate()
    {
        parent::truncate();
        $this->touchUnknown();
    }

    /**
     * A change to an access row (token, terminal, user, permission) drops the remembered channel grants that depended
     * on those rows (CachedChannelAuth): the rows are listed first (one small select); a write whose rows cannot be
     * listed, or a branch moved to another store, drops every grant.
     */
    private function forgetChannelGrants(?array $values): void
    {
        $table = $this->revisionTable();
        if ($table === null || !in_array($table, CachedChannelAuth::AUTH_TABLES, true)) {
            return;
        }
        if ($values !== null) {
            $names = array_map(fn ($column) => substr((string) $column, (int) strrpos('.'.$column, '.')), array_keys($values));
            $relevant = CachedChannelAuth::AUTH_COLUMNS[$table] ?? null;
            if ($relevant !== null && array_intersect($names, $relevant) === []) {
                return;
            }
            $quiet = CachedChannelAuth::AUTH_QUIET_COLUMNS[$table] ?? [];
            if ($relevant === null && $names !== [] && array_diff($names, $quiet) === []) {
                return;
            }
        }

        $subject = CachedChannelAuth::SUBJECTS[$table] ?? null;
        if ($subject === null) {
            CachedChannelAuth::forgetGrants();

            return;
        }
        [$prefix, $column] = $subject;

        try {
            $alias = $this->revisionAlias($table);
            $select = clone $this;
            $select->orders = null;
            $select->limit = null;
            $select->offset = null;
            $select->unions = null;
            $select->lock = null;
            $select->aggregate = null;
            $select->bindings['order'] = [];
            $select->bindings['union'] = [];
            $select->bindings['unionOrder'] = [];
            $select->columns = null;
            $ids = $select->select(new Expression($this->grammar->wrap($alias.'.'.$column).' as '.$this->grammar->wrap('subject')))
                ->distinct()->limit(CachedChannelAuth::MAX_SUBJECTS + 1)->pluck('subject')->all();
        } catch (\Throwable) {
            CachedChannelAuth::forgetGrants();

            return;
        }

        $subjects = [];
        foreach ($ids as $id) {
            if ($id !== null) {
                $subjects[] = $prefix.(int) $id;
            }
        }
        if (count($ids) > CachedChannelAuth::MAX_SUBJECTS) {
            CachedChannelAuth::forgetGrants();
        } else {
            CachedChannelAuth::forgetGrantsOf($subjects);
        }
    }

    /** An inserted permission override can take a channel away too: that store's grants */
    private function forgetInsertedGrants(array $values): void
    {
        if ($this->revisionTable() !== 'role_permission_overrides' || $values === []) {
            return;
        }
        $rows = is_array(reset($values)) ? array_values($values) : [$values];
        $subjects = [];
        foreach ($rows as $row) {
            if (!isset($row['store_id'])) {
                CachedChannelAuth::forgetGrants();

                return;
            }
            $subjects[] = 's:'.(int) $row['store_id'];
        }
        CachedChannelAuth::forgetGrantsOf($subjects);
    }

    /** The table written, without alias or quoting (null for a sub-query or an expression) */
    public function revisionTable(): ?string
    {
        if (!is_string($this->from)) {
            return null;
        }
        $table = trim(preg_split('/\s+as\s+/i', $this->from)[0] ?? '');
        $table = str_replace('"', '', $table);
        if (str_contains($table, '.')) {
            $table = substr($table, (int) strrpos($table, '.') + 1);
        }
        $prefix = $this->grammar->getTablePrefix();
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $table = substr($table, strlen($prefix));
        }

        return $table === '' ? null : $table;
    }

    /** The alias (or table) the columns of this query are qualified with */
    private function revisionAlias(string $table): string
    {
        if (is_string($this->from) && preg_match('/\s+as\s+("?)([A-Za-z0-9_]+)\1\s*$/i', $this->from, $m) === 1) {
            return $m[2];
        }

        return $table;
    }

    /**
     * The scope columns of the rows an update/delete is about to write, or null when nothing needs touching.
     *
     * @return list<array<string, mixed>>|null
     */
    private function rowsBeforeWrite(?array $values): ?array
    {
        $table = $this->revisionTable();
        $hooks = app(RevisionHooks::class);
        $columns = $table !== null ? $hooks->columnsFor($table) : null;
        if ($table === null || $columns === null) {
            return null;
        }
        if ($values !== null && $hooks->isQuiet($table, $values)) {
            return null;
        }

        // An Eloquent save/delete of one row: its model event announced the row
        if (count($this->wheres) === 1 && ($this->wheres[0]['type'] ?? null) === 'Basic'
            && ($this->wheres[0]['operator'] ?? null) === '=' && is_string($this->wheres[0]['column'] ?? null)) {
            $column = (string) $this->wheres[0]['column'];
            $column = substr($column, (int) strrpos('.'.$column, '.'));
            $expected = $hooks->takeExpected($table, $column, $this->wheres[0]['value'] ?? null);
            if ($expected !== null) {
                return $expected;
            }
        }

        if ($columns === []) {
            return [[]]; // rules that need no row data (ALL_STORES)
        }

        // A write bounded to one row by `id = ?` among AND-ed conditions (an optimistic-lock update, a guarded stock
        // decrement): that row's rule columns, read by id and remembered for the request like a VIA parent
        $id = $this->revisionRowId($table);
        if ($id !== null) {
            return $hooks->rowsById($table, $id);
        }

        $alias = $this->revisionAlias($table);
        $select = clone $this;
        $select->orders = null;
        $select->limit = null;
        $select->offset = null;
        $select->unions = null;
        $select->lock = null;
        $select->aggregate = null;
        $select->bindings['order'] = [];
        $select->bindings['union'] = [];
        $select->bindings['unionOrder'] = [];
        $select->columns = null;
        $select->select(array_map(fn (string $c) => new Expression(
            $this->grammar->wrap($alias.'.'.$c).' as '.$this->grammar->wrap($c)
        ), $columns))->distinct();

        return array_map(fn ($row) => (array) $row, $select->get()->all());
    }

    /** The id the AND-ed wheres pin the write to, null when they do not (or a join is involved) */
    private function revisionRowId(string $table): ?int
    {
        if ($this->joins !== null && $this->joins !== []) {
            return null;
        }
        $alias = $this->revisionAlias($table);
        $id = null;
        foreach ($this->wheres as $where) {
            if (($where['boolean'] ?? 'and') !== 'and') {
                return null;
            }
            if (($where['type'] ?? null) === 'Basic' && ($where['operator'] ?? null) === '='
                && in_array($where['column'] ?? null, ['id', "{$table}.id", "{$alias}.id"], true)) {
                $value = $where['value'] ?? null;
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $id = (int) $value;
                }
            }
        }

        return $id;
    }

    /** @param list<array<string, mixed>>|null $rows */
    private function touchWritten(?array $rows, array $values): void
    {
        $table = $this->revisionTable();
        if ($rows === null || $table === null) {
            return;
        }

        // A write that moves a row (another branch, another customer) touches the new scope too
        $moved = [];
        foreach ($values as $column => $value) {
            $name = substr((string) $column, (int) strrpos('.'.$column, '.'));
            if (!$value instanceof Expression && (is_int($value) || is_string($value) || $value === null)) {
                $moved[$name] = $value;
            }
        }
        if ($moved !== []) {
            foreach ($rows as $row) {
                $rows[] = array_merge($row, array_intersect_key($moved, $row));
            }
        }
        $hooks = app(RevisionHooks::class);
        if (array_intersect(array_keys($moved), $hooks->columnsFor($table) ?? []) !== [] || $this->hasExpressionRuleValue($values, $hooks->columnsFor($table) ?? [])) {
            $hooks->forget($table);
        }

        $hooks->touchRows($table, $rows);
    }

    /** @param list<string> $columns */
    private function hasExpressionRuleValue(array $values, array $columns): bool
    {
        foreach ($values as $column => $value) {
            if ($value instanceof Expression && in_array(substr((string) $column, (int) strrpos('.'.$column, '.')), $columns, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys  columns that identify an inserted/upserted row, to read the rule columns the values
     *   leave out (an upsert that updates an existing row by its unique key)
     */
    private function touchInserted(array $values, array $keys = []): void
    {
        $table = $this->revisionTable();
        if ($table === null || $values === [] || !RevisionMap::tracks($table)) {
            return;
        }
        $rows = is_array(reset($values)) ? array_values($values) : [$values];
        $columns = app(RevisionHooks::class)->columnsFor($table) ?? [];

        foreach ($rows as $index => $row) {
            if (array_diff($columns, array_keys($row)) === [] || $keys === [] || array_diff($keys, array_keys($row)) !== []) {
                continue;
            }
            $query = $this->newQuery()->from($table);
            foreach ($keys as $key) {
                $query->where($key, $row[$key]);
            }
            $stored = $query->first($columns);
            if ($stored !== null) {
                $rows[$index] = array_merge((array) $stored, $row);
            }
        }

        app(RevisionHooks::class)->touchRows($table, $rows);
    }

    private function touchUnknown(): void
    {
        $table = $this->revisionTable();
        if ($table !== null) {
            app(RevisionHooks::class)->touchEverything($table);
        }
    }
}
