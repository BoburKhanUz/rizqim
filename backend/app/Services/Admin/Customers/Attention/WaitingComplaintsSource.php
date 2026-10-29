<?php

namespace App\Services\Admin\Customers\Attention;

use App\Enums\AdminPermission;
use App\Models\Complaint;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Customers\ComplaintInbox;
use App\Services\Admin\Customers\ComplaintLabels;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-31: complaints waiting for the support team longer than support.complaint_waiting_hours (ComplaintInbox::waiting:
 * new or in progress, not answered by the store), the oldest first. A customer who sent it on to RIZQIM after the
 * store's answer makes it a warning.
 */
class WaitingComplaintsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::SUPPORT_VIEW;
    }

    public function kind(): string
    {
        return 'complaint_waiting';
    }

    public function label(): string
    {
        return 'Javob kutayotgan shikoyatlar';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $query = ComplaintInbox::waiting();

        $items = (clone $query)->with(['user:id,name', 'order:id,short_code,store_id', 'order.store:id,name'])
            ->orderBy('created_at')->orderBy('id')->limit($limit)
            ->get()
            ->map(fn (Complaint $complaint) => new AttentionItem(
                kind: $this->kind(),
                severity: $complaint->status === 'in_review' ? 'warning' : 'info',
                title: ComplaintLabels::type($complaint->type) . ($complaint->order?->short_code ? ' · #' . $complaint->order->short_code : ''),
                subtitle: $this->join([$complaint->user?->name, $complaint->order?->store?->name, $this->short($complaint->description, 80), $this->time($complaint->created_at)]),
                href: '/customers?tab=complaints&complaint=' . $complaint->uuid,
                since: $this->iso($complaint->created_at),
                storeId: $complaint->order?->store_id !== null ? (int) $complaint->order->store_id : null,
            ))
            ->values()->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
