<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class DailyOrdersChart extends ChartWidget
{
    protected ?string $heading = 'Kunlik Buyurtmalar (Oxirgi 7 kun)';
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()->role === \App\Enums\UserRole::OWNER;
    }

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $data = [];
        $labels = [];

        // Loop through the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('D, d M');

            // Count orders for that day
            $count = Order::where('store_id', $tenant->getKey())
                ->whereDate('created_at', $date)
                ->count();

            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Buyurtmalar Soni',
                    'data' => $data,
                    'borderColor' => '#4f46e5', // Indigo to match admin theme
                    'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                    'fill' => true,
                    'tension' => 0.4, // Smooth curve
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
