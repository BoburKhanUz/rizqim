<?php

namespace App\Filament\Widgets;

use App\Filament\Support\StorePanelScope;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    protected ?string $heading = 'Top Selling Products';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        // Items of the tenant store's orders (a branch manager's branch) only: the raw order_items table held every store's sales
        $topItems = OrderItem::query()
            ->whereHas('order', fn ($query) => StorePanelScope::scopeOrders($query))
            ->selectRaw('product_name, sum(quantity) as total_qty')
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $labels = $topItems->pluck('product_name')->toArray();
        $data = $topItems->pluck('total_qty')->toArray();
        
        if (empty($labels)) {
            $labels = ['No data'];
            $data = [1];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => ['#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6'],
                    'borderWidth' => 0,
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'color' => 'rgba(255, 255, 255, 0.7)',
                    ],
                ],
            ],
            'cutout' => '70%',
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
