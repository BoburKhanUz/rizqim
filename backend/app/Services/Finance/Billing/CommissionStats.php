<?php

namespace App\Services\Finance\Billing;

use App\Enums\StoreTransactionType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-21: the commission a store paid RIZQIM in a billing month, from the ledger (the 50% discount rule, docs/BILLING.md
 * "Chegirma"): every COMMISSION entry booked in the month (Asia/Tashkent), from the wallet and onto the debt, less the
 * commission given back since for those orders (a full refund all of it, a partial one its share); the referral
 * reward's cost is not commission.
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

        $booked = fn (string $table) => DB::table("{$table} as c")
            ->where('c.store_id', $storeId)
            ->where('c.type', $commission)
            ->where('c.created_at', '>=', $from)
            ->where('c.created_at', '<', $to);
        $debtRows = fn () => $booked('store_transactions')
            ->where(fn ($query) => $query->whereNull('c.notes')->orWhere('c.notes', 'not like', self::REFERRAL_NOTE . '%'));
        $walletRows = fn () => $booked('store_wallet_transactions');

        $debt = Money::of((string) $debtRows()->sum('c.amount'));
        $wallet = Money::of((string) $walletRows()->sum('c.amount'))->abs();

        // The commission given back since, of the orders booked in the month (LedgerService::reverseDelivery, a full
        // or a partial refund): on the debt ledger a REFUND that lowers the debt, on the wallet one that credits it
        // (a settlement or payoff taken back moves the other way and is not commission)
        $orders = $debtRows()->whereNotNull('c.order_id')->select('c.order_id')
            ->union($walletRows()->whereNotNull('c.order_id')->select('c.order_id'));
        $reversedDebt = Money::of((string) DB::table('store_transactions')->where('store_id', $storeId)->where('type', $refund)
            ->where('amount', '<', 0)->whereIn('order_id', $orders)->sum('amount'))->abs();
        $reversedWallet = Money::of((string) DB::table('store_wallet_transactions')->where('store_id', $storeId)->where('type', $refund)
            ->where('amount', '>', 0)->whereIn('order_id', $orders)->sum('amount'));

        return Money::max(Money::zero(), $debt->plus($wallet)->minus($reversedDebt)->minus($reversedWallet));
    }

    /**
     * Whether the month after $period gets the discount.
     */
    public function earnsDiscount(int $storeId, string $period): bool
    {
        return $this->inMonth($storeId, $period)->isGreaterThan((string) config('billing.commission_discount.threshold', '1000000'));
    }
}
