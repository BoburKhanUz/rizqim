<?php

namespace App\Services\Order;

use App\Enums\OrderChannel;
use App\Enums\PlaceFeeMode;
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
        return ServiceFeeSetting::query()
            ->where('store_id', $storeId)
            ->where(fn ($query) => $query->whereNull('branch_id')->when($branchId !== null, fn ($query) => $query->orWhere('branch_id', $branchId)))
            ->orderByRaw('branch_id IS NULL') // the branch's own row first
            ->first();
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
     * @param  list<string>|null  $channels  null: DEFAULT_CHANNELS
     */
    private function appliesTo(?array $channels, OrderChannel $channel): bool
    {
        return $channel !== OrderChannel::TELEGRAM && in_array($channel->value, $channels ?? self::DEFAULT_CHANNELS, true);
    }
}
