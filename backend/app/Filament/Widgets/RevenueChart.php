<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue Trend (Last 7 Days)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn($day) => now()->subDays($day)->format('Y-m-d'));
        
        $revenueData = \Illuminate\Support\Facades\DB::table('orders')
            ->selectRaw('DATE(created_at) as date, sum(total_price) as total')
            ->whereIn('status', ['accepted', 'preparing', 'delivery', 'delivered'])
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->pluck('total', 'date');

        $data = $days->map(fn($day) => $revenueData->get($day, 0))->toArray();
        $labels = $days->map(fn($day) => \Carbon\Carbon::parse($day)->format('D'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (UZS)',
                    'data' => $data,
                    'borderColor' => '#8b5cf6', // Deep purple-blue
                    'backgroundColor' => 'rgba(139, 92, 246, 0.15)',
                    'fill' => 'start',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
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
        return 'line';
    }
}
