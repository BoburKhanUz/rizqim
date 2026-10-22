<?php

namespace App\Domain\Pos\Orders;

/**
 * The charge lines of a POS order besides its dishes (P3-12): what PosChargesCalculator works out and what staff may
 * waive with a reason (order_charge_waivers). Each is its own line on the bill and the receipt (fiscal MXIK per charge,
 * App\Domain\Fiscal\FiscalLineBuilder::forCharge).
 */
enum PosCharge: string
{
    case SERVICE_CHARGE = 'service_charge';
    case PLACE_FEE = 'place_fee';
    case ROOM_FEE = 'room_fee';
    case MIN_SPEND_TOPUP = 'min_spend_topup';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_CHARGE => 'Xizmat haqi',
            self::PLACE_FEE => 'Joy narxi',
            self::ROOM_FEE => 'Xona puli',
            self::MIN_SPEND_TOPUP => 'Minimal summa farqi',
        };
    }

    /**
     * The orders column holding the amount.
     */
    public function column(): string
    {
        return match ($this) {
            self::SERVICE_CHARGE => 'service_charge_amount',
            self::PLACE_FEE => 'place_fee_amount',
            self::ROOM_FEE => 'room_fee_amount',
            self::MIN_SPEND_TOPUP => 'min_spend_topup_amount',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $charge) => $charge->value, self::cases());
    }
}
