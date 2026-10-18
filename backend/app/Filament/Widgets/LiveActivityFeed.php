<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\OrderStatusHistory;

class LiveActivityFeed extends Widget
{
    protected string $view = 'filament.widgets.live-activity-feed';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 5;

    public function getActivities()
    {
        return OrderStatusHistory::with(['order', 'user'])
            ->whereHas('order', function($q) {
                $q->where('store_id', filament()->getTenant()->id);
            })
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();
    }
}
