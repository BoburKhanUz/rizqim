<?php

namespace App\Domain\Pos\Vouchers;

/**
 * P6-43: a gift certificate (sold at the till as an advance, spent as a tender) or a discount voucher. A discount
 * voucher is a store promo code (App\Models\PromoCode) applied to a POS order (PosPromoService): no `vouchers` row is
 * made for it today; the kind is kept so a printed discount voucher can be one later.
 */
enum VoucherKind: string
{
    case GIFT = 'gift';
    case DISCOUNT = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::GIFT => "Sovg'a sertifikati",
            self::DISCOUNT => 'Chegirma vaucheri',
        };
    }
}
