<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected ?string $heading = 'Orders by Status';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $new = \App\Models\Order::where('status', 'new')->count();
        $prep = \App\Models\Order::where('status', 'preparing')->count();
        $del = \App\Models\Order::where('status', 'delivery')->count();
        $comp = \App\Models\Order::where('status', 'delivered')->count();
        
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
