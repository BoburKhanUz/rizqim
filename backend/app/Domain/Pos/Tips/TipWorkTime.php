<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Setup\PosSettings;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\Store;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P6-22: who worked in a tip pool's period and for how many minutes (the `equal` and `hours` rules), from what the
 * branch already records:
 *
 *  1. the staff's clock-in/out sessions (P6-23 staff_work_sessions; an open one counts until now, one set aside
 *     not at all);
 *  2. the till shifts each cashier opened (open: until now);
 *  3. someone with neither but who served orders (orders.waiter_id) or took payments (payments.received_by) in the
 *     period: from their first to their last such action.
 *
 * Each person's pieces are clipped to the period and merged (an overlap counted once). The period: the business day
 * (from the branch's day start, in the store's time zone, 24 hours) or the shift (opened → closed, or now).
 */
final class TipWorkTime
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(Branch $branch, string $date, ?PosShift $shift): array
    {
        if ($shift !== null) {
            return [CarbonImmutable::instance($shift->opened_at), CarbonImmutable::instance($shift->closed_at ?? now())];
        }
        $store = Store::find($branch->store_id);
        [$hour, $minute] = array_map('intval', explode(':', PosSettings::timeOf($branch->getAttribute('business_day_starts_at'))));
        $start = CarbonImmutable::parse($date, StoreTimezone::of($store))->setTime($hour, $minute);

        return [$start->utc(), $start->addDay()->utc()];
    }

    /**
     * @return array<int, int> user id => minutes
     */
    public function minutes(Branch $branch, string $date, ?PosShift $shift): array
    {
        [$from, $to] = self::window($branch, $date, $shift);
        $now = CarbonImmutable::now();
        $intervals = [];
        $clip = function (int $userId, mixed $start, mixed $end) use (&$intervals, $from, $to): void {
            $s = max(CarbonImmutable::parse($start)->getTimestamp(), $from->getTimestamp());
            $e = min(CarbonImmutable::parse($end)->getTimestamp(), $to->getTimestamp());
            if ($e > $s) {
                $intervals[$userId][] = [$s, $e];
            }
        };

        if (Schema::hasTable('staff_work_sessions')) {
            $sessions = DB::table('staff_work_sessions')->where('branch_id', $branch->id)->whereNull('abandoned_at')
                ->when($shift === null, fn ($query) => $query->whereDate('business_date', $date))
                ->when($shift !== null, fn ($query) => $query->where('clock_in_at', '<', $to)->where(fn ($query) => $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>', $from)))
                ->get(['user_id', 'clock_in_at', 'clock_out_at']);
            foreach ($sessions as $session) {
                $clip((int) $session->user_id, $session->clock_in_at, $session->clock_out_at ?? $now);
            }
        }

        $shifts = PosShift::where('branch_id', $branch->id)->whereNotNull('opened_by')
            ->when($shift === null, fn ($query) => $query->whereDate('business_date', $date))
            ->when($shift !== null, fn ($query) => $query->whereKey($shift->id))
            ->get(['opened_by', 'opened_at', 'closed_at']);
        foreach ($shifts as $row) {
            $clip((int) $row->getAttribute('opened_by'), $row->opened_at, $row->closed_at ?? $now);
        }

        // Neither a session nor a shift: the first to the last order served or payment taken in the period
        $activity = [];
        $orders = DB::table('orders')->where('branch_id', $branch->id)->whereNotNull('waiter_id')
            ->when($shift === null, fn ($query) => $query->whereDate('business_date', $date))
            ->when($shift !== null, fn ($query) => $query->where('shift_id', $shift->id))
            ->groupBy('waiter_id')->selectRaw('waiter_id as user_id, min(created_at) as first_at, max(coalesce(closed_at, updated_at)) as last_at')->get();
        $shiftIds = $shift !== null ? [$shift->id] : PosShift::where('branch_id', $branch->id)->whereDate('business_date', $date)->pluck('id')->all();
        $payments = DB::table('payments')->whereIn('shift_id', $shiftIds)->whereNotNull('received_by')
            ->groupBy('received_by')->selectRaw('received_by as user_id, min(paid_at) as first_at, max(paid_at) as last_at')->get();
        foreach ($orders->merge($payments) as $row) {
            $userId = (int) $row->user_id;
            if (isset($intervals[$userId]) || $row->first_at === null) {
                continue;
            }
            $activity[$userId][] = [$row->first_at, $row->last_at ?? $row->first_at];
        }
        foreach ($activity as $userId => $pieces) {
            foreach ($pieces as [$start, $end]) {
                $clip($userId, $start, $end);
            }
            $intervals[$userId] ??= [];
        }

        $minutes = [];
        foreach ($intervals as $userId => $list) {
            $minutes[$userId] = intdiv(self::merged($list), 60);
        }
        ksort($minutes);

        return $minutes;
    }

    /**
     * Seconds covered by the intervals, overlaps once.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private static function merged(array $intervals): int
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = 0;
        $start = null;
        $end = null;
        foreach ($intervals as [$s, $e]) {
            if ($end === null || $s > $end) {
                $total += $end !== null ? $end - $start : 0;
                [$start, $end] = [$s, $e];
            } else {
                $end = max($end, $e);
            }
        }

        return $total + ($end !== null ? $end - $start : 0);
    }
}
