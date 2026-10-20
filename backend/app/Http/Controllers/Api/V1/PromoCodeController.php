<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Services\Promo\InvalidPromoCodeException;
use App\Services\Promo\PromoCodeService;

class PromoCodeController extends Controller
{
    use ApiResponse;

    /**
     * Preview only: the discount is recalculated and the limits re-checked under lock when the order is placed.
     */
    public function validateCode(Request $request, PromoCodeService $promoCodes): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'store_id' => 'required|integer',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        $subtotal = $request->filled('subtotal') ? (float) $request->subtotal : null;

        try {
            $promo = $promoCodes->find($request->code);
            $discountAmount = $promoCodes->discountFor($promo, (int) $request->store_id, $subtotal, $request->user()?->id);
        } catch (InvalidPromoCodeException $e) {
            return $this->error($e->getMessage(), [], $e->status);
        }

        $description = $promo->type === 'percentage' 
            ? "Buyurtmaga {$promo->value}% chegirma" 
            : "Buyurtmaga " . number_format($promo->value, 0, '.', ' ') . " UZS chegirma";

        return $this->success([
            'id' => $promo->id,
            'code' => $promo->code,
            'type' => $promo->type,
            'amount' => $promo->value,
            'discount_amount' => $discountAmount,
            'description' => $description,
        ], "Promo-kod muvaffaqiyatli qo'llanildi");
    }
}
