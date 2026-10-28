<?php

namespace App\Services\Finance\Adjustments;

use App\Enums\AdminPermission;
use App\Models\FinanceAdjustment;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-23: manual adjustments of 1 000 000 so'm or more waiting for a second member's approval, oldest first.
 */
class AdjustmentSecondApprovalSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_MANAGE;
    }

    public function kind(): string
    {
        return 'adjustment_second_approval';
    }

    public function label(): string
    {
        return 'Tasdiq kutayotgan qo\'lda tuzatishlar';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $query = FinanceAdjustment::query()->where('status', FinanceAdjustment::PENDING);

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'requester:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (FinanceAdjustment $adjustment) => new AttentionItem(
                kind: $this->kind(),
                severity: 'warning',
                title: $this->join([$adjustment->store?->name, $this->money($adjustment->amount)]) ?? '',
                subtitle: $this->join([AdjustmentKind::tryFrom($adjustment->kind)?->label(), $adjustment->requester?->name, $this->short($adjustment->reason, 80)]),
                href: '/finance?tab=adjustments&adjustment=' . $adjustment->id,
                since: $this->iso($adjustment->created_at),
                storeId: (int) $adjustment->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
