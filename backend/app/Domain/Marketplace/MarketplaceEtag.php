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
 * - GET /stores, GET /catalog (feed): the revisions of the stores shown, as the documents recorded them (read before
 *   the data they cover, never after: review M2 R1-4), and the customer's. Which stores an area has
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
     * The customer's scope (default address, cash rule): declare it before reading them.
     */
    public static function forUser(Request $request, ?User $user): void
    {
        if (self::enabled() && $user !== null) {
            ConditionalGet::depends($request, 'user', (int) $user->getKey());
        }
    }

    /**
     * @param  array<int, string>  $tokens  store id => the store revision the shown data was read under (read before
     *                                      it; '' when unknown: the answer gets no ETag)
     */
    public static function forStores(Request $request, array $tokens, ?User $user): void
    {
        if (!self::enabled()) {
            return;
        }
        $tokens = array_filter($tokens, fn ($token, $id) => (int) $id > 0, ARRAY_FILTER_USE_BOTH);
        if (count($tokens) > self::MAX_STORES || in_array('', $tokens, true)) {
            ConditionalGet::skip($request);

            return;
        }
        $storeIds = array_keys($tokens);
        foreach ($tokens as $storeId => $token) {
            ConditionalGet::dependsAt($request, 'store', (int) $storeId, $token);
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
