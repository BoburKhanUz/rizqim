<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreRating;
use Illuminate\Support\Facades\DB;

#[Group('Stores')]
class StoreRatingController extends Controller
{
    use ApiResponse;

    private const ALREADY_RATED = 'Siz ushbu buyurtma uchun allaqachon baho bergansiz.';

    /**
     * Rate a store
     *
     * Only the customer who placed the order, for a delivered order of this store, once per order (400 when this order
     * was already rated; a rating is not edited). Staff accounts of any store are refused (403). A missing order and
     * somebody else's order (or an order of another store) answer the same 404; 422 while the order is not delivered.
     * Returns the store's new average and count, recomputed from the stored ratings.
     */
    public function rate(Request $request, int $storeId): JsonResponse
    {
        // Wave M4 H1/H2: who may rate, and no order-existence oracle
        $store = Store::findOrFail($storeId);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'order_id' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        // Owner, manager, cashier, courier, ... of any store (a competitor too): their account rates nothing
        if ($user->store_id !== null || in_array($user->role, UserRole::storeRoles(), true) || $user->isPlatformAccount()) {
            return $this->error("Do'kon xodimlari baho qoldira olmaydi.", [], 403);
        }

        $order = Order::query()
            ->whereKey((int) $request->integer('order_id'))
            ->where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->first();
        if (!$order) {
            return $this->error('Buyurtma topilmadi.', [], 404);
        }
        if ($order->status !== OrderStatus::DELIVERED) {
            return $this->error('Buyurtma yetkazib berilgandan keyin baholash mumkin.', [], 422);
        }

        try {
            $saved = DB::transaction(function () use ($user, $store, $order, $request) {
                // The order first, then the store (the ledger's order of locks): KEY SHARE is what the rating's foreign
                // key takes anyway. The store's row lock serializes the ratings of one store, so each recomputation
                // below sees every committed rating (two at once never lose one).
                Order::query()->whereKey($order->id)->lock('for key share')->first();
                $locked = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

                if (StoreRating::where('order_id', $order->id)->exists()) {
                    return null;
                }

                StoreRating::create([
                    'user_id' => $user->id,
                    'store_id' => $locked->id,
                    'order_id' => $order->id,
                    'rating' => $request->integer('rating'),
                    'comment' => $request->input('comment'),
                ]);

                $ratingsInfo = StoreRating::where('store_id', $locked->id)
                    ->selectRaw('COUNT(*) as count, AVG(rating) as average')
                    ->first();

                $locked->update([
                    'rating' => round((float) ($ratingsInfo->average ?? 0), 2),
                    'review_count' => (int) ($ratingsInfo->count ?? 0),
                ]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            $saved = null;
        }

        if ($saved === null) {
            return $this->error(self::ALREADY_RATED, [], 400);
        }

        return $this->success([
            'rating' => $saved->rating,
            'review_count' => (int) $saved->review_count,
        ], 'Baholash muvaffaqiyatli saqlandi.');
    }
}
