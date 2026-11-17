<?php

namespace App\Domain\Marketplace;

use App\Enums\OrderChannel;
use App\Http\Controllers\Api\V1\Pos\PosCatalogController;
use App\Models\Brand;
use App\Models\User;
use App\Support\RowId;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wave M2: the customer browse reads (GET /stores, GET /catalog, GET /catalog/availability) answered from the read
 * model in front of their controllers, which stay as they were and serve whatever this does not take: the rollback
 * switch (marketplace.read_model=false), the cart's store list (for=checkout), the POS channels and every request
 * that names a branch. The request checks (store_id forms, 404/403/422 answers) are the controllers' own, repeated.
 *
 * Route order: ConditionalGet::using('none', N) BEFORE this one (it needs the scopes this declares to set the ETag).
 */
final class MarketplaceBrowse
{
    private const UUID = '/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i';

    /**
     * The middleware string for a route: MarketplaceBrowse::for('stores').
     */
    public static function for(string $read): string
    {
        return self::class.':'.$read;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $read): Response
    {
        if (!StoreDirectory::enabled() || !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $user = $request->user('sanctum') ?: $request->user();
        $user = $user instanceof User ? $user : null;

        return match ($read) {
            'stores' => $this->stores($request, $user) ?? $next($request),
            'catalog' => $this->catalog($request, $user) ?? $next($request),
            'availability' => $this->availability($request) ?? $next($request),
            default => $next($request),
        };
    }

    private function stores(Request $request, ?User $user): ?JsonResponse
    {
        if ($request->query('for') === 'checkout') {
            return null; // the cart: live, on the default pool
        }

        $result = app(MarketplaceReads::class)->stores($request, $user);
        $body = ['success' => true, 'data' => $result['data']];
        if ($result['meta'] !== null) {
            $body['meta'] = $result['meta'];
        }

        return response()->json($body);
    }

    private function catalog(Request $request, ?User $user): ?JsonResponse
    {
        if (PosCatalogController::handles($request->query('channel'))) {
            return null;
        }

        $reads = app(MarketplaceReads::class);
        $channel = $request->query('channel') === OrderChannel::MOBILE->value ? OrderChannel::MOBILE : OrderChannel::TELEGRAM;
        $storeKey = $request->query('store_id');

        if (!$storeKey) {
            return self::success($reads->feed($request, $user, $channel));
        }

        if (is_string($storeKey) && ctype_digit($storeKey)) {
            // P1-34: a numeric id must fit the id column
            $storeId = RowId::parse($storeKey)
                ?? throw ValidationException::withMessages(['store_id' => "store_id musbat butun son bo'lishi kerak."]);
        } elseif (!is_string($storeKey) || !preg_match(self::UUID, $storeKey)) {
            return self::error('Store not found.', 404);
        } else {
            $storeId = $reads->resolveStoreId($storeKey, false);
        }

        if ($storeId !== null) {
            MarketplaceEtag::forStore($request, $storeId, $user);
        }
        if ($storeId === null || !$reads->storeAvailable($storeId)) {
            return self::error('Store is currently unavailable.', 403);
        }

        // P6-55: `brand` (uuid or id) narrows the menu to that brand's dishes (brands table, read live)
        $brandId = null;
        $brandKey = $request->query('brand');
        if (is_string($brandKey) && $brandKey !== '') {
            $brand = Brand::where('store_id', $storeId)->where('is_active', true)
                ->where(ctype_digit($brandKey) ? 'id' : 'uuid', ctype_digit($brandKey) ? (RowId::parse($brandKey) ?? 0) : strtolower($brandKey))
                ->first();
            if ($brand === null) {
                return self::error('Brend topilmadi.', 404);
            }
            $brandId = (int) $brand->id;
        }

        return self::success($reads->storeMenu($storeId, $user, $channel, $brandId));
    }

    private function availability(Request $request): ?JsonResponse
    {
        $branchId = $request->query('branch_id');
        if ($branchId !== null && $branchId !== '') {
            return null; // a till or a staff screen: the tables
        }

        $storeKey = $request->query('store_id', '');
        $storeKey = is_string($storeKey) ? $storeKey : '';
        $numeric = ctype_digit($storeKey);
        if ($storeKey === '' || (!$numeric && !preg_match(self::UUID, $storeKey))) {
            return self::error('Store not found.', 404);
        }

        $reads = app(MarketplaceReads::class);
        $storeId = $reads->resolveStoreId($numeric ? $storeKey : strtolower($storeKey), $numeric);
        if ($storeId !== null) {
            MarketplaceEtag::forStore($request, $storeId, null);
        }
        $overlay = $reads->availability($storeId);
        if ($overlay === null) {
            return self::error('Store not found.', 404);
        }

        return self::success([
            'store_id' => (int) $storeId,
            'branches' => $overlay['branches'],
            'generated_at' => $overlay['generated_at'],
        ]);
    }

    /**
     * App\Traits\ApiResponse::success, as the controllers answer.
     */
    private static function success(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '', 'data' => $data]);
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => []], $status);
    }
}
