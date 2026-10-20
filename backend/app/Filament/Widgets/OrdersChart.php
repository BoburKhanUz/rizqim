<?php

namespace App\Filament\Widgets;

use App\Filament\Support\StorePanelScope;
use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected ?string $heading = 'Orders by Status';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        // The tenant store's orders (a branch manager's branch) only
        $new = StorePanelScope::orders()->where('status', 'new')->count();
        $prep = StorePanelScope::orders()->where('status', 'preparing')->count();
        $del = StorePanelScope::orders()->where('status', 'delivery')->count();
        $comp = StorePanelScope::orders()->where('status', 'delivered')->count();
        
        return [
            'datasets' => [
                [
                    'label' => 'Active Orders',
                    'data' => [$new, $prep, $del, $comp],
                    'backgroundColor' => ['#9ca3af', '#f59e0b', '#8b5cf6', '#10b981'],
                    'borderRadius' => 4,
                ],
            ],
            'labels' => ['NEW', 'PREPARING', 'DELIVERING', 'COMPLETED'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'grid' => [
                        'color' => 'rgba(255, 255, 255, 0.04)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'color' => 'rgba(255, 255, 255, 0.04)',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
