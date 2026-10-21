<?php

namespace App\Domain\Pos\Orders;

use App\Enums\DayType;
use App\Support\Money;

/**
 * The money of a POS order as PosChargesCalculator works it out (P3-12): the dishes, the discount, and each charge line
 * (zero when it does not apply or was waived), with what it was based on.
 *
 *   total = max(0, items − discount) + service charge + place fee + room fee + minimum spend top-up
 */
final class PosCharges
{
    /**
     * @param  list<PosCharge>  $waived
     */
    public function __construct(
        public readonly Money $itemsSubtotal,
        public readonly Money $discount,
        public readonly Money $itemsAfterDiscount,
        public readonly string $serviceChargePercent,
        public readonly Money $serviceCharge,
        public readonly Money $placeFee,
        public readonly Money $roomFee,
        public readonly ?int $roomTariffId,
        public readonly ?string $businessDate,
        public readonly ?DayType $dayType,
        public readonly Money $minSpend,
        public readonly Money $minSpendTopup,
        public readonly array $waived,
        public readonly Money $total,
    ) {}

    public static function none(Money $itemsSubtotal, Money $discount): self
    {
        $after = Money::max(Money::zero(), $itemsSubtotal->minus($discount));

        return new self($itemsSubtotal, $discount, $after, '0.00', Money::zero(), Money::zero(), Money::zero(), null, null, null, Money::zero(), Money::zero(), [], $after);
    }

    public function amountOf(PosCharge $charge): Money
    {
        return match ($charge) {
            PosCharge::SERVICE_CHARGE => $this->serviceCharge,
            PosCharge::PLACE_FEE => $this->placeFee,
            PosCharge::ROOM_FEE => $this->roomFee,
            PosCharge::MIN_SPEND_TOPUP => $this->minSpendTopup,
        };
    }

    /**
     * @return array{items_subtotal: string, discount: string, service_charge_percent: string, service_charge_amount: string, place_fee_amount: string, room_fee_amount: string, room_tariff_id: int|null, business_date: string|null, day_type: string|null, min_spend: string, min_spend_topup_amount: string, waived: list<string>, total: string}
     */
    public function toArray(): array
    {
        return [
            'items_subtotal' => $this->itemsSubtotal->toString(),
            'discount' => $this->discount->toString(),
            'service_charge_percent' => $this->serviceChargePercent,
            'service_charge_amount' => $this->serviceCharge->toString(),
            'place_fee_amount' => $this->placeFee->toString(),
            'room_fee_amount' => $this->roomFee->toString(),
            'room_tariff_id' => $this->roomTariffId,
            'business_date' => $this->businessDate,
            'day_type' => $this->dayType?->value,
            'min_spend' => $this->minSpend->toString(),
            'min_spend_topup_amount' => $this->minSpendTopup->toString(),
            'waived' => array_map(fn (PosCharge $charge) => $charge->value, $this->waived),
            'total' => $this->total->toString(),
        ];
    }
}
