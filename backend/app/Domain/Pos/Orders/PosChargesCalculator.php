<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Hall\BusinessCalendar;
use App\Domain\Pos\Hall\RoomTariffResolver;
use App\Enums\KitchenItemStatus;
use App\Enums\MinSpendMode;
use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderChargeWaiver;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;

/**
 * The charges engine of a POS order (P3-12, R3, R4, R6; docs/POS_SPEC.md §2.7). One deterministic calculation from
 * the order as it stands, run after every line, guest, table or discount change (recalculate() stores it on the order):
 *
 *   items             = Σ total_price of the lines that are not void
 *   after discount    = max(0, items − orders.discount)
 *   service charge    = after discount × percent / 100, whole so'm, half-up — the percent of the order's hall
 *                       (service_fee_settings.hall_id), else the branch's, else the store's (DiningFeeCalculator)
 *   place fee         = the same settings' "joy narxi": per guest (× guests, at least 1) or per table
 *   room fee          = the room tariff of the order's table for the day type of the business date the order was
 *                       opened on (RoomTariffResolver, BusinessCalendar); no tariff: 0
 *   minimum spend     = dining_tables.min_spend (× guests, at least 1, when per_person)
 *   min spend top-up  = max(0, minimum spend − after discount), whole so'm
 *   total             = after discount + service charge + place fee + room fee + top-up
 *
 * A waived charge (order_charge_waivers) is 0. Room fee and minimum spend need a table and the hall channel. Only POS
 * channels are charged here: a Telegram or mobile order never gets any of these lines.
 */
class PosChargesCalculator
{
    public function __construct(
        protected DiningFeeCalculator $fees,
        protected BusinessCalendar $calendar,
        protected RoomTariffResolver $tariffs,
    ) {}

    public function compute(Order $order): PosCharges
    {
        $items = Money::sum(...$order->items()
            ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->pluck('total_price')
            ->all());
        $discount = Money::of($order->discount);

        if (!$order->channel->isPos()) {
            return PosCharges::none($items, $discount);
        }

        $after = Money::max(Money::zero(), $items->minus($discount));
        $guests = $order->guests_count !== null ? (int) $order->guests_count : null;
        $waived = OrderChargeWaiver::where('order_id', $order->id)->pluck('charge')
            ->map(fn ($charge) => PosCharge::tryFrom((string) $charge))->filter()->values()->all();
        $isWaived = fn (PosCharge $charge) => in_array($charge, $waived, true);

        $settings = $this->fees->settingsForHall((int) $order->store_id, $order->branch_id !== null ? (int) $order->branch_id : null, $order->hall_id);
        $dining = $settings !== null
            ? $this->fees->apply($settings, $order->channel, $after, $guests)
            : ['service_charge' => Money::zero(), 'place_fee' => Money::zero(), 'service_charge_percent' => '0.00'];

        $serviceCharge = $isWaived(PosCharge::SERVICE_CHARGE) ? Money::zero() : $dining['service_charge'];
        $percent = $serviceCharge->isPositive() ? $dining['service_charge_percent'] : '0.00';
        $placeFee = $isWaived(PosCharge::PLACE_FEE) ? Money::zero() : $dining['place_fee'];

        $roomFee = Money::zero();
        $tariffId = null;
        $businessDate = null;
        $dayType = null;
        $minSpend = Money::zero();
        $topup = Money::zero();

        $table = $order->table_id !== null ? DiningTable::withTrashed()->find($order->table_id) : null;
        $branch = $order->branch_id !== null ? Branch::with('store')->find($order->branch_id) : null;
        if ($table !== null && $branch !== null && $order->channel === OrderChannel::POS_HALL) {
            $businessDate = $this->calendar->businessDate($branch, $order->created_at ?? now());
            $dayType = $this->calendar->dayType($branch, $businessDate);

            $tariff = $this->tariffs->resolve($table, $businessDate, $dayType);
            if ($tariff !== null) {
                $tariffId = (int) $tariff->id;
                $roomFee = $isWaived(PosCharge::ROOM_FEE) ? Money::zero() : Money::of($tariff->price)->roundTo(0);
            }

            if ($table->min_spend !== null && Money::of($table->min_spend)->isPositive()) {
                $minSpend = $table->min_spend_mode === MinSpendMode::PER_PERSON
                    ? Money::of($table->min_spend)->times(max(1, (int) $guests))
                    : Money::of($table->min_spend);
                $topup = $isWaived(PosCharge::MIN_SPEND_TOPUP)
                    ? Money::zero()
                    : Money::max(Money::zero(), $minSpend->minus($after))->roundTo(0);
            }
        }

        $total = $after->plus($serviceCharge)->plus($placeFee)->plus($roomFee)->plus($topup);

        return new PosCharges($items, $discount, $after, Money::of($percent)->toString(), $serviceCharge, $placeFee, $roomFee, $tariffId, $businessDate, $dayType, $minSpend, $topup, $waived, $total);
    }

    /**
     * compute() and store the result on the order (its subtotal, charge lines and total; no commission on POS
     * channels). The caller holds the order's row lock.
     */
    public function recalculate(Order $order): PosCharges
    {
        $charges = $this->compute($order);

        if ($order->channel->isPos()) {
            $order->forceFill([
                'subtotal' => $charges->itemsSubtotal->toString(),
                'service_charge_amount' => $charges->serviceCharge->toString(),
                'place_fee_amount' => $charges->placeFee->toString(),
                'room_fee_amount' => $charges->roomFee->toString(),
                'min_spend_topup_amount' => $charges->minSpendTopup->toString(),
                'total_price' => $charges->total->toString(),
                'commission_amount' => '0.00',
            ])->save();
        }

        return $charges;
    }
}
