<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Models\StoreRating;
use Illuminate\Support\Facades\DB;

class StoreRatingController extends Controller
{
    use ApiResponse;

    public function rate(Request $request, int $storeId): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $user = $request->user();
        $store = Store::findOrFail($storeId);

        // Check if user has already rated this store for this order (if order_id provided)
        if ($request->order_id) {
            $exists = StoreRating::where('user_id', $user->id)
                ->where('store_id', $storeId)
                ->where('order_id', $request->order_id)
                ->exists();
            if ($exists) {
                return $this->error('Siz ushbu buyurtma uchun allaqachon baho bergansiz.', [], 400);
            }
        }

        DB::transaction(function () use ($user, $store, $request) {
            StoreRating::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'order_id' => $request->order_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            // Re-calculate store rating and review count
            $ratingsInfo = StoreRating::where('store_id', $store->id)
                ->selectRaw('COUNT(*) as count, AVG(rating) as average')
                ->first();

            $store->update([
                'rating' => round($ratingsInfo->average ?? 0, 2),
                'review_count' => $ratingsInfo->count ?? 0,
            ]);
        });

        return $this->success([
            'rating' => $store->rating,
            'review_count' => $store->review_count,
        ], 'Baholash muvaffaqiyatli saqlandi.');
    }
}
