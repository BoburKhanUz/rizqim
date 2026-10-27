<?php

namespace App\Services\Admin\Today\Attention;

use App\Enums\AdminPermission;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\SubscriptionInvoice;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\PlatformStats;

/**
 * Subscription invoices still unpaid (`issued`) after their due date (Asia/Tashkent), the longest overdue first.
 */
class OverdueInvoicesSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::FINANCE_VIEW;
    }

    public function kind(): string
    {
        return 'invoice_overdue';
    }

    public function label(): string
    {
        return "Muddati o'tgan hisob-fakturalar";
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $today = PlatformStats::dayStart(PlatformStats::now());
        $query = SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->where('due_date', '<', $today->format('Y-m-d'));

        $items = (clone $query)
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'number', 'store_id', 'amount', 'due_date'])
            ->map(function (SubscriptionInvoice $invoice) use ($today) {
                $due = PlatformStats::dayStartOf($invoice->due_date->format('Y-m-d'));
                $days = (int) $due->diffInDays($today);

                return new AttentionItem(
                    kind: $this->kind(),
                    severity: 'warning',
                    title: $this->join([$invoice->number, $invoice->store?->name]) ?? $invoice->number,
                    subtitle: $this->join([$this->money($invoice->amount), "muddati {$due->format('d.m.Y')} edi, {$days} kun o'tdi"]),
                    href: '/pos?invoice=' . $invoice->uuid,
                    // Overdue from the day after the due date
                    since: $due->addDay()->toIso8601String(),
                    storeId: (int) $invoice->store_id,
                );
            })
            ->values()
            ->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
