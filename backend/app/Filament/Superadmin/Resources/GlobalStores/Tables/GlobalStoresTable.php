<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Table;

class GlobalStoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge(),
                \Filament\Tables\Columns\TextColumn::make('legal_name')->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('telegram_invite')
                    ->label('Telegram Link')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->modalHeading('Telegram Boshqaruv Linki')
                    ->modalDescription('Ushbu havolani restoran egasiga yuboring. U botga kirib boshlaganida tizimga avtomat bog\'lanadi.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Yopish')
                    ->form(function (\App\Models\Store $record) {
                        $owner = $record->users()->where('role', \App\Enums\UserRole::OWNER->value)->first();
                        $token = $owner?->telegram_invite_token;
                        if (!$token) {
                            $token = \Illuminate\Support\Str::uuid()->toString();
                            $owner?->update(['telegram_invite_token' => $token]);
                        }
                        $botName = config('services.telegram.bot_username', 'SizningBotName');
                        $link = "https://t.me/{$botName}?start=invite_{$token}";
                        
                        return [
                            \Filament\Forms\Components\TextInput::make('invite_link')
                                ->label('Nusxa olish uchun havola')
                                ->default($link)
                                ->readOnly()
                                ->extraInputAttributes(['readonly' => true]),
                        ];
                    }),
                Action::make('top_up')
                    ->label('To\'lov Qabul Qilish (Top Up)')
                    ->color('primary')
                    ->icon('heroicon-o-banknotes')
                    ->modalHeading('Restoran Balansini To\'ldirish')
                    ->modalDescription('Restorandan olingan naqd yoki o\'tkazma to\'lov summasini kiriting. Bu summa do\'kon qarzini (balansini) kamaytiradi.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Summa (UZS)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        \Filament\Forms\Components\TextInput::make('notes')
                            ->label('Izoh (Notes)')
                            ->placeholder('Masalan: Click orqali to\'landi')
                            ->maxLength(255),
                    ])
                    ->action(function (\App\Models\Store $record, array $data) {
                        $amount = (float) $data['amount'];
                        $notes = $data['notes'] ?? 'Manual top-up';

                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $amount, $notes) {
                            $lockedStore = \App\Models\Store::where('id', $record->id)->lockForUpdate()->firstOrFail();

                            $balanceBefore = $lockedStore->balance;
                            $balanceAfter = $balanceBefore - $amount; // Reduce debt

                            \App\Models\StoreTransaction::create([
                                'store_id' => $lockedStore->id,
                                'type' => \App\Enums\StoreTransactionType::TOPUP->value,
                                'amount' => -$amount, // negative represents payment reducing debt
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'notes' => $notes,
                                'performed_by' => auth()->id(),
                            ]);

                            $updateData = ['balance' => $balanceAfter];

                            if ($lockedStore->status === \App\Enums\StoreStatus::PAYMENT_REQUIRED && $balanceAfter <= -100000) {
                                $updateData['status'] = \App\Enums\StoreStatus::ACTIVE->value;
                            }

                            $lockedStore->update($updateData);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Balans to\'ldirildi')
                            ->success()
                            ->send();
                    }),
                Action::make('impersonate')
                    ->label('Login as Owner')
                    ->color('info')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->url(fn (\App\Models\Store $record) => "/admin/{$record->uuid}")
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
