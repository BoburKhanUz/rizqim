<?php

namespace App\Services\Promo;

use App\Models\PromoCode;
use App\Support\Money;

class PromoCodeService
{
    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @throws InvalidPromoCodeException
     */
    public function find(string $code, bool $lockForUpdate = false): PromoCode
    {
        $promo = PromoCode::where('code', $this->normalize($code))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if (!$promo) {
            throw new InvalidPromoCodeException("Bunday promo-kod mavjud emas yoki muddati tugagan", 404);
        }

        return $promo;
    }

    /**
     * Checks every rule for this store, customer and items subtotal and returns the discount.
     * Without a subtotal (preview) the minimum amount check is skipped and null is returned.
     *
     * @throws InvalidPromoCodeException
     */
    public function discountFor(PromoCode $promo, int $storeId, Money|string|int|float|null $subtotal, ?int $userId): ?Money
    {
        if (!$promo->is_active || ($promo->expires_at && $promo->expires_at->isPast())) {
            throw new InvalidPromoCodeException("Bu promo-kod faol emas yoki muddati tugagan");
        }

        if (!$promo->isValidForStore($storeId)) {
            throw new InvalidPromoCodeException("Ushbu promo-kod tanlangan do'kon uchun yaroqsiz");
        }

        if ($subtotal !== null && Money::of($subtotal)->isLessThan($promo->min_order_amount)) {
            $minFormatted = number_format($promo->min_order_amount, 0, '.', ' ');
            throw new InvalidPromoCodeException("Ushbu promo-koddan foydalanish uchun minimal buyurtma summasi {$minFormatted} UZS bo'lishi kerak");
        }

        if ($promo->usage_limit !== null && $promo->redemptions()->count() >= $promo->usage_limit) {
            throw new InvalidPromoCodeException("Ushbu promo-koddan foydalanish limiti tugagan");
        }

        if ($userId !== null && $promo->per_user_limit !== null
            && $promo->redemptions()->where('user_id', $userId)->count() >= $promo->per_user_limit) {
            throw new InvalidPromoCodeException("Siz ushbu promo-koddan foydalanib bo'lgansiz");
        }

        return $subtotal === null ? null : $this->calculate($promo, $subtotal);
    }

    /**
     * Discount in whole UZS, never more than the items subtotal (the delivery fee is not discounted).
     */
    public function calculate(PromoCode $promo, Money|string|int|float $subtotal): Money
    {
        $subtotal = Money::of($subtotal);

        if ($promo->type === 'percentage') {
            $discount = $subtotal->percent($promo->value, 0);
            $cap = Money::of($promo->max_discount_amount);
            if (!$cap->isZero() && $discount->isGreaterThan($cap)) {
                $discount = $cap;
            }
        } else {
            $discount = Money::of($promo->value)->roundTo(0);
        }

        return Money::max(Money::zero(), Money::min($discount, $subtotal));
    }
}
