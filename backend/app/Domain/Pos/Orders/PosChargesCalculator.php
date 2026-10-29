<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Billing\ServiceChargeRule;
use App\Domain\Pos\Billing\ServiceChargeRules;
use App\Domain\Pos\Billing\ServiceOrderType;
use App\Domain\Pos\Hall\BusinessCalendar;
use App\Domain\Pos\Hall\RoomTariffResolver;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Models\Booking;
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
 *   discount          = the till's discount (P3-37): a percent of the items, whole so'm, half-up, worked out again
 *                       with every line change; an amount, never more than the items. Without one, orders.discount
 *   after discount    = max(0, items − discount)
 *   service charge    = after discount × percent / 100, whole so'm, half-up — the percent of the order's hall
 *                       (service_fee_settings.hall_id), else the branch's, else the store's (DiningFeeCalculator)
 *   place fee         = the same settings' "joy narxi": per guest (× guests, at least 1) or per table
 *   room fee          = the room tariff of the order's table for the day type of the business date the order was
 *                       opened on (RoomTariffResolver, BusinessCalendar); no tariff: 0
 *   minimum spend     = the booking's own bookings.min_spend (orders.booking_id; the whole booking's, never × guests)
 *                       when set, else dining_tables.min_spend (× guests, at least 1, when per_person) — the rule the
 *                       booking confirmation announces (DiningFeeCalculator::minimumSpend)
 *   min spend top-up  = max(0, minimum spend − after discount), whole so'm
 *   total             = after discount + service charge + place fee + room fee + top-up
 *
 * P6-22 (C1): with the branch's `service_charge_rules` module, the row's service charge rules (ServiceChargeRules)
 * pick the percent — by the order's type (hall, table service, takeaway), the large-group rule — and may charge the
 * restaurant cover per guest as the place fee; a stolovaya stays at 0% (the lock wins). The bill's notice text comes
 * with the result.
 *
 * A waived charge (order_charge_waivers) is 0. Room fee and the table's minimum spend need a table and the hall channel
 * (a booking's minimum holds in the hall channel without a table). Only POS channels are charged here: a Telegram or
 * mobile order never gets any of these lines.
 */
class PosChargesCalculator
{
    public function __construct(
        protected DiningFeeCalculator $fees,
        protected BusinessCalendar $calendar,
        protected RoomTariffResolver $tariffs,
        protected PosModules $modules,
    ) {}

    public function compute(Order $order): PosCharges
    {
        $items = Money::sum(...$order->items()
            ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->pluck('total_price')
            ->all());
        if (!$order->channel->isPos()) {
            return PosCharges::none($items, Money::of($order->discount));
        }

        $discount = self::discountOf($order, $items);

        $after = Money::max(Money::zero(), $items->minus($discount));
        $guests = $order->guests_count !== null ? (int) $order->guests_count : null;
        $waived = OrderChargeWaiver::where('order_id', $order->id)->pluck('charge')
            ->map(fn ($charge) => PosCharge::tryFrom((string) $charge))->filter()->values()->all();
        $isWaived = fn (PosCharge $charge) => in_array($charge, $waived, true);

        $branchId = $order->branch_id !== null ? (int) $order->branch_id : null;
        $branch = $branchId !== null ? Branch::with('store')->find($branchId) : null;
        $settings = $this->fees->settingsForHall((int) $order->store_id, $branchId, $order->hall_id);

        // P6-22: the service charge rules of the row (the module on), resolved for this order; the notice for the bill
        $rule = ServiceChargeRule::NONE;
        $cover = false;
        $notice = null;
        if ($settings !== null) {
            $rules = $branch !== null && $this->modules->enabled($branch, PosModule::SERVICE_CHARGE_RULES) ? ServiceChargeRules::of($settings) : new ServiceChargeRules();
            $locked = $this->modules->serviceChargeLocked($branchId);
            $notice = $rules->notice($settings, $locked);
            if (!$rules->isEmpty()) {
                $resolved = $rules->resolve($settings, ServiceOrderType::of($order), $order->channel, $guests, $locked);
                $settings = $resolved['settings'];
                $rule = $resolved['rule'];
                $cover = $resolved['cover'];
            }
        }
        $dining = $settings !== null
            ? $this->fees->apply($settings, $order->channel, $after, $guests)
            : ['service_charge' => Money::zero(), 'place_fee' => Money::zero(), 'service_charge_percent' => '0.00'];

        $serviceCharge = $isWaived(PosCharge::SERVICE_CHARGE) ? Money::zero() : $dining['service_charge'];
        $percent = $serviceCharge->isPositive() ? $dining['service_charge_percent'] : '0.00';
        $placeFee = $isWaived(PosCharge::PLACE_FEE) ? Money::zero() : $dining['place_fee'];
        $rule = !$serviceCharge->isPositive() ? ServiceChargeRule::NONE : ($rule === ServiceChargeRule::NONE ? ServiceChargeRule::BASE : $rule);

        $roomFee = Money::zero();
        $tariffId = null;
        $businessDate = null;
        $dayType = null;
        $minSpend = Money::zero();
        $topup = Money::zero();

        $table = $order->table_id !== null ? DiningTable::withTrashed()->find($order->table_id) : null;
        if ($table !== null && $branch !== null && $order->channel === OrderChannel::POS_HALL) {
            $businessDate = $this->calendar->businessDate($branch, $order->created_at ?? now());
            $dayType = $this->calendar->dayType($branch, $businessDate);

            $tariff = $this->tariffs->resolve($table, $businessDate, $dayType);
            if ($tariff !== null) {
                $tariffId = (int) $tariff->id;
                $roomFee = $isWaived(PosCharge::ROOM_FEE) ? Money::zero() : Money::of($tariff->price)->roundTo(0);
            }
        }

        // The minimum spend: the booking's own (orders.booking_id; agreed with the guest, as the confirmation said), else
        // the table's — one rule with the booking confirmation (DiningFeeCalculator::minimumSpend). The hall channel only;
        // the table's needs the table, the booking's holds without one.
        if ($order->channel === OrderChannel::POS_HALL) {
            $bookingId = $order->getAttribute('booking_id');
            $agreed = $bookingId !== null ? Booking::whereKey((int) $bookingId)->value('min_spend') : null;
            $minimum = $this->fees->minimumSpend($agreed !== null ? (string) $agreed : null, $table, $guests);
            if ($minimum !== null) {
                $minSpend = $minimum['amount'];
                $topup = $isWaived(PosCharge::MIN_SPEND_TOPUP)
                    ? Money::zero()
                    : Money::max(Money::zero(), $minSpend->minus($after))->roundTo(0);
            }
        }

        $total = $after->plus($serviceCharge)->plus($placeFee)->plus($roomFee)->plus($topup);

        return new PosCharges($items, $discount, $after, Money::of($percent)->toString(), $serviceCharge, $placeFee, $roomFee, $tariffId, $businessDate, $dayType, $minSpend, $topup, $waived, $total, $rule, $cover && $placeFee->isPositive() ? PosCharges::COVER : PosCharges::PLACE_FEE, $notice);
    }

    /**
     * The order's discount on $items (P3-37): `percent` → items × value / 100, whole so'm, half-up; `amount` → the
     * value in whole so'm, at most the items; no kind → orders.discount as stored.
     */
    public static function discountOf(Order $order, Money $items): Money
    {
        $kind = $order->getAttribute('discount_kind');
        $value = $order->getAttribute('discount_value');
        if ($kind === null || $value === null) {
            return Money::of($order->discount);
        }

        $discount = $kind === 'percent'
            ? $items->percent((string) $value, 0)
            : Money::of((string) $value)->roundTo(0);

        return Money::max(Money::zero(), Money::min($discount, $items));
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
                'discount' => $charges->discount->toString(),
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
