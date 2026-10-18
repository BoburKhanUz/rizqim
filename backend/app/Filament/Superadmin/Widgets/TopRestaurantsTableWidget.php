<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Store;
use App\Enums\OrderStatus;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopRestaurantsTableWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Top Restoranlar (Sotuv hajmiga ko\'ra)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Store::query()
                    ->withSum(['orders' => function (Builder $query) {
                        $query->where('status', OrderStatus::DELIVERED->value);
                    }], 'total_price')
                    ->withCount(['orders' => function (Builder $query) {
                        $query->where('status', OrderStatus::DELIVERED->value);
                    }])
                    ->orderByDesc('orders_sum_total_price')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Restoran')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Muvaffaqiyatli Buyurtmalar')
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_sum_total_price')
                    ->label('Umumiy Sotuv (GMV)')
                    ->money('UZS')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Bizga qarzi')
                    ->money('UZS')
                    ->color('danger')
                    ->sortable(),
            ])
            ->paginated([5]);
    }
}
