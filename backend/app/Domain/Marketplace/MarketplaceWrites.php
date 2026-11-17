<?php

namespace App\Domain\Marketplace;

/**
 * Watches the executed SQL for writes to the tables the area and global documents show and touches their revision
 * (after commit, MarketplaceRevision). Statements are matched by their text, so Eloquent saves, mass updates,
 * increments and DB::table() writes all count. Reads cost one character comparison.
 *
 * - directory: stores and branches. An UPDATE that only sets columns no store card shows (balances, counters of
 *   other screens, timestamps) does not touch it: the ledger writes stores.debt_balance on every settled order, and
 *   rebuilding every area on each one would defeat the cache. A card's `is_order_locked` (which the debt decides)
 *   follows the store's own revision instead (StoreDirectory::snapshot re-reads a store whose token moved; the debt
 *   lock flipping moves it, RevisionMap); the raw balances on a card follow within `ttl.area` (120 s); checkout
 *   reads the debt live.
 * - global: categories, promos, payment_providers, settings (rare writes by the RIZQIM team).
 */
final class MarketplaceWrites
{
    /** Columns of a stores row that an area document shows (StoreDirectory::CARD_COLUMNS and the filters) */
    private const STORE_COLUMNS = [
        'uuid', 'name', 'phone', 'address', 'logo', 'banner', 'description', 'is_open', 'is_premium', 'review_count',
        'payment_settings', 'status', 'credit_limit', 'open_time', 'close_time', 'min_order_amount', 'is_active',
        'deleted_at', 'timezone', 'marketplace_offline_minutes', 'slug', 'accept_cash', 'accept_click', 'accept_payme',
    ];

    /** Columns of a branches row an area document shows or filters by */
    private const BRANCH_COLUMNS = [
        'store_id', 'name', 'latitude', 'longitude', 'radius_km', 'address', 'phone', 'region_id', 'district_id',
        'deleted_at', 'status',
    ];

    private const GLOBAL_TABLES = ['categories', 'promos', 'payment_providers', 'settings'];

    public function __construct(private readonly MarketplaceRevision $revisions) {}

    public function seen(string $sql): void
    {
        $first = $sql[0] ?? '';
        if ($first === 's' || $first === 'S' || $first === '(' || $first === 'w' || $first === 'W') {
            return; // select / with
        }

        if (!preg_match('/^\s*(insert\s+into|update|delete\s+from)\s+(?:"?[a-z_]+"?\.)?"?([a-z_]+)"?/i', $sql, $m)) {
            return;
        }

        $table = strtolower($m[2]);
        if (in_array($table, self::GLOBAL_TABLES, true)) {
            $this->revisions->touchGlobal();

            return;
        }

        $columns = match ($table) {
            'stores' => self::STORE_COLUMNS,
            'branches' => self::BRANCH_COLUMNS,
            default => null,
        };
        if ($columns === null) {
            return;
        }

        if (strncasecmp(ltrim($m[1]), 'update', 6) !== 0 || $this->setsAny($sql, $columns)) {
            $this->revisions->touchDirectory();
        }
    }

    /**
     * Whether an UPDATE sets one of the columns (true when its SET list cannot be read: when in doubt, touch).
     *
     * @param  list<string>  $columns
     */
    private function setsAny(string $sql, array $columns): bool
    {
        if (!preg_match('/\bset\b(.*?)(?:\bwhere\b|\breturning\b|$)/is', $sql, $m)) {
            return true;
        }

        if (!preg_match_all('/(?:^|,)\s*"?([a-z_]+)"?\s*=/i', $m[1], $set)) {
            return true;
        }

        foreach ($set[1] as $column) {
            if (in_array(strtolower($column), $columns, true)) {
                return true;
            }
        }

        return false;
    }
}
