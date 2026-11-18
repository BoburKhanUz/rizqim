<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\Store;
use App\Models\Voucher;
use App\Support\StoreTimezone;

/**
 * P6-43: a voucher's effective status: the stored one, `expired` once the business date (of the branch it is used
 * in, else of the branch it was sold in, else the store's local date) is past its valid_until.
 */
final class VoucherState
{
    public static function of(Voucher $voucher, ?Branch $branch = null): VoucherStatus
    {
        if ($voucher->status !== VoucherStatus::ACTIVE || $voucher->valid_until === null) {
            return $voucher->status;
        }

        return self::today($voucher, $branch) > $voucher->valid_until->toDateString() ? VoucherStatus::EXPIRED : VoucherStatus::ACTIVE;
    }

    /**
     * The business date (Y-m-d) the voucher is looked at on.
     */
    public static function today(Voucher $voucher, ?Branch $branch = null): string
    {
        $branch ??= $voucher->branch_id !== null ? Branch::find($voucher->branch_id) : null;
        if ($branch !== null) {
            return BusinessDay::of($branch);
        }

        return now()->setTimezone(StoreTimezone::of(Store::find($voucher->store_id)))->toDateString();
    }

    /**
     * Wave M3: "today" for the owner's views of a store's certificates (one branch's, or all): each branch's business
     * day (00:00-05:00 still the day before) and the store's local date for a certificate sold nowhere, as self::today
     * reads them for one certificate.
     *
     * @return array{0: array<int, string>, 1: string}
     */
    public static function todays(int $storeId, ?int $branchId = null): array
    {
        $days = Branch::where('store_id', $storeId)->when($branchId !== null, fn ($query) => $query->whereKey($branchId))->get()
            ->mapWithKeys(fn (Branch $branch) => [(int) $branch->id => BusinessDay::of($branch)])->all();

        return [$days, now()->setTimezone(StoreTimezone::of(Store::find($storeId)))->toDateString()];
    }

    /**
     * Wave M3: self::of's expiry as a query condition: past its last day on the business day of the branch it was sold
     * in ($days from self::todays), on the store's local date when sold nowhere (store-wide views only).
     *
     * @param  array<int, string>  $days
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<Voucher>): void
     */
    public static function pastDue(array $days, string $storeToday, ?int $branchId): \Closure
    {
        return function ($query) use ($days, $storeToday, $branchId): void {
            $query->where(function ($query) use ($days, $storeToday, $branchId) {
                foreach ($days as $id => $day) {
                    $query->orWhere(fn ($query) => $query->where('branch_id', $id)->where('valid_until', '<', $day));
                }
                if ($branchId === null) {
                    $query->orWhere(fn ($query) => $query->whereNull('branch_id')->where('valid_until', '<', $storeToday));
                }
            });
        };
    }
}
