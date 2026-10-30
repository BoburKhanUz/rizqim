<?php

namespace App\Domain\Pos\Tips;

use App\Models\Branch;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use App\Models\PosTipDistributionLine;
use App\Models\User;
use App\Support\Money;

/**
 * P6-22: the owner's tips report of a branch for a range of business days (GET /owner/pos/tips), separate from the
 * sales reports (tips are not revenue): the totals (ShiftTips::summarize), each day with its pool and its split, and
 * each person's personal tips and pool shares (from the splits computed so far, `pool_share_closed` of the closed
 * ones). The staff pay rules (P6-23) read the same ledger (App\Domain\Pos\StaffPay\PosTipsStaffTips).
 */
final class TipsReport
{
    public const VIEW = 'array{branch_id: int, from: string, to: string, totals: ' . ShiftTips::SHAPE . ', by_day: list<array{business_date: string, amount: string, personal: string, pool: string, cash: string, card: string, qr: string, distributions: list<array{uuid: string, shift_uuid: string|null, status: string, pool_amount: string, distributed: string}>, undistributed: string}>, by_staff: list<array{staff_id: int, name: string|null, role: string|null, personal: string, pool_share: string, pool_share_closed: string, total: string}>, undistributed: string, staff: list<array{id: int, name: string, role: string}>}';

    /**
     * @return array<string, mixed>
     */
    public function build(Branch $branch, string $from, string $to): array
    {
        $rows = PosTip::where('branch_id', $branch->id)->whereBetween('business_date', [$from, $to])->orderBy('id')->get();
        $distributions = PosTipDistribution::where('branch_id', $branch->id)->whereBetween('business_date', [$from, $to])->orderBy('business_date')->orderBy('id')->get();
        $lines = PosTipDistributionLine::whereIn('distribution_id', $distributions->pluck('id'))->get();
        $linesOf = $lines->groupBy('distribution_id');
        $shiftUuids = \App\Models\PosShift::whereIn('id', $distributions->pluck('shift_id')->filter())->pluck('uuid', 'id');
        $sum = fn ($rows) => Money::sum('0', ...collect($rows)->pluck('amount')->all());

        $days = [];
        $undistributedTotal = Money::zero();
        foreach ($rows->groupBy(fn (PosTip $row) => $row->business_date->toDateString())->sortKeys() as $date => $dayRows) {
            $pool = $sum($dayRows->filter(fn (PosTip $row) => $row->mode === TipMode::POOL));
            $dayDistributions = $distributions->filter(fn (PosTipDistribution $d) => $d->business_date->toDateString() === $date);
            $distributed = $dayDistributions->reduce(fn (Money $carry, PosTipDistribution $d) => $carry->plus($sum($linesOf->get($d->id, collect()))), Money::zero());
            $undistributed = Money::max(Money::zero(), $pool->minus($distributed));
            $undistributedTotal = $undistributedTotal->plus($undistributed);
            $days[] = [
                'business_date' => (string) $date,
                'amount' => $sum($dayRows)->toString(),
                'personal' => $sum($dayRows->filter(fn (PosTip $row) => $row->mode === TipMode::PERSONAL))->toString(),
                'pool' => $pool->toString(),
                'cash' => $sum($dayRows->filter(fn (PosTip $row) => $row->method === TipMethod::CASH))->toString(),
                'card' => $sum($dayRows->filter(fn (PosTip $row) => $row->method === TipMethod::CARD))->toString(),
                'qr' => $sum($dayRows->filter(fn (PosTip $row) => $row->method === TipMethod::QR))->toString(),
                'distributions' => $dayDistributions->map(fn (PosTipDistribution $d) => [
                    'uuid' => $d->uuid,
                    'shift_uuid' => $d->shift_id !== null ? ($shiftUuids[$d->shift_id] ?? null) : null,
                    'status' => $d->status->value,
                    'pool_amount' => Money::of($d->pool_amount)->toString(),
                    'distributed' => $sum($linesOf->get($d->id, collect()))->toString(),
                ])->values()->all(),
                'undistributed' => $undistributed->toString(),
            ];
        }

        $closedIds = $distributions->filter(fn (PosTipDistribution $d) => $d->status === TipDistributionStatus::CLOSED)->pluck('id')->all();
        $personal = $rows->filter(fn (PosTip $row) => $row->mode === TipMode::PERSONAL && $row->staff_id !== null)->groupBy('staff_id');
        $shares = $lines->filter(fn (PosTipDistributionLine $line) => $line->staff_id !== null)->groupBy('staff_id');
        $ids = $personal->keys()->merge($shares->keys())->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $users = User::whereIn('id', $ids)->get(['id', 'name', 'role'])->keyBy('id');

        $byStaff = [];
        foreach ($ids as $id) {
            $own = $sum($personal->get($id, collect()));
            $share = $sum($shares->get($id, collect()));
            $closed = $sum($shares->get($id, collect())->filter(fn (PosTipDistributionLine $line) => in_array($line->distribution_id, $closedIds, true)));
            if ($own->isZero() && $share->isZero()) {
                continue;
            }
            $byStaff[] = [
                'staff_id' => $id,
                'name' => $users[$id]->name ?? null,
                'role' => isset($users[$id]) ? $users[$id]->role->value : null,
                'personal' => $own->toString(),
                'pool_share' => $share->toString(),
                'pool_share_closed' => $closed->toString(),
                'total' => $own->plus($share)->toString(),
            ];
        }

        return [
            'branch_id' => (int) $branch->id,
            'from' => $from,
            'to' => $to,
            'totals' => ShiftTips::summarize($rows),
            'by_day' => $days,
            'by_staff' => $byStaff,
            'undistributed' => $undistributedTotal->toString(),
            // Whom a tip may be given to (a correction): the branch's floor staff
            'staff' => User::where('store_id', $branch->store_id)
                ->whereIn('role', TipSettings::poolRoles())
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                ->orderBy('name')->get(['id', 'name', 'role'])
                ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name, 'role' => $user->role->value])->values()->all(),
        ];
    }
}
