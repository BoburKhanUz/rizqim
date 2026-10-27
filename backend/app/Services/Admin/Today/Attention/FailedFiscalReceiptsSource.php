<?php

namespace App\Services\Admin\Today\Attention;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Enums\AdminPermission;
use App\Models\FiscalReceipt;
use App\Services\Admin\Attention\AttentionItem;

/**
 * Fiscal receipts the provider refused or that ran out of retries (fiscal_receipts.status = failed) in the last
 * 7 days, newest first: a sale without a valid receipt. Older ones are on the POS section's fiscal list.
 */
class FailedFiscalReceiptsSource extends TodayAttentionSource
{
    public const WITHIN_DAYS = 7;

    public function permission(): AdminPermission
    {
        return AdminPermission::DEVICES_VIEW;
    }

    public function kind(): string
    {
        return 'fiscal_failed';
    }

    public function label(): string
    {
        return 'Yiqilgan fiskal cheklar';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function collect(int $limit): array
    {
        $query = FiscalReceipt::query()
            ->where('status', FiscalReceiptStatus::FAILED->value)
            ->where('created_at', '>=', now()->subDays(self::WITHIN_DAYS));

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'branch:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'store_id', 'branch_id', 'total', 'error', 'attempts', 'created_at', 'updated_at'])
            ->map(fn (FiscalReceipt $receipt) => new AttentionItem(
                kind: $this->kind(),
                severity: 'critical',
                title: $this->join([$receipt->store?->name, $receipt->branch?->name]) ?? '',
                subtitle: $this->join([$this->money($receipt->total), $this->short($receipt->error) ?? 'Fiskal chek chiqmadi']),
                href: '/pos?fiscal=' . $receipt->uuid,
                since: $this->iso($receipt->created_at),
                storeId: (int) $receipt->store_id,
            ))
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
