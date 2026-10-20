<?php

namespace App\Filament\Superadmin\Resources\GlobalUsers;

use App\Filament\Superadmin\Resources\GlobalUsers\Pages\ManageGlobalUsers;
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

class GlobalUserResource extends Resource
{
    protected static ?string $model = User::class;
    
    protected static ?string $modelLabel = 'Foydalanuvchi';
    protected static ?string $pluralModelLabel = 'Foydalanuvchilar';
    protected static string|\UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::CUSTOMER->value)
                    ->required()
                    ->live(),
                // Staff without a store would turn store-scoped queries into "store_id IS NULL"
                Select::make('store_id')
                    ->label("Do'kon")
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => self::isStoreRole($get('role')))
                    ->required(fn ($get) => self::isStoreRole($get('role')))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, $get) => self::isStoreRole($get('role')) ? $state : null),
                // P1-24: whose courier this is; the Rizqim fleet is assigned here, not by restaurants
                Select::make('courier_type')
                    ->label('Kuryer turi')
                    ->options(\App\Enums\CourierType::options())
                    ->default(\App\Enums\CourierType::OWN->value)
                    ->visible(fn ($get) => $get('role') === UserRole::COURIER->value || $get('role') === UserRole::COURIER)
                    ->required(fn ($get) => $get('role') === UserRole::COURIER->value || $get('role') === UserRole::COURIER),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                TextInput::make('telegram_username')
                    ->prefix('@')
                    ->maxLength(255),
            ]);
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
                TextColumn::make('phone')
                    ->searchable(),
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
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('telegram_link')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->hidden(fn (User $record) => $record->telegram_id !== null)
                    ->form([
                        TextInput::make('link')
                            ->label('Telegram Invite Link')
                            ->default(function (User $record) {
                                if (!$record->telegram_invite_token) {
                                    $record->update(['telegram_invite_token' => \Illuminate\Support\Str::random(32)]);
                                }
                                $botUsername = config('services.telegram.bot_username', 'bot_username');
                                return "https://t.me/{$botUsername}?start=invite_{$record->telegram_invite_token}";
                            })
                            ->disabled()
                            ->helperText("Ushbu ssilkani nusxalab, foydalanuvchiga jo'nating. U botga kirib /start bossa, profili bog'lanadi.")
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Yopish'),
                // P1-36: a staff member's phone typed in without an SMS code (e.g. before SMS sign-in existed) becomes
                // their login phone only when the platform vouches for it here
                \Filament\Actions\Action::make('vouchLoginPhone')
                    ->label('SMS bilan kirishni yoqish')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->visible(fn (User $record) => $record->role->isStoreStaff()
                        && $record->phone_verified_at === null
                        && is_string($record->phone)
                        && preg_match(\App\Services\Auth\StaffPhoneLoginService::PHONE_PATTERN, $record->phone) === 1)
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => "{$record->phone} raqami egasi hamkor paneli va ilovalarga SMS kod bilan shu xodim hisobiga kira oladi. Raqam haqiqatan shu xodimniki ekanini tekshiring.")
                    ->action(function (User $record) {
                        app(\App\Services\Auth\StaffPhoneLoginService::class)->vouch($record, $record->phone);
                        \Filament\Notifications\Notification::make()->title('SMS bilan kirish yoqildi')->success()->send();
                    }),
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('4xl'),
                EditAction::make()
                    ->using(fn (User $record, array $data) => static::saveUser($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Save an edited user. A phone typed here is a contact detail (it loses its verification, User::booted), and a
     * vouched login phone ends when the account leaves the store roles; when either drops a staff login phone, that
     * is audited and the account's app sessions end (P1-34, StaffPhoneLoginService::loginPhoneChanged).
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveUser(User $record, array $data): User
    {
        $loginPhone = $record->role->isStoreStaff() ? \App\Services\Auth\StaffPhoneLoginService::loginPhone($record) : null;
        $via = $record->phone_verified_via;
        $storeId = $record->store_id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data, $loginPhone, $via, $storeId) {
            $record->update($data);

            if ($loginPhone !== null && \App\Services\Auth\StaffPhoneLoginService::loginPhone($record) !== $loginPhone) {
                app(\App\Services\Auth\StaffPhoneLoginService::class)->loginPhoneChanged($record, $loginPhone, $via, $storeId);
            }
        });

        return $record;
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
                                          $url = \App\Filament\Superadmin\Resources\GlobalOrders\GlobalOrderResource::getUrl('view', ['record' => $order]);
                                          
                                          $html .= '<div class="flex relative gap-3">';
                                          if (!$isLast) {
                                              $html .= '<div class="absolute left-1.5 top-5 bottom-[-1rem] w-px bg-gray-200 dark:bg-gray-800"></div>';
                                          }
                                          $html .= '<div class="relative z-10 w-3 h-3 mt-1.5 rounded-full bg-primary-500 ring-4 ring-white dark:ring-gray-900"></div>';
                                          
                                          // Clickable Link to Order details
                                          $html .= '<a href="' . $url . '" class="flex flex-col flex-1 group hover:underline decoration-primary-500">';
                                          $html .= '<div class="text-sm font-bold text-primary-600 dark:text-primary-400 group-hover:text-primary-500 leading-tight">Order #' . $order->short_code . '</div>';
                                          $html .= '<div class="text-xs font-medium text-gray-500">' . $order->created_at->format('d M Y, H:i') . ' • ' . number_format((float) $order->total_price, 0, ',', ' ') . ' UZS</div>';
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
            'index' => ManageGlobalUsers::route('/'),
        ];
    }

    public static function isStoreRole(mixed $role): bool
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return in_array($value, array_map(fn (UserRole $role) => $role->value, UserRole::storeRoles()), true);
    }
}
