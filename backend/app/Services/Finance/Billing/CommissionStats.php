<?php

namespace App\Services\Finance\Billing;

use App\Enums\StoreTransactionType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-21: the commission a store paid RIZQIM in a billing month, from the ledger (the 50% discount rule, docs/BILLING.md
 * "Chegirma"): every COMMISSION entry booked in the month (Asia/Tashkent), from the wallet and onto the debt, of orders
 * that were not refunded since; the referral reward's cost is not commission.
 */
class CommissionStats
{
    private const REFERRAL_NOTE = 'Referral cashback reward cost';

    public function inMonth(int $storeId, string $period): Money
    {
        $timezone = (string) config('billing.timezone', 'Asia/Tashkent');
        $from = Carbon::createFromFormat('Y-m-d H:i:s', "{$period}-01 00:00:00", $timezone);
        if (!$from instanceof Carbon) {
            return Money::zero();
        }
        $to = $from->copy()->addMonthNoOverflow();
        $from = $from->setTimezone((string) config('app.timezone'));
        $to = $to->setTimezone((string) config('app.timezone'));
        $commission = StoreTransactionType::COMMISSION->value;
        $refund = StoreTransactionType::REFUND->value;

        $refundedDebt = fn ($query) => $query->selectRaw('1')->from('store_transactions as r')
            ->whereColumn('r.order_id', 'c.order_id')->where('r.type', $refund);
        $refundedWallet = fn ($query) => $query->selectRaw('1')->from('store_wallet_transactions as rw')
            ->whereColumn('rw.order_id', 'c.order_id')->where('rw.type', $refund);

        $debt = DB::table('store_transactions as c')
            ->where('c.store_id', $storeId)
            ->where('c.type', $commission)
            ->where('c.created_at', '>=', $from)
            ->where('c.created_at', '<', $to)
            ->where(fn ($query) => $query->whereNull('c.notes')->orWhere('c.notes', 'not like', self::REFERRAL_NOTE . '%'))
            ->whereNotExists($refundedDebt)->whereNotExists($refundedWallet)
            ->sum('c.amount');

        $wallet = DB::table('store_wallet_transactions as c')
            ->where('c.store_id', $storeId)
            ->where('c.type', $commission)
            ->where('c.created_at', '>=', $from)
            ->where('c.created_at', '<', $to)
            ->whereNotExists($refundedDebt)->whereNotExists($refundedWallet)
            ->sum('c.amount');

        return Money::of((string) $debt)->plus(Money::of((string) $wallet)->abs());
    }

    /**
     * Whether the month after $period gets the discount.
     */
    public function earnsDiscount(int $storeId, string $period): bool
    {
        return $this->inMonth($storeId, $period)->isGreaterThan((string) config('billing.commission_discount.threshold', '1000000'));
    }
}
