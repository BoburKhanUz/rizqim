<?php

namespace App\Services\Admin\Stores\Attention;

use App\Enums\AdminPermission;
use App\Models\StoreCrmProfile;
use App\Models\StoreTask;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;
use App\Services\Admin\Today\PlatformStats;

/**
 * P4-22: CRM-lite tasks still open and due today or earlier (overdue first, as warnings), then stores whose next
 * contact date is today or past (until someone sets a new one).
 */
class DueStoreTasksSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::STORES_VIEW;
    }

    public function kind(): string
    {
        return 'store_task_due';
    }

    public function label(): string
    {
        return 'Bugungi vazifalar va aloqalar';
    }

    public function severity(): string
    {
        return 'info';
    }

    public function collect(int $limit): array
    {
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');
        $tasks = StoreTask::query()->whereNull('done_at')->where('due_on', '<=', $today);
        $contacts = StoreCrmProfile::query()->where('next_contact_on', '<=', $today)->whereHas('store');

        $items = (clone $tasks)->with(['assignee:id,name', 'store:id,name', 'application:id,uuid,name'])
            ->orderBy('due_on')->orderBy('id')->limit($limit)->get()
            ->map(function (StoreTask $task) use ($today) {
                $due = $task->due_on->format('Y-m-d');

                return new AttentionItem(
                    kind: $this->kind(),
                    severity: $due < $today ? 'warning' : 'info',
                    title: $task->title,
                    subtitle: $this->join([$task->store->name ?? $task->application?->name, $task->assignee?->name, $due < $today ? 'muddati ' . $task->due_on->format('d.m') . ' edi' : 'bugun']),
                    href: $task->store_id !== null ? '/stores/' . $task->store_id . '?tab=crm' : '/stores/applications/' . $task->application?->uuid,
                    since: PlatformStats::dayStartOf($due)->toIso8601String(),
                    storeId: $task->store_id !== null ? (int) $task->store_id : null,
                );
            })
            ->values()->all();

        $left = $limit - count($items);
        if ($left > 0) {
            $items = [...$items, ...(clone $contacts)->with('store:id,name')->orderBy('next_contact_on')->orderBy('store_id')->limit($left)->get()
                ->map(fn (StoreCrmProfile $profile) => new AttentionItem(
                    kind: $this->kind(),
                    severity: 'info',
                    title: (string) $profile->store?->name,
                    subtitle: 'Aloqa kuni: ' . $profile->next_contact_on?->format('d.m.Y'),
                    href: '/stores/' . $profile->store_id . '?tab=crm',
                    since: $profile->next_contact_on !== null ? PlatformStats::dayStartOf($profile->next_contact_on->format('Y-m-d'))->toIso8601String() : null,
                    storeId: (int) $profile->store_id,
                ))->values()->all()];
        }

        return ['count' => $tasks->count() + $contacts->count(), 'items' => $items];
    }
}
