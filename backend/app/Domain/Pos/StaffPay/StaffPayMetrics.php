<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Booking\BookingStatus;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\CashMovementType;
use App\Models\Branch;
use App\Models\Store;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Facades\DB;

/**
 * P6-23: the work and sales behind a pay statement, per person and business day of the branch:
 *
 *  - worked: the person clocked in that day, opened a till shift of it, or had a paid bill attributed to them;
 *  - minutes: their work time (WorkTime::byDay);
 *  - sales and service charge: their paid bills — attributed to the waiter, or to whoever made the bill for an order
 *    without one — on the business day of the shift they were paid in; sales are the lines less the discount (no
 *    service charge, room fee, place fee or tips). The waiter follows the branch's SalesAttribution (owner decision
 *    30.10.2026): `at_payment` (default) — the order's waiter when the bill was paid (order_bills.waiter_id, else the
 *    order's for a bill stamped before it existed), so after a table is handed to another waiter (P6-24) the bills
 *    paid before stay with the first one; `current_waiter` — the order's waiter when the statement is computed, the
 *    whole order for the new one. A refund made in the period takes its share off both from the same person as the
 *    refunded bill, in the proportion of that bill (on the refund's business day);
 *  - events: completed bookings of the branch whose order the person served, by kind (BookingEventType), on the
 *    business day the booking started;
 *  - tips: StaffTipsSource (P6-22);
 *  - advances: wage money given to them from the branch's tills in the period (cash movements `wage`, categories
 *    advance and daily_wage; a bonus is extra and not taken off; payroll payments of statements are not advances).
 */
final class StaffPayMetrics
{
    /** Wage categories taken off a statement line as paid in advance. */
    public const ADVANCE_CATEGORIES = ['advance', 'daily_wage'];

    public function __construct(
        private readonly WorkTime $workTime,
        private readonly StaffTipsSource $tips,
        private readonly StaffPaySettings $settings,
    ) {}

    /**
     * @return array{
     *     days: array<int, array<string, array{worked: bool, minutes: int, sales: Money, service_charge: Money, events: array<string, int>, tips: Money, bills: int}>>,
     *     advances: array<int, Money>,
     *     uncounted_sessions: array<int, int>
     * }
     */
    public function collect(Branch $branch, string $from, string $to): array
    {
        $empty = fn () => ['worked' => false, 'minutes' => 0, 'sales' => Money::zero(), 'service_charge' => Money::zero(), 'events' => [], 'tips' => Money::zero(), 'bills' => 0];
        $days = [];
        $touch = function (int $userId, string $date) use (&$days, $empty) {
            $days[$userId][$date] ??= $empty();
        };

        $work = $this->workTime->byDay($branch, $from, $to);
        foreach ($work['days'] as $userId => $dates) {
            foreach (array_keys($dates) as $date) {
                $touch($userId, $date);
                $days[$userId][$date]['worked'] = true;
                $days[$userId][$date]['minutes'] = $work['minutes'][$userId][$date] ?? 0;
            }
        }

        $businessDate = $this->businessDateOf($branch);
        $atPayment = $this->settings->salesAttribution($branch) === SalesAttribution::AT_PAYMENT;
        // The person of a bill: its waiter (at payment, else the order's now), else the bill's maker
        $personOf = fn (object $row) => (int) (($atPayment ? ($row->bill_waiter_id ?? $row->waiter_id) : $row->waiter_id) ?? $row->created_by ?? 0);

        // Paid bills: the shift's business day (a bill paid outside a shift: its paid_at's)
        $bills = DB::table('order_bills')
            ->join('orders', 'orders.id', '=', 'order_bills.order_id')
            ->leftJoin('pos_shifts', 'pos_shifts.id', '=', 'order_bills.shift_id')
            ->where('order_bills.branch_id', $branch->id)
            ->where('order_bills.status', BillStatus::PAID->value)
            ->where(fn ($query) => $query->whereBetween('pos_shifts.business_date', [$from, $to])
                ->orWhere(fn ($query) => $query->whereNull('order_bills.shift_id')->whereBetween('order_bills.paid_at', $this->window($from, $to))))
            ->get(['order_bills.subtotal', 'order_bills.discount', 'order_bills.service_charge_amount', 'order_bills.paid_at', 'order_bills.created_by', 'order_bills.waiter_id as bill_waiter_id', 'orders.waiter_id', 'pos_shifts.business_date']);
        foreach ($bills as $bill) {
            $userId = $personOf($bill);
            $date = $bill->business_date !== null ? substr((string) $bill->business_date, 0, 10) : ($bill->paid_at !== null ? $businessDate((string) $bill->paid_at) : null);
            if ($userId === 0 || $date === null || $date < $from || $date > $to) {
                continue;
            }
            $touch($userId, $date);
            $day = &$days[$userId][$date];
            $day['worked'] = true;
            $day['bills']++;
            $day['sales'] = $day['sales']->plus(Money::of($bill->subtotal)->minus($bill->discount));
            $day['service_charge'] = $day['service_charge']->plus($bill->service_charge_amount);
            unset($day);
        }

        // Refunds of those bills, on the refund's business day, in the refunded bill's proportions
        $refunds = DB::table('pos_refunds')
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->leftJoin('order_bills', 'order_bills.id', '=', 'pos_refunds.bill_id')
            ->join('pos_shifts', 'pos_shifts.id', '=', 'pos_refunds.shift_id')
            ->where('pos_refunds.branch_id', $branch->id)
            ->whereBetween('pos_shifts.business_date', [$from, $to])
            ->get(['pos_refunds.amount', 'orders.waiter_id', 'order_bills.waiter_id as bill_waiter_id', 'order_bills.created_by', 'order_bills.subtotal', 'order_bills.discount', 'order_bills.service_charge_amount', 'order_bills.total', 'pos_shifts.business_date']);
        foreach ($refunds as $refund) {
            $userId = $personOf($refund);
            if ($userId === 0) {
                continue;
            }
            $date = substr((string) $refund->business_date, 0, 10);
            $amount = Money::of($refund->amount);
            $total = Money::of($refund->total ?? 0);
            if ($total->isPositive()) {
                $salesPart = Money::fromTiyin(intdiv($amount->toTiyin() * Money::of($refund->subtotal)->minus($refund->discount)->toTiyin() * 2 + $total->toTiyin(), 2 * $total->toTiyin()));
                $servicePart = Money::fromTiyin(intdiv($amount->toTiyin() * Money::of($refund->service_charge_amount)->toTiyin() * 2 + $total->toTiyin(), 2 * $total->toTiyin()));
            } else {
                [$salesPart, $servicePart] = [$amount, Money::zero()];
            }
            $touch($userId, $date);
            $days[$userId][$date]['sales'] = $days[$userId][$date]['sales']->minus($salesPart);
            $days[$userId][$date]['service_charge'] = $days[$userId][$date]['service_charge']->minus($servicePart);
        }

        // Events: completed bookings the person served
        $bookings = DB::table('bookings')
            ->join('orders', 'orders.id', '=', 'bookings.order_id')
            ->where('bookings.branch_id', $branch->id)
            ->where('bookings.status', BookingStatus::COMPLETED->value)
            ->whereNotNull('orders.waiter_id')
            ->whereBetween('bookings.starts_at', $this->window($from, $to))
            ->get(['bookings.starts_at', 'bookings.event_type', 'orders.waiter_id']);
        foreach ($bookings as $booking) {
            $date = $businessDate((string) $booking->starts_at);
            if ($date < $from || $date > $to) {
                continue;
            }
            $userId = (int) $booking->waiter_id;
            $touch($userId, $date);
            $days[$userId][$date]['events'][(string) $booking->event_type] = ($days[$userId][$date]['events'][(string) $booking->event_type] ?? 0) + 1;
        }

        foreach ($this->tips->tipsByDay($branch, $from, $to) as $userId => $byDate) {
            foreach ($byDate as $date => $amount) {
                if ($date < $from || $date > $to) {
                    continue;
                }
                $touch((int) $userId, $date);
                $days[$userId][$date]['tips'] = $days[$userId][$date]['tips']->plus($amount);
            }
        }

        $advances = [];
        $movements = DB::table('cash_movements')
            ->join('pos_shifts', 'pos_shifts.id', '=', 'cash_movements.shift_id')
            ->where('cash_movements.branch_id', $branch->id)
            ->where('cash_movements.type', CashMovementType::WAGE->value)
            ->whereIn('cash_movements.category', self::ADVANCE_CATEGORIES)
            ->whereNotNull('cash_movements.employee_id')
            ->whereBetween('pos_shifts.business_date', [$from, $to])
            ->get(['cash_movements.employee_id', 'cash_movements.amount']);
        foreach ($movements as $movement) {
            $advances[(int) $movement->employee_id] = ($advances[(int) $movement->employee_id] ?? Money::zero())->plus($movement->amount);
        }

        return ['days' => $days, 'advances' => $advances, 'uncounted_sessions' => $work['uncounted']];
    }

    /**
     * A moment's business date in the branch.
     *
     * @return \Closure(string): string
     */
    private function businessDateOf(Branch $branch): \Closure
    {
        $tz = StoreTimezone::of(Store::find($branch->store_id));
        $start = PosSettings::timeOf($branch->getAttribute('business_day_starts_at'));

        return fn (string $at) => PosSettings::businessDate(new \DateTimeImmutable($at, new \DateTimeZone('UTC')), $tz, $start);
    }

    /**
     * Moments (UTC) wide enough to hold every moment of the business days: a day either side.
     *
     * @return array{0: string, 1: string}
     */
    private function window(string $from, string $to): array
    {
        return [
            (new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d H:i:s'),
            (new \DateTimeImmutable($to . ' 23:59:59', new \DateTimeZone('UTC')))->modify('+2 day')->format('Y-m-d H:i:s'),
        ];
    }
}
