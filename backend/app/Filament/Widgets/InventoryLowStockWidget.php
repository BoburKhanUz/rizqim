<?php

namespace App\Filament\Widgets;

use App\Filament\Support\StorePanelScope;
use App\Models\ProductVariant;
use App\Services\Ops\InventoryVelocityService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class InventoryLowStockWidget extends BaseWidget
{
    protected static ?string $heading = '🚨 Low Stock Critical Feed';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Variants of the tenant store's products only; the bare query listed every store's stock
                ProductVariant::query()
                    ->whereHas('product', fn ($query) => $query->where('store_id', StorePanelScope::storeId()))
                    ->where('stock', '<', 10)
                    ->orderBy('stock', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->weight('bold')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Variant / Spec')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Remaining Stock')
                    ->badge()
                    ->color(fn ($state): string => $state <= 2 ? 'danger' : 'warning')
                    ->formatStateUsing(fn ($state) => $state <= 0 ? 'OUT OF STOCK' : $state . ' left'),

                Tables\Columns\TextColumn::make('depletion_prediction')
                    ->label('Depletion Forecast (Sales Velocity)')
                    ->badge()
                    ->color(fn ($record) => InventoryVelocityService::getInventoryRisk($record)->getColor())
                    ->getStateUsing(fn ($record) => InventoryVelocityService::getInventoryRisk($record)->getForecastString()),
            ])
            ->emptyStateHeading('🛡️ Stock levels are healthy')
            ->emptyStateDescription('Your most used ingredients like Cheeseburger Buns, Fries, and Cheddar Slices are fully stocked. Critical sorting is active: items nearing depletion will auto-escalate to the top.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
