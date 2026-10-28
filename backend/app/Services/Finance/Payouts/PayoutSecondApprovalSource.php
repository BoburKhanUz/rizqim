<?php

namespace App\Services\Finance\Payouts;

use App\Enums\AdminPermission;
use App\Models\PayoutRequest;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-23: payouts of 1 000 000 so'm or more approved once and waiting for a second member (Moliya's second-approval
 * queue), oldest first. Members who may approve see them.
 */
class PayoutSecondApprovalSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::PAYOUTS_APPROVE;
    }

    public function kind(): string
    {
        return 'payout_second_approval';
    }

    public function label(): string
    {
        return 'Ikkinchi tasdiq kutayotgan payoutlar';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $query = PayoutRequest::query()->where('status', 'pending')->whereNotNull('first_approved_by');

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'firstApprover:id,name'])
            ->orderBy('first_approved_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (PayoutRequest $payout) => new AttentionItem(
                kind: $this->kind(),
                severity: 'warning',
                title: $this->join([$payout->store?->name, $this->money($payout->amount)]) ?? '',
                subtitle: $this->join(['Birinchi tasdiq: ' . $this->approverName($payout), 'ikkinchi xodim tasdiqlashi kerak']),
                href: '/finance?tab=payouts&payout=' . $payout->id,
                since: $this->iso($payout->first_approved_at),
                storeId: (int) $payout->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }

    private function approverName(PayoutRequest $payout): string
    {
        $approver = $payout->getRelation('firstApprover');

        return $approver instanceof \App\Models\User ? (string) $approver->name : '—';
    }
}
