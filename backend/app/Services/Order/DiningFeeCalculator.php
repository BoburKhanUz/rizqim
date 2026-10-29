<?php

namespace App\Services\Order;

use App\Domain\Pos\Setup\PosModules;
use App\Enums\MinSpendMode;
use App\Enums\OrderChannel;
use App\Enums\PlaceFeeMode;
use App\Models\DiningTable;
use App\Models\ServiceFeeSetting;
use App\Support\Money;

/**
 * Service charge (xizmat haqi) and place fee (joy narxi) of an order, prepared for the POS hall (P1-21). The settings
 * of the branch win over the store's; each fee applies only to its channels (pos_hall unless configured) and never to
 * Telegram orders. Both are separate lines of the order (service_charge_amount, place_fee_amount), rounded half-up to
 * whole so'm:
 *   service charge = items subtotal after discount × percent / 100
 *   place fee      = amount × guests (per_person, at least 1 guest) or amount (per_table)
 */
class DiningFeeCalculator
{
    public const DEFAULT_CHANNELS = [OrderChannel::POS_HALL->value];

    public function settingsFor(int $storeId, ?int $branchId): ?ServiceFeeSetting
    {
        return $this->lawful(ServiceFeeSetting::query()
            ->where('store_id', $storeId)
            ->whereNull('hall_id') // P3-12: a hall's row applies in that hall only (settingsForHall)
            ->where(fn ($query) => $query->whereNull('branch_id')->when($branchId !== null, fn ($query) => $query->orWhere('branch_id', $branchId)))
            ->orderByRaw('branch_id IS NULL') // the branch's own row first
            ->first(), $branchId);
    }

    /**
     * P6-11: a stolovaya charges no service charge (VM 75, the format profile's lock): its settings read with 0%
     * whatever the store's or a hall's row says (a copy, never saved); the place fee stays.
     */
    private function lawful(?ServiceFeeSetting $settings, ?int $branchId): ?ServiceFeeSetting
    {
        $branchId ??= $settings?->branch_id !== null ? (int) $settings->branch_id : null;
        if ($settings === null || !Money::of((string) $settings->service_charge_percent)->isPositive()
            || !app(PosModules::class)->serviceChargeLocked($branchId)) {
            return $settings;
        }

        $copy = clone $settings;
        $copy->setAttribute('service_charge_percent', '0.00');

        return $copy;
    }

    /**
     * P3-12: the settings in force in a hall (R6): the hall's own row (VIP rooms another percent, a self-service zone
     * 0%), else the branch's, else the store's.
     */
    public function settingsForHall(int $storeId, ?int $branchId, ?int $hallId): ?ServiceFeeSetting
    {
        if ($hallId !== null) {
            $own = ServiceFeeSetting::query()->where('store_id', $storeId)->where('hall_id', $hallId)->first();
            if ($own !== null) {
                return $this->lawful($own, $branchId);
            }
        }

        return $this->settingsFor($storeId, $branchId);
    }

    /**
     * @return array{service_charge: Money, place_fee: Money, service_charge_percent: string}
     */
    public function calculate(int $storeId, ?int $branchId, OrderChannel $channel, Money $itemsAfterDiscount, ?int $guests): array
    {
        $none = ['service_charge' => Money::zero(), 'place_fee' => Money::zero(), 'service_charge_percent' => '0.00'];

        if ($channel === OrderChannel::TELEGRAM) {
            return $none;
        }

        $settings = $this->settingsFor($storeId, $branchId);

        return $settings ? $this->apply($settings, $channel, $itemsAfterDiscount, $guests) : $none;
    }

    /**
     * @return array{service_charge: Money, place_fee: Money, service_charge_percent: string}
     */
    public function apply(ServiceFeeSetting $settings, OrderChannel $channel, Money $itemsAfterDiscount, ?int $guests): array
    {
        $serviceCharge = Money::zero();
        $placeFee = Money::zero();
        $percent = (string) $settings->service_charge_percent;

        if ($channel !== OrderChannel::TELEGRAM) {
            if ($this->appliesTo($settings->service_charge_channels, $channel) && Money::of($percent)->isPositive() && $itemsAfterDiscount->isPositive()) {
                $serviceCharge = $itemsAfterDiscount->percent($percent, 0);
            }

            $amount = Money::of($settings->place_fee_amount);
            if ($this->appliesTo($settings->place_fee_channels, $channel) && $amount->isPositive()) {
                $placeFee = $settings->place_fee_mode === PlaceFeeMode::PER_TABLE
                    ? $amount->roundTo(0)
                    : $amount->times(max(1, (int) $guests))->roundTo(0);
            }
        }

        return [
            'service_charge' => $serviceCharge,
            'place_fee' => $placeFee,
            'service_charge_percent' => $serviceCharge->isPositive() ? $percent : '0.00',
        ];
    }

    /**
     * P3-38: what a guest is told ahead (the booking confirmation): the service charge percent that will apply on
     * `$channel` ("0.00": none) and the place fee for `$guests`, each only where these settings apply it to the channel.
     *
     * @return array{service_charge_percent: string, place_fee: Money}
     */
    public function announced(ServiceFeeSetting $settings, OrderChannel $channel, ?int $guests): array
    {
        $percent = (string) $settings->service_charge_percent;
        $charged = $this->appliesTo($settings->service_charge_channels, $channel) && Money::of($percent)->isPositive();

        return [
            'service_charge_percent' => $charged ? Money::of($percent)->toString() : '0.00',
            'place_fee' => $this->apply($settings, $channel, Money::zero(), $guests)['place_fee'],
        ];
    }

    /**
     * The minimum spend ("obyazalovka", R4) in force: the booking's own amount agreed with the guest
     * (bookings.min_spend) when it is set and above 0 — for the whole booking, never × guests — else the room's
     * (dining_tables.min_spend, × `$guests` (at least 1) when per_person); null when neither has one. The one rule for
     * what the booking confirmation tells the guest (P3-38) and what the order charges (PosChargesCalculator), so the two
     * cannot drift. `per_person` is the room's amount for one guest when the room's per-person minimum applies.
     *
     * @return array{amount: Money, source: 'booking'|'room', per_person: Money|null}|null
     */
    public function minimumSpend(?string $bookingMinSpend, ?DiningTable $room, ?int $guests): ?array
    {
        if ($bookingMinSpend !== null && Money::of($bookingMinSpend)->isPositive()) {
            return ['amount' => Money::of($bookingMinSpend), 'source' => 'booking', 'per_person' => null];
        }

        if ($room === null || $room->min_spend === null || !Money::of($room->min_spend)->isPositive()) {
            return null;
        }

        $amount = Money::of($room->min_spend);

        return $room->min_spend_mode === MinSpendMode::PER_PERSON
            ? ['amount' => $amount->times(max(1, (int) $guests)), 'source' => 'room', 'per_person' => $amount]
            : ['amount' => $amount, 'source' => 'room', 'per_person' => null];
    }

    /**
     * @param  list<string>|null  $channels  null: DEFAULT_CHANNELS
     */
    private function appliesTo(?array $channels, OrderChannel $channel): bool
    {
        return $channel !== OrderChannel::TELEGRAM && in_array($channel->value, $channels ?? self::DEFAULT_CHANNELS, true);
    }
}
