<?php

namespace App\Domain\Marketplace;

use App\Http\Middleware\ConditionalGet;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The change scopes of the customer browse answers, for M2-2's ConditionalGet middleware (route: using('none', N)).
 *
 * - GET /catalog?store_id=: the store's revision (menu, stop-list, store card, reviews; categories and payment switches
 *   touch every store) and the customer's (default address, cash rule). Read before the documents.
 * - GET /stores, GET /catalog (feed): the revisions of the stores shown and the customer's. Which stores an area has
 *   (a new store, a moved branch) is not a store revision of the answer, so these ETags live AREA_MAX_AGE seconds at
 *   most; an answer showing more than MAX_STORES stores gets no ETag (the old full list of a large area).
 * - The clock (opening hours, a stop-list entry's `until`, the daily menu) is bounded by the max ages.
 */
final class MarketplaceEtag
{
    /** /catalog (store menu and feed): seconds an ETag validates at most */
    public const STORE_MAX_AGE = 30;

    /** /stores */
    public const AREA_MAX_AGE = 60;

    /** /catalog/availability (route constant in routes/api_menu.php) */
    public const AVAILABILITY_MAX_AGE = 15;

    private const MAX_STORES = 60;

    public static function forStore(Request $request, int $storeId, ?User $user): void
    {
        if (!self::enabled()) {
            return;
        }
        ConditionalGet::depends($request, 'store', $storeId);
        if ($user !== null) {
            ConditionalGet::depends($request, 'user', (int) $user->getKey());
        }
    }

    /**
     * @param  list<int>  $storeIds
     */
    public static function forStores(Request $request, array $storeIds, ?User $user): void
    {
        if (!self::enabled()) {
            return;
        }
        $storeIds = array_values(array_unique(array_filter($storeIds, fn (int $id) => $id > 0)));
        if (count($storeIds) > self::MAX_STORES) {
            ConditionalGet::skip($request);

            return;
        }
        foreach ($storeIds as $storeId) {
            ConditionalGet::depends($request, 'store', $storeId);
        }
        if ($user !== null) {
            ConditionalGet::depends($request, 'user', (int) $user->getKey());
        } elseif ($storeIds === []) {
            ConditionalGet::skip($request); // nothing to tie an empty answer to
        }
    }

    private static function enabled(): bool
    {
        return class_exists(ConditionalGet::class) && (bool) config('conditional_get.enabled', true);
    }
}
