<?php

namespace App\Filament\Superadmin\Resources\GlobalOrders;

use App\Filament\Superadmin\Resources\GlobalOrders\Pages;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;

class GlobalOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?int $navigationSort = 4;
    protected static string|\UnitEnum|null $navigationGroup = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('short_code')
                    ->label('ID')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time context')
                    ->since()
                    ->color(fn (Order $record): string => match ($record->status->value ?? $record->status) {
                        'preparing' => $record->created_at->diffInMinutes(now()) > 15 ? 'danger' : 'gray',
                        'new' => $record->created_at->diffInMinutes(now()) > 5 ? 'danger' : 'gray',
                        default => 'gray',
                    })
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->description(fn (Order $record) => $record->user?->phone ?? 'No phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Active Order items')
                    ->default(fn (Order $record) => $record->items->map(fn ($item) => "{$item->product_name} x{$item->quantity}")->join(', ')),

                Tables\Columns\TextColumn::make('urgency')
                    ->label('Urgency Monitor')
                    ->badge()
                    ->color(fn (Order $record): string => match ($record->status->value ?? $record->status) {
                        'preparing' => $record->created_at->diffInMinutes(now()) > 15 ? 'danger' : 'gray',
                        'new' => $record->created_at->diffInMinutes(now()) > 5 ? 'danger' : 'gray',
                        default => 'gray',
                    })
                    ->default(function (Order $record): string {
                        $statusVal = $record->status->value ?? $record->status;
                        $minutes = $record->created_at->diffInMinutes(now());

                        if ($statusVal === 'preparing' && $minutes > 20) {
                            return '⚠️ COURIER DELAY';
                        }
                        if ($statusVal === 'preparing' && $minutes > 15) {
                            return '🔥 LATE ORDER';
                        }
                        if ($statusVal === 'new' && $minutes > 5) {
                            return '🚨 ACTION REQ';
                        }
                        return '● NORMAL';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Ops Queue')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value ?? $state) {
                        'new' => 'gray',
                        'accepted' => 'info',
                        'preparing' => 'warning',
                        'delivery' => 'primary',
                        'delivered' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state->value ?? $state) {
                        'new' => 'AWAITING ACCEPT',
                        'accepted' => 'QUEUED',
                        'preparing' => 'KITCHEN PREP',
                        'delivery' => 'EN ROUTE',
                        'delivered' => 'COMPLETED',
                        'canceled' => 'CANCELED',
                        default => strtoupper($state->value ?? $state),
                    }),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total Check')
                    ->money('UZS', locale: 'uz-UZ')
                    ->alignment('right')
                    ->sortable(),

                Tables\Columns\TextColumn::make('courier.name')
                    ->label('Courier')
                    ->placeholder('Not Assigned Yet 🛵')
                    ->badge()
                    ->color('purple')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->emptyStateHeading('No active orders 🍃')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->emptyStateDescription('Your operations center is quiet right now. Waiting for new orders to arrive.')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->actions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('5xl'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Grid::make(4)
                    ->schema([
                        \Filament\Schemas\Components\Section::make('Customer')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('user.name')->hiddenLabel()->weight('bold')->size('lg'),
                                \Filament\Infolists\Components\TextEntry::make('user.phone')->icon('heroicon-m-phone')->hiddenLabel(),
                                \Filament\Infolists\Components\TextEntry::make('delivery_address')->icon('heroicon-m-map-pin')->hiddenLabel(),
                                \Filament\Infolists\Components\TextEntry::make('notes')->icon('heroicon-m-document-text')->hiddenLabel(),
                                \Filament\Infolists\Components\TextEntry::make('courier.name')
                                    ->label('Courier')
                                    ->icon('heroicon-m-truck')
                                    ->placeholder('No courier assigned yet 🛵'),
                            ])->columnSpan(1),
                            
                        \Filament\Schemas\Components\Section::make('Order Items')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('items')
                                    ->hiddenLabel()
                                    ->formatStateUsing(function ($state, Order $record) {
                                        $html = '<div class="flex flex-col gap-3 w-full">';
                                        foreach ($record->items as $item) {
                                            $variant = $item->variant_name ? " ({$item->variant_name})" : "";
                                            $price = number_format($item->total_price, 0, '.', ' ') . " so'm";
                                            $html .= "
                                                <div class='flex justify-between items-center w-full gap-4 py-3.5 px-4 bg-gray-50/50 dark:bg-gray-800/40 rounded-xl border border-gray-100 dark:border-gray-800/80 text-sm font-semibold shadow-sm'>
                                                    <span class='text-gray-900 dark:text-white flex-1 min-w-0 truncate'>🍔 {$item->product_name}{$variant} <span class='text-gray-400 font-normal ml-1.5'>x{$item->quantity}</span></span>
                                                    <span class='text-purple-600 dark:text-purple-400 font-bold whitespace-nowrap text-right'>{$price}</span>
                                                </div>
                                            ";
                                        }
                                        $html .= '</div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                            ])->columnSpan(2),
                        
                    \Filament\Schemas\Components\Section::make('Timeline')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('id')
                                ->hiddenLabel()
                                ->formatStateUsing(function ($state, Order $record) {
                                    $html = '<div class="flex flex-col gap-4 mt-2">';
                                    $histories = $record->statusHistories()->orderBy('created_at', 'asc')->get();
                                    foreach ($histories as $index => $history) {
                                        $isLast = $index === $histories->count() - 1;
                                        $statusObj = is_object($history->status) ? $history->status->value : $history->status;
                                        $statusLabel = match($statusObj) {
                                            'new' => 'Created',
                                            'accepted' => 'Accepted',
                                            'preparing' => 'Preparing',
                                            'delivery' => 'Delivering',
                                            'delivered' => 'Completed',
                                            'canceled' => 'Canceled',
                                            default => ucfirst($statusObj)
                                        };
                                        $colorClass = match($statusObj) {
                                            'new' => 'bg-gray-400',
                                            'accepted' => 'bg-blue-500',
                                            'preparing' => 'bg-orange-500',
                                            'delivery' => 'bg-purple-500',
                                            'delivered' => 'bg-green-500',
                                            'canceled' => 'bg-red-500',
                                            default => 'bg-gray-500'
                                        };
                                        
                                        $pulseClass = $isLast ? 'animate-pulse-dot shadow-sm' : '';
                                        
                                        $html .= '<div class="flex relative gap-3">';
                                        if (!$isLast) {
                                            $html .= '<div class="absolute left-1.5 top-5 bottom-[-1rem] w-px bg-gray-200 dark:bg-gray-800"></div>';
                                        }
                                        $html .= '<div class="relative z-10 w-3 h-3 mt-1.5 rounded-full ring-4 ring-white dark:ring-gray-900 ' . $colorClass . ' ' . $pulseClass . '"></div>';
                                        $html .= '<div class="flex flex-col flex-1">';
                                        $html .= '<div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">' . $statusLabel . '</div>';
                                        $html .= '<div class="text-xs font-medium text-gray-500">' . $history->created_at->format('H:i') . '</div>';
                                        $html .= '</div>';
                                        $html .= '</div>';
                                    }
                                    $html .= '</div>';
                                    return new \Illuminate\Support\HtmlString($html);
                                })
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlobalOrders::route('/'),
            'view' => Pages\ViewGlobalOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
