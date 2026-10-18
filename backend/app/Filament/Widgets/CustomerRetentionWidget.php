<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomerRetentionWidget extends ChartWidget
{
    protected ?string $heading = 'Mijozlar sadoqati (Qaytgan mijozlar)';
    protected static ?int $sort = 3;

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

        // Count how many orders each user has made in this store
        $userOrderCounts = Order::where('store_id', $tenant->getKey())
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total');

        $newCustomers = 0; // 1 order
        $returningCustomers = 0; // 2+ orders

        foreach ($userOrderCounts as $count) {
            if ($count == 1) {
                $newCustomers++;
            } else {
                $returningCustomers++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Mijozlar',
                    'data' => [$newCustomers, $returningCustomers],
                    'backgroundColor' => [
                        '#10b981', // emerald-500 (New)
                        '#6366f1', // indigo-500 (Returning)
                    ],
                ],
            ],
            'labels' => ['Yangi Mijozlar (1 marta)', 'Doimiy Mijozlar (2+ marta)'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
