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
}
