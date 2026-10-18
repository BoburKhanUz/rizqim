<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Orders nobody acts on any more: the archive, never the active queue
    private const FINISHED = ['delivered', 'canceled', 'rejected', 'refunded'];

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        // The table's own scope (tenant store, a branch manager's branch), so a badge never counts orders the tab hides
        $orders = fn () => OrderResource::getEloquentQuery();

        return [
            'active' => Tab::make('Active Queue')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('status', self::FINISHED))
                ->badge($orders()->whereNotIn('status', self::FINISHED)->count())
                ->badgeColor('primary'),

            'new' => Tab::make('1. Awaiting Accept')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'new'))
                ->badge($orders()->where('status', 'new')->count())
                ->badgeColor(fn () => $orders()->where('status', 'new')->count() > 0 ? 'danger' : 'gray'),

            'preparing' => Tab::make('2. Kitchen Active')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['accepted', 'preparing']))
                ->badge($orders()->whereIn('status', ['accepted', 'preparing'])->count())
                ->badgeColor('warning'),

            'delivering' => Tab::make('3. En Route')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'delivery'))
                ->badge($orders()->where('status', 'delivery')->count())
                ->badgeColor('info'),

            'completed' => Tab::make('4. Archive')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', self::FINISHED))
                ->badge($orders()->whereIn('status', self::FINISHED)->count())
                ->badgeColor('success'),
        ];
    }
}
