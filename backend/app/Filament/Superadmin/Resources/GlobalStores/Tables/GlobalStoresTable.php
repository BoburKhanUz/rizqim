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
                        $amount = \App\Support\Money::of((string) $data['amount']);
                        $notes = $data['notes'] ?? 'Manual top-up';
                        $member = auth()->user();
                        if (!$member instanceof \App\Models\User) {
                            return;
                        }

                        // Review W2: four eyes, as for adjustments and payouts: what reaches the threshold (at once or
                        // with this member's or this store's one-member adjustments of the last 24 hours) is only done
                        // in the Boshqaruv markazi (Moliya → Qo'lda tuzatish), where a second member approves it
                        $done = \Illuminate\Support\Facades\DB::transaction(function () use ($record, $amount, $notes, $member) {
                            if (app(\App\Services\Finance\Adjustments\AdjustmentService::class)->needsSecondApproval($record, $member, $amount)) {
                                return false;
                            }
                            // Same ledger entry and credit-limit unblock rule as online top-ups
                            app(\App\Services\Finance\LedgerService::class)->reduceDebt($record, $amount, $notes, null, $member->id);

                            return true;
                        });
                        if (!$done) {
                            \Filament\Notifications\Notification::make()
                                ->title(\App\Services\Finance\Payouts\PayoutService::uzs(\App\Services\Finance\Payouts\PayoutService::threshold())
                                    . " va undan katta tuzatishni Boshqaruv markazida ikki xodim tasdiqlaydi.")
                                ->danger()
                                ->send();

                            return;
                        }

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
