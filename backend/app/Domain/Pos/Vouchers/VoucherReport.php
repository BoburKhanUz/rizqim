<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\Store;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Support\Money;
use App\Support\StoreTimezone;

/**
 * P6-43: the owner's certificate report for business days `from`..`to` (one branch, or the whole store): what was
 * sold (the advances, by tender), what bills took from certificates (redeemed) and what refunds gave back to them,
 * and — as of today — the outstanding liability (the balance of certificates that can still pay a bill), with what is
 * blocked and what expired unused apart. "Today" is the business day of the branch a certificate was sold in (review
 * C4; `as_of`: the branch's, the earliest of the branches' when store-wide). Money as decimal strings.
 */
final class VoucherReport
{
    public const REPORT = 'array{from: string, to: string, branch_id: int|null, sold: array{count: int, amount: string, by_tender: list<array{tender: string, count: int, amount: string}>}, redeemed: array{count: int, amount: string}, refunded: array{count: int, amount: string}, net_change: string, outstanding: array{count: int, amount: string}, blocked: array{count: int, amount: string}, expired: array{count: int, amount: string}, as_of: string}';

    /**
     * @return array<string, mixed>
     */
    public function build(int $storeId, ?int $branchId, string $from, string $to): array
    {
        $entries = VoucherEntry::where('store_id', $storeId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('business_date', [$from, $to])
            ->whereIn('type', [VoucherEntryType::SALE->value, VoucherEntryType::REDEMPTION->value, VoucherEntryType::REFUND->value])
            ->get(['type', 'amount', 'tender']);
        $sum = fn ($rows) => Money::sum(...$rows->pluck('amount')->all());

        $sales = $entries->where('type', VoucherEntryType::SALE);
        $redeemed = $entries->where('type', VoucherEntryType::REDEMPTION);
        $refunded = $entries->where('type', VoucherEntryType::REFUND);
        $byTender = [];
        foreach (VoucherService::SALE_TENDERS as $tender) {
            $rows = $sales->where('tender', $tender->value);
            $byTender[] = ['tender' => $tender->value, 'count' => $rows->count(), 'amount' => $sum($rows)->toString()];
        }

        // The liability today: certificates sold in the branch (or anywhere in the store). Review C4: "today" is each
        // branch's business day (VoucherState: 00:00–05:00 still the day before), the store's date for one sold nowhere
        $storeToday = now()->setTimezone(StoreTimezone::of(Store::find($storeId)))->toDateString();
        $days = Branch::where('store_id', $storeId)->when($branchId !== null, fn ($query) => $query->whereKey($branchId))->get()
            ->mapWithKeys(fn (Branch $branch) => [(int) $branch->id => BusinessDay::of($branch)])->all();
        $today = $days !== [] ? min($days) : $storeToday;
        $vouchers = Voucher::where('store_id', $storeId)->where('kind', VoucherKind::GIFT->value)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('balance', '>', 0);
        // Past its last day on the business day of the branch it was sold in
        $pastDue = function ($query) use ($days, $storeToday, $branchId) {
            $query->where(function ($query) use ($days, $storeToday, $branchId) {
                foreach ($days as $id => $day) {
                    $query->orWhere(fn ($query) => $query->where('branch_id', $id)->where('valid_until', '<', $day));
                }
                if ($branchId === null) {
                    $query->orWhere(fn ($query) => $query->whereNull('branch_id')->where('valid_until', '<', $storeToday));
                }
            });
        };
        $live = (clone $vouchers)->where('status', VoucherStatus::ACTIVE->value)->where(fn ($query) => $query->whereNull('valid_until')->orWhereNot($pastDue));
        $expired = (clone $vouchers)->where(fn ($query) => $query->where('status', VoucherStatus::EXPIRED->value)
            ->orWhere(fn ($query) => $query->where('status', VoucherStatus::ACTIVE->value)->whereNotNull('valid_until')->where($pastDue)));
        $blocked = (clone $vouchers)->where('status', VoucherStatus::BLOCKED->value);
        $total = fn ($query) => ['count' => (clone $query)->count(), 'amount' => Money::of((string) (clone $query)->sum('balance'))->toString()];

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'sold' => ['count' => $sales->count(), 'amount' => $sum($sales)->toString(), 'by_tender' => $byTender],
            'redeemed' => ['count' => $redeemed->count(), 'amount' => $sum($redeemed)->toString()],
            'refunded' => ['count' => $refunded->count(), 'amount' => $sum($refunded)->toString()],
            // What the period added to the liability
            'net_change' => $sum($sales)->minus($sum($redeemed))->plus($sum($refunded))->toString(),
            'outstanding' => $total($live),
            'blocked' => $total($blocked),
            'expired' => $total($expired),
            'as_of' => $today,
        ];
    }
}
