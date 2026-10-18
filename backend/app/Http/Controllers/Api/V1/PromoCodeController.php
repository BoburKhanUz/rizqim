<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\PromoCode;

class PromoCodeController extends Controller
{
    use ApiResponse;

    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'store_id' => 'required|integer',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $code = strtoupper(trim($request->code));
        $storeId = (int) $request->store_id;
        $subtotal = (float) $request->subtotal;

        // Auto-seed default promo codes if table is empty
        if (PromoCode::count() === 0) {
            PromoCode::create([
                'code' => 'TEZFOOD',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 0,
                'is_active' => true,
            ]);
            PromoCode::create([
                'code' => 'RIZQIM_81',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 0,
                'is_active' => true,
            ]);
            PromoCode::create([
                'code' => 'YANGI2026',
                'type' => 'percentage',
                'value' => 15,
                'min_order_amount' => 0,
                'is_active' => true,
            ]);
        }

        $promo = PromoCode::where('code', $code)->first();

        if (!$promo) {
            return $this->error("Bunday promo-kod mavjud emas yoki muddati tugagan", [], 404);
        }

        if (!$promo->isValidForStore($storeId)) {
            return $this->error("Ushbu promo-kod tanlangan do'kon uchun yaroqsiz", [], 422);
        }

        if ($subtotal < $promo->min_order_amount) {
            $minFormatted = number_format($promo->min_order_amount, 0, '.', ' ');
            return $this->error("Ushbu promo-koddan foydalanish uchun minimal buyurtma summasi {$minFormatted} UZS bo'lishi kerak", [], 422);
        }

        // Calculate discount amount
        $discountAmount = 0;
        if ($promo->type === 'percentage') {
            $discountAmount = $subtotal * ($promo->value / 100);
            if ($promo->max_discount_amount && $discountAmount > $promo->max_discount_amount) {
                $discountAmount = $promo->max_discount_amount;
            }
        } else {
            $discountAmount = $promo->value;
            if ($discountAmount > $subtotal) {
                $discountAmount = $subtotal;
            }
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
