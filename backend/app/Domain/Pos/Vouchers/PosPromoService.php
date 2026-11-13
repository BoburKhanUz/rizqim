<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Orders\PosOrderService;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Promo\PromoCodeService;
use App\Support\Money;

/**
 * P6-43 (D3): a discount voucher is a store promo code applied to a POS order. The code must be the store's own
 * (a platform-wide code is the online checkout's), active, not expired, within its usage limit and the order's dishes
 * at least its minimum; the discount is the order's discount (PosOrderService::promoDiscount) with the reason
 * `promo:<CODE>` — a percentage without a cap stays a percent, a fixed amount or a capped percentage is stored as the
 * amount worked out now; with every later change of the order the charges recalculation works the discount out again
 * from the code (PosChargesCalculator::discountOf) and drops the code once the dishes fall under its minimum (review C1:
 * `pos.order.promo_dropped`, the order's `promo_note`). One code per order (409 `promo_already_applied`: take it
 * off first); a discount typed by hand later replaces it. The cashier's discount limit does not apply. Usage is
 * counted as for online orders: orders.promo_code_id (a cancelled order gives it back, PromoCode::redemptions); the
 * per-customer limit is not checked at the till (a POS order has no customer). Online only.
 */
class PosPromoService
{
    public function __construct(
        protected PosOrderService $orders,
        protected PromoCodeService $promos,
    ) {}

    /**
     * @throws VoucherException
     */
    public function apply(Order $order, int $version, User $actor, string $code): Order
    {
        $normalized = $this->promos->normalize($code);
        if ($normalized === '' || mb_strlen($normalized) > 50) {
            throw new VoucherException('promo_not_found', 'Bunday promo-kod topilmadi.', 404);
        }

        return $this->orders->promoDiscount($order, $version, $actor, function (Order $order, Money $items) use ($normalized, $actor) {
            $promo = PromoCode::where('code', $normalized)->where('store_id', $order->store_id)->lockForUpdate()->first();
            if ($promo === null) {
                throw new VoucherException('promo_not_found', 'Bunday promo-kod topilmadi.', 404);
            }
            $current = $order->getAttribute('promo_code_id');
            if ($current !== null && (int) $current !== (int) $promo->id) {
                throw new VoucherException('promo_already_applied', "Buyurtmada boshqa promo-kod bor: avval uni olib tashlang.", 409);
            }
            if (!$promo->is_active || ($promo->expires_at !== null && $promo->expires_at->isPast())) {
                throw new VoucherException('promo_inactive', "Bu promo-kod faol emas yoki muddati tugagan.", 422);
            }
            if ($items->isLessThan($promo->min_order_amount)) {
                $min = Money::of($promo->min_order_amount)->toString();
                throw new VoucherException('promo_min_order', "Bu promo-kod uchun taomlar summasi kamida {$min} so'm bo'lishi kerak.", 422, ['min_order_amount' => $min]);
            }
            if ($current === null && $promo->usage_limit !== null && $promo->redemptions()->count() >= $promo->usage_limit) {
                throw new VoucherException('promo_limit_reached', "Bu promo-koddan foydalanish limiti tugagan.", 422);
            }

            $uncapped = $promo->type === 'percentage' && Money::of($promo->max_discount_amount)->isZero();
            $discount = $this->promos->calculate($promo, $items);
            if (!$discount->isPositive()) {
                throw new VoucherException('promo_no_discount', "Bu promo-kod bu buyurtmaga chegirma bermaydi.", 422);
            }

            return [
                'discount_kind' => $uncapped ? 'percent' : 'amount',
                'discount_value' => $uncapped ? Money::of((string) $promo->value)->toString() : $discount->toString(),
                'discount_reason' => 'promo:' . $promo->code,
                'discount_by' => $actor->id,
                'promo_code_id' => $promo->id,
                'promo_note' => null,
            ];
        });
    }

    /**
     * @throws VoucherException
     */
    public function remove(Order $order, int $version, User $actor): Order
    {
        return $this->orders->promoDiscount($order, $version, $actor, function (Order $order) {
            if ($order->getAttribute('promo_code_id') === null) {
                throw new VoucherException('promo_not_applied', "Buyurtmada promo-kod yo'q.", 422);
            }

            return [
                'discount_kind' => null,
                'discount_value' => null,
                'discount_reason' => null,
                'discount_by' => null,
                'discount' => '0.00',
                'promo_code_id' => null,
                'promo_note' => null,
            ];
        });
    }
}
