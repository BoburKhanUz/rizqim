<?php

namespace App\Services\Admin\Stores\Attention;

use App\Enums\AdminPermission;
use App\Enums\StoreApplicationStage;
use App\Models\StoreApplication;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;
use App\Services\Admin\Today\PlatformStats;

/**
 * P4-22: applications in the pipeline past their stage's due date (Asia/Tashkent), the longest overdue first.
 */
class OverdueApplicationsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::STORES_VIEW;
    }

    public function kind(): string
    {
        return 'store_application_overdue';
    }

    public function label(): string
    {
        return "Muddati o'tgan arizalar";
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function collect(int $limit): array
    {
        $today = PlatformStats::dayStart(PlatformStats::now());
        $query = StoreApplication::query()
            ->whereNotIn('stage', [StoreApplicationStage::ACTIVE->value, StoreApplicationStage::REJECTED->value])
            ->where('due_on', '<', $today->format('Y-m-d'));

        $items = (clone $query)->with('assignee:id,name')->orderBy('due_on')->orderBy('id')->limit($limit)
            ->get(['id', 'uuid', 'name', 'stage', 'assignee_id', 'due_on', 'store_id'])
            ->map(function (StoreApplication $application) use ($today) {
                /** @var \Illuminate\Support\Carbon $dueOn */
                $dueOn = $application->due_on;
                $due = PlatformStats::dayStartOf($dueOn->format('Y-m-d'));
                $days = (int) $due->diffInDays($today);

                return new AttentionItem(
                    kind: $this->kind(),
                    severity: 'warning',
                    title: $application->name,
                    subtitle: $this->join([$application->stage->label(), $application->assignee->name ?? "mas'ul yo'q", "muddati {$due->format('d.m')} edi, {$days} kun o'tdi"]),
                    href: '/stores/applications/' . $application->uuid,
                    since: $due->addDay()->toIso8601String(),
                    storeId: $application->store_id !== null ? (int) $application->store_id : null,
                );
            })
            ->values()->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
