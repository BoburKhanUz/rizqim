<?php

namespace App\Domain\Realtime;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wave M2: turns written rows into ChangeRevision touches by the rules of RevisionMap. Called by RevisionQueryBuilder
 * for every insert, update, upsert and delete (Eloquent or not); the touches themselves wait for the commit.
 */
#[Singleton]
final class RevisionHooks
{
    /** Eloquent single-row writes announced by the model event (table|column|key => rows), so no pre-select is needed */
    private array $expected = [];

    /**
     * Parent rows looked up for VIA rules (table => [id => row]), kept for PARENT_MEMO_SECONDS only: long-lived queue
     * workers must not resolve with a row another process has changed since.
     */
    private array $parents = [];

    private float $parentsAt = 0.0;

    private const PARENT_MEMO_SECONDS = 2.0;

    /** Columns a pre-select needs to resolve the table's rules (null: untracked) */
    public function columnsFor(string $table): ?array
    {
        if (!RevisionMap::tracks($table)) {
            return null;
        }

        $columns = [];
        foreach (RevisionMap::rules($table) ?? [] as $rule) {
            switch ($rule[0]) {
                case RevisionMap::BRANCH:
                    $columns[] = 'branch_id';
                    $columns[] = 'store_id';
                    break;
                case RevisionMap::STORE:
                case RevisionMap::STORE_BRANCHES:
                    $columns[] = $rule[1] ?? 'store_id';
                    break;
                case RevisionMap::USER:
                case RevisionMap::VIA:
                case RevisionMap::FEATURE_SCOPE:
                    $columns[] = $rule[1];
                    break;
            }
        }

        return array_values(array_unique($columns));
    }

    /** True when the update only writes liveness stamps of the table (RevisionMap::QUIET_COLUMNS) */
    public function isQuiet(string $table, array $values): bool
    {
        $quiet = RevisionMap::QUIET_COLUMNS[$table] ?? null;
        if ($quiet === null || $values === []) {
            return false;
        }

        foreach (array_keys($values) as $column) {
            $name = Str::afterLast((string) $column, '.');
            if (!in_array($name, $quiet, true)) {
                return false;
            }
        }

        return true;
    }

    /** True when the update writes only RevisionMap::NARROW_COLUMNS of the table (decided by touchNarrow()) */
    public function isNarrow(string $table, array $values): bool
    {
        $narrow = RevisionMap::NARROW_COLUMNS[$table] ?? null;
        if ($narrow === null || $values === []) {
            return false;
        }

        foreach (array_keys($values) as $column) {
            if (!in_array(Str::afterLast((string) $column, '.'), $narrow, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A narrow write (RevisionMap::NARROW_COLUMNS): only the tokens of the screens that show a difference between the
     * rows before and after it. A row whose after-state is unknown is decided as if everything changed.
     *
     * @param  list<array<string, mixed>>  $before  RevisionMap::NARROW_INPUTS of each written row
     * @param  array<int, array<string, mixed>>  $after  the same, by id, read after the write
     */
    public function touchNarrow(string $table, array $before, array $after): void
    {
        $set = new RevisionTouchSet;
        $band = (float) config('realtime.exact_stock_below', 20);
        $inBand = static fn (mixed $quantity): bool => !is_numeric($quantity) || (float) $quantity <= $band;

        foreach ($before as $row) {
            $id = self::id($row['id'] ?? null);
            $new = $id !== null ? ($after[$id] ?? null) : null;
            switch ($table) {
                case 'stores':
                    if ($id !== null && ($new === null || self::orderLocked($row) !== self::orderLocked($new))) {
                        $set->stores[$id] = true; // the debt lock flipped: the store document shows it
                    }
                    break;
                case 'product_variants':
                    if ($new === null || $inBand($row['stock'] ?? null) || $inBand($new['stock'] ?? null)) {
                        foreach ($this->parentRows('products', array_filter([self::id($row['product_id'] ?? null)])) as $product) {
                            if (($storeId = self::id($product['store_id'] ?? null)) !== null) {
                                $set->storesAlone[$storeId] = true;
                            }
                        }
                    }
                    break;
                case 'menu_batches':
                    if (($branchId = self::id($row['branch_id'] ?? null)) !== null) {
                        $set->branches[$branchId] = true;
                    }
                    if (($storeId = self::id($row['store_id'] ?? null)) !== null
                        && ($new === null || $inBand($row['quantity_left'] ?? null) || $inBand($new['quantity_left'] ?? null))) {
                        $set->storesAlone[$storeId] = true;
                    }
                    break;
            }
        }

        $this->apply($set);
    }

    /** Store::is_order_locked of a stores row (status and the debt columns): the one rule CreateOrderAction applies */
    private static function orderLocked(array $row): bool
    {
        $store = new \App\Models\Store;
        $store->setRawAttributes(array_intersect_key($row, array_flip(['status', 'debt_balance', 'subscription_debt', 'credit_limit'])));

        return (bool) $store->is_order_locked;
    }

    /**
     * The model events of an Eloquent update/delete announce the row (original and new attributes), so the query that
     * follows (`where <key> = ?`) is resolved without a pre-select. Skipped when an attribute the rules need was not
     * loaded: the builder then pre-selects as for any mass write.
     */
    public function expect(string $table, string $keyName, mixed $key, array $original, array $attributes): void
    {
        $columns = $this->columnsFor($table);
        if ($columns === null || $key === null || !is_scalar($key)) {
            return;
        }
        foreach ($columns as $column) {
            if (!array_key_exists($column, $original) && !array_key_exists($column, $attributes)) {
                return;
            }
        }

        if (count($this->expected) > 200) {
            $this->expected = [];
        }
        $this->expected[$table.'|'.$keyName.'|'.$key] = [$original, array_merge($original, $attributes)];
    }

    public function takeExpected(string $table, string $keyName, mixed $key): ?array
    {
        if (!is_scalar($key)) {
            return null;
        }
        $index = $table.'|'.$keyName.'|'.$key;
        $rows = $this->expected[$index] ?? null;
        unset($this->expected[$index]);

        return $rows;
    }

    /**
     * Touch the scopes of the given rows of the table (arrays or objects with the rule columns).
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     */
    public function touchRows(string $table, iterable $rows): void
    {
        $rules = RevisionMap::rules($table);
        if ($rules === null || $rules[0][0] === RevisionMap::NONE) {
            return;
        }

        $set = new RevisionTouchSet;
        $this->resolve($table, $rules, $this->arrays($rows), $set, 0);
        $this->apply($set);
    }

    /** An update changed a rule column of the table (a moved order, another customer): forget its remembered rows */
    public function forget(string $table): void
    {
        unset($this->parents[$table]);
    }

    /**
     * The rule columns of one row by id (remembered for PARENT_MEMO_SECONDS, like a VIA parent)
     *
     * @return list<array<string, mixed>>
     */
    public function rowsById(string $table, int $id): array
    {
        return $this->parentRows($table, [$id]);
    }

    /** A write whose rows cannot be known (insert from a sub-select, truncate): touch every store */
    public function touchEverything(string $table): void
    {
        if (!RevisionMap::tracks($table)) {
            return;
        }
        $set = new RevisionTouchSet;
        $set->allStores = true;
        $this->apply($set);
    }

    /**
     * @param  list<list<string>>  $rules
     * @param  list<array<string, mixed>>  $rows
     */
    private function resolve(string $table, array $rules, array $rows, RevisionTouchSet $set, int $depth): void
    {
        if ($rows === [] || $depth > 4) {
            return;
        }

        foreach ($rules as $rule) {
            switch ($rule[0]) {
                case RevisionMap::BRANCH:
                    foreach ($rows as $row) {
                        if (self::id($row['branch_id'] ?? null) !== null) {
                            $set->branches[(int) $row['branch_id']] = true;
                        } elseif (self::id($row['store_id'] ?? null) !== null) {
                            $set->storeBranches[(int) $row['store_id']] = true;
                        }
                    }
                    break;
                case RevisionMap::STORE:
                case RevisionMap::STORE_BRANCHES:
                    $column = $rule[1] ?? 'store_id';
                    foreach ($rows as $row) {
                        if (($id = self::id($row[$column] ?? null)) !== null) {
                            if ($rule[0] === RevisionMap::STORE) {
                                $set->stores[$id] = true;
                            } else {
                                $set->storeBranches[$id] = true;
                            }
                        }
                    }
                    break;
                case RevisionMap::USER:
                    foreach ($rows as $row) {
                        if (($id = self::id($row[$rule[1]] ?? null)) !== null) {
                            $set->users[$id] = true;
                        }
                    }
                    break;
                case RevisionMap::VIA:
                    $ids = [];
                    foreach ($rows as $row) {
                        if (($id = self::id($row[$rule[1]] ?? null)) !== null) {
                            $ids[$id] = true;
                        }
                    }
                    $parentRules = RevisionMap::rules($rule[2]);
                    if ($ids !== [] && $parentRules !== null) {
                        $this->resolve($rule[2], $parentRules, $this->parentRows($rule[2], array_keys($ids)), $set, $depth + 1);
                    }
                    break;
                case RevisionMap::ALL_STORES:
                    $set->allStores = true;
                    break;
                case RevisionMap::FEATURE_SCOPE:
                    foreach ($rows as $row) {
                        $scope = (string) ($row[$rule[1]] ?? '');
                        if (preg_match('/^' . preg_quote(\App\Models\Store::class, '/') . '\|(\d+)$/', $scope, $m) === 1) {
                            $set->stores[(int) $m[1]] = true;
                        } else {
                            $set->allStores = true;
                        }
                    }
                    break;
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function parentRows(string $table, array $ids): array
    {
        if (microtime(true) - $this->parentsAt > self::PARENT_MEMO_SECONDS) {
            $this->parents = [];
            $this->parentsAt = microtime(true);
        }
        $known = $this->parents[$table] ?? [];
        $missing = array_values(array_filter($ids, fn ($id) => !isset($known[$id])));
        if ($missing !== []) {
            $columns = array_values(array_unique(array_merge(['id'], $this->columnsFor($table) ?? [])));
            foreach (array_chunk($missing, 500) as $chunk) {
                foreach (DB::table($table)->whereIn('id', $chunk)->get($columns) as $row) {
                    $known[(int) $row->id] = (array) $row;
                }
            }
            $this->parents[$table] = count($known) > 1000 ? [] : $known;
        }

        $rows = [];
        foreach ($ids as $id) {
            if (isset($known[$id])) {
                $rows[] = $known[$id];
            }
        }

        return $rows;
    }

    private function apply(RevisionTouchSet $set): void
    {
        $revisions = app(ChangeRevision::class);

        if ($set->allStores) {
            $revisions->touchAllStores(); // every store and branch, resolved once at the flush

            return;
        }

        foreach (array_keys($set->stores) as $storeId) {
            $revisions->touchStore($storeId); // reaches every branch of the store (resolved at the flush)
            unset($set->storeBranches[$storeId], $set->storesAlone[$storeId]);
        }
        foreach (array_keys($set->storesAlone) as $storeId) {
            $revisions->touchStoreAlone($storeId);
        }
        foreach (array_keys($set->storeBranches) as $storeId) {
            $revisions->touchStoreBranches($storeId);
        }
        foreach (array_keys($set->branches) as $branchId) {
            $revisions->touchBranch($branchId);
        }
        foreach (array_keys($set->users) as $userId) {
            $revisions->touchUser($userId);
        }
    }

    /**
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return list<array<string, mixed>>
     */
    private function arrays(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            } elseif (is_object($row)) {
                $out[] = (array) $row;
            }
        }

        return $out;
    }

    private static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value) && $value !== '0') {
            return (int) $value;
        }

        return null;
    }
}
