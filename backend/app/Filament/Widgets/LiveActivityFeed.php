<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Filament\Support\StorePanelScope;
use App\Models\OrderStatusHistory;

class LiveActivityFeed extends Widget
{
    protected string $view = 'filament.widgets.live-activity-feed';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 5;

    public function getActivities()
    {
        // The tenant store's orders, and a branch manager's branch only (as the Orders page)
        return OrderStatusHistory::with(['order', 'user'])
            ->whereHas('order', fn ($query) => StorePanelScope::scopeOrders($query))
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();
    }
}
