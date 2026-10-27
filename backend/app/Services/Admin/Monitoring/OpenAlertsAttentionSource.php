<?php

namespace App\Services\Admin\Monitoring;

use App\Enums\AdminPermission;
use App\Models\Alert;
use App\Services\Admin\Attention\AttentionItem;
use App\Services\Admin\Attention\AttentionSource;

/**
 * P4-15: "Bugun → Diqqat talab qiladi": alerts nobody has taken yet (state `open`; an acknowledged alert has an owner).
 * Critical first, then the newest. Indexed on (state, severity, id).
 */
class OpenAlertsAttentionSource implements AttentionSource
{
    public function permission(): AdminPermission
    {
        return AdminPermission::MONITORING_VIEW;
    }

    public function collect(int $limit): array
    {
        $open = Alert::query()->where('state', AlertState::OPEN->value);

        $count = (clone $open)->count();
        if ($count === 0 || $limit < 1) {
            return ['count' => $count, 'items' => []];
        }

        $items = (clone $open)
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Alert $alert) => new AttentionItem(
                'alert',
                $alert->severity,
                $alert->title,
                AlertKind::from($alert->kind)->label(),
                '/monitoring?tab=alerts&alert=' . $alert->id,
                $alert->first_seen_at->toIso8601String(),
                $alert->store_id !== null ? (int) $alert->store_id : null,
            ))
            ->all();

        return ['count' => $count, 'items' => $items];
    }
}
