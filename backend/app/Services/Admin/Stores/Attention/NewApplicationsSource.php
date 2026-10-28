<?php

namespace App\Services\Admin\Stores\Attention;

use App\Enums\AdminPermission;
use App\Enums\StoreApplicationStage;
use App\Models\StoreApplication;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Today\Attention\TodayAttentionSource;

/**
 * P4-22: applications still in the first stage ("Ariza") nobody has taken yet (no assignee), the oldest first.
 */
class NewApplicationsSource extends TodayAttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::STORES_VIEW;
    }

    public function kind(): string
    {
        return 'store_application_new';
    }

    public function label(): string
    {
        return 'Yangi arizalar';
    }

    public function severity(): string
    {
        return 'info';
    }

    public function collect(int $limit): array
    {
        $query = StoreApplication::query()
            ->where('stage', StoreApplicationStage::APPLICATION->value)
            ->whereNull('assignee_id');

        $items = (clone $query)->with('region:id,name_uz')->orderBy('created_at')->orderBy('id')->limit($limit)
            ->get(['id', 'uuid', 'name', 'format', 'region_id', 'city', 'owner_name', 'created_at'])
            ->map(fn (StoreApplication $application) => new AttentionItem(
                kind: $this->kind(),
                severity: 'info',
                title: $application->name,
                subtitle: $this->join([$application->format->label(), $application->region->name_uz ?? $application->city, $application->owner_name, $this->time($application->created_at)]),
                href: '/stores/applications/' . $application->uuid,
                since: $this->iso($application->created_at),
            ))
            ->values()->all();

        return ['count' => $query->count(), 'items' => $items];
    }
}
