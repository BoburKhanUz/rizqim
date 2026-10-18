<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Models\User;
use App\Enums\UserRole;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = User::class;
    
    protected static ?string $modelLabel = 'Employee';
    protected static ?string $pluralModelLabel = 'Employees';
    protected static string|\UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return auth()->user()->role === \App\Enums\UserRole::MANAGER ? 'Couriers' : 'Employees';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Ismi'),
                Select::make('role')
                    ->options(function () {
                        if (auth()->user()->role === \App\Enums\UserRole::MANAGER) {
                            return [\App\Enums\UserRole::COURIER->value => 'Courier'];
                        }
                        return [
                            \App\Enums\UserRole::MANAGER->value => 'Manager',
                            \App\Enums\UserRole::COURIER->value => 'Courier',
                        ];
                    })
                    ->default(\App\Enums\UserRole::COURIER->value)
                    ->required(),
                Select::make('branch_id')
                    ->label('Filial (Branch)')
                    ->relationship('branch', 'name', function ($query) {
                        $storeId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->store_id;
                        return $query->where('store_id', $storeId);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $storeId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->store_id;
        $query = parent::getEloquentQuery()->where('store_id', $storeId);
        
        if (auth()->user()->role === UserRole::MANAGER) {
            $query->where('role', UserRole::COURIER->value);
        } else {
            $query->whereIn('role', [UserRole::MANAGER->value, UserRole::COURIER->value]);
        }
        
        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('telegram_photo_url')
                    ->label('Avatar')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=User'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('branch.name')
                    ->label('Filial')
                    ->sortable()
                    ->badge(),
                TextColumn::make('telegram_invite_token')
                    ->label('Taqlif Linki (Invite)')
                    ->formatStateUsing(fn ($state) => $state ? "https://t.me/" . (config('services.telegram.bot_username') ?: env('TELEGRAM_BOT_USERNAME', 'rizqimbot')) . "?start=invite_" . $state : 'Bog\'langan')
                    ->copyable()
                    ->copyMessage('Link nusha olindi!')
                    ->icon('heroicon-m-link')
                    ->color('primary'),
                TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? "@{$state}" : '-'),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Orders')
                    ->sortable(),
                TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->getStateUsing(fn (User $record) => $record->orders()->where('status', 'delivered')->sum('total_price'))
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 0, ',', ' ') . ' UZS')
                    ->sortable(),
                TextColumn::make('segment')
                    ->label('Mood')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'VIP' => '🔥 VIP Client 👑',
                        'Returning' => '⭐ Returning',
                        'New' => '👋 New',
                        'Inactive' => '😴 Inactive',
                        default => $state
                    })
                    ->getStateUsing(function (User $record) {
                        $count = $record->orders()->count();
                        if ($count >= 10) return 'VIP';
                        if ($count > 1) return 'Returning';
                        if ($count === 1) return 'New';
                        return 'Inactive';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'VIP' => 'warning', // Gold/Yellow glow for VIP
                        'Returning' => 'info',
                        'New' => 'success',
                        'Inactive' => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('telegram_invite_token')
                    ->label('Invite Link')
                    ->getStateUsing(fn (\App\Models\User $record) => empty($record->telegram_invite_token) ? null : "https://t.me/" . (config('services.telegram.bot_username') ?: env('TELEGRAM_BOT_USERNAME', 'rizqimbot')) . "?start=invite_" . $record->telegram_invite_token)
                    ->copyable()
                    ->copyableState(fn (\App\Models\User $record) => "https://t.me/" . (config('services.telegram.bot_username') ?: env('TELEGRAM_BOT_USERNAME', 'rizqimbot')) . "?start=invite_" . $record->telegram_invite_token)
                    ->copyMessage('Ssilka nusxalandi!')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->iconColor('success')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Ssilkani nusxalash' : 'Token yo\'q'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('4xl'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

  public static function infolist(Schema $schema): Schema
  {
      return $schema
          ->components([
              \Filament\Schemas\Components\Grid::make(3)
                  ->schema([
                      \Filament\Schemas\Components\Section::make('Customer Profile')
                          ->schema([
                              \Filament\Infolists\Components\TextEntry::make('name')
                                  ->label('Name')
                                  ->weight('bold'),
                              \Filament\Infolists\Components\TextEntry::make('phone')
                                  ->label('Phone')
                                  ->icon('heroicon-m-phone'),
                              \Filament\Infolists\Components\TextEntry::make('telegram_username')
                                  ->label('Telegram username')
                                  ->icon('heroicon-m-paper-airplane')
                                  ->formatStateUsing(fn ($state) => $state ? "@{$state}" : '-'),
                          ])
                          ->columns(3)
                          ->columnSpanFull(),
                          
                      \Filament\Schemas\Components\Section::make('Analytics')
                          ->schema([
                              \Filament\Infolists\Components\TextEntry::make('orders_count')
                                  ->label('Total Orders')
                                  ->getStateUsing(fn (User $record) => $record->orders()->count()),
                              \Filament\Infolists\Components\TextEntry::make('total_spent')
                                  ->label('Lifetime Value')
                                  ->weight('bold')
                                  ->color('primary')
                                  ->getStateUsing(fn (User $record) => $record->orders()->where('status', 'delivered')->sum('total_price'))
                                  ->formatStateUsing(fn ($state) => number_format($state ?? 0, 0, ',', ' ') . ' UZS'),
                              \Filament\Infolists\Components\TextEntry::make('avg_check')
                                  ->label('Average Check')
                                  ->getStateUsing(function (User $record) {
                                      $count = $record->orders()->where('status', 'delivered')->count();
                                      $total = $record->orders()->where('status', 'delivered')->sum('total_price');
                                      return $count > 0 ? $total / $count : 0;
                                  })
                                  ->formatStateUsing(fn ($state) => number_format($state ?? 0, 0, ',', ' ') . ' UZS'),
                          ])
                          ->columns(3)
                          ->columnSpanFull(),
                          
                      \Filament\Schemas\Components\Section::make('Recent Orders Timeline')
                          ->schema([
                              \Filament\Infolists\Components\TextEntry::make('id')
                                  ->hiddenLabel()
                                  ->formatStateUsing(function ($state, User $record) {
                                      $orders = $record->orders()->orderBy('created_at', 'desc')->take(5)->get();
                                      if ($orders->isEmpty()) return 'No orders yet.';
                                      
                                      $html = '<div class="flex flex-col gap-4 mt-2">';
                                      foreach ($orders as $index => $order) {
                                          $isLast = $index === $orders->count() - 1;
                                          $url = \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]);
                                          
                                          $html .= '<div class="flex relative gap-3">';
                                          if (!$isLast) {
                                              $html .= '<div class="absolute left-1.5 top-5 bottom-[-1rem] w-px bg-gray-200 dark:bg-gray-800"></div>';
                                          }
                                          $html .= '<div class="relative z-10 w-3 h-3 mt-1.5 rounded-full bg-primary-500 ring-4 ring-white dark:ring-gray-900"></div>';
                                          
                                          // Clickable Link to Order details
                                          $html .= '<a href="' . $url . '" class="flex flex-col flex-1 group hover:underline decoration-primary-500">';
                                          $html .= '<div class="text-sm font-bold text-primary-600 dark:text-primary-400 group-hover:text-primary-500 leading-tight">Order #' . $order->short_code . '</div>';
                                          $html .= '<div class="text-xs font-medium text-gray-500">' . $order->created_at->format('d M Y, H:i') . ' • ' . number_format($order->total_price, 0, ',', ' ') . ' UZS</div>';
                                          $html .= '</a>';
                                          
                                          $html .= '</div>';
                                      }
                                      $html .= '</div>';
                                      return new \Illuminate\Support\HtmlString($html);
                                  })
                          ])
                          ->columnSpanFull(),
                  ])
                  ->columnSpanFull(),
          ]);
  }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
        ];
    }
}
