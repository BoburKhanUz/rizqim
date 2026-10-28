<?php

namespace App\Filament\Superadmin\Resources\SubscriptionInvoices;

use App\Enums\SubscriptionInvoiceStatus;
use App\Filament\Superadmin\Resources\SubscriptionInvoices\Pages\ListSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P1-23: every store's monthly subscription invoices (docs/BILLING.md). Voiding one reverses its ledger charge;
 * "To'landi" only changes the label (the money itself comes in as a top-up, which reduces the debt).
 */
class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Tariflar';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Hisob-fakturalar';
    protected static ?string $modelLabel = 'Hisob-faktura';
    protected static ?string $pluralModelLabel = 'Hisob-fakturalar';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store:id,name', 'plan:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Raqam')->searchable()->copyable(),
                TextColumn::make('store.name')->label('Restoran')->searchable()->sortable(),
                TextColumn::make('period')->label('Oy')->sortable(),
                TextColumn::make('plan.name')->label('Tarif')->placeholder('—'),
                TextColumn::make('terminals_count')->label('Terminallar')
                    ->state(fn (SubscriptionInvoice $record) => "{$record->terminals_count} ({$record->included_terminals} tasi kiradi)"),
                TextColumn::make('amount')->label('Summa')->alignment('right')->sortable()
                    ->formatStateUsing(fn ($state) => number_format(Money::of($state)->toFloat(), 2, '.', ' ') . ' UZS'),
                TextColumn::make('status')->label('Holati')->badge()
                    ->formatStateUsing(fn (SubscriptionInvoiceStatus $state) => $state->label())
                    ->color(fn (SubscriptionInvoiceStatus $state) => $state->color()),
                TextColumn::make('due_date')->label("To'lov muddati")->date('d.m.Y')->sortable(),
                TextColumn::make('void_reason')->label('Bekor qilish sababi')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holati')->options(SubscriptionInvoiceStatus::options()),
                SelectFilter::make('period')->label('Oy')
                    ->options(fn () => SubscriptionInvoice::query()->distinct()->orderByDesc('period')->limit(36)->pluck('period', 'period')->all()),
                SelectFilter::make('store_id')->label('Restoran')->relationship('store', 'name')->searchable(),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label("To'landi")
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SubscriptionInvoice $record) => $record->status === SubscriptionInvoiceStatus::ISSUED)
                    ->requiresConfirmation()
                    ->modalDescription("Hisob-faktura to'langan deb belgilanadi. Balans o'zgarmaydi: to'lovning o'zi balansni to'ldirish sifatida yoziladi.")
                    ->action(function (SubscriptionInvoice $record): void {
                        app(SubscriptionService::class)->markPaid($record, self::actor());
                        Notification::make()->title("To'langan deb belgilandi")->success()->send();
                    }),
                Action::make('void')
                    ->label('Bekor qilish')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (SubscriptionInvoice $record) => $record->status !== SubscriptionInvoiceStatus::VOID)
                    ->modalDescription("Hisob-faktura bekor qilinadi va uning summasi do'kon qarzidan ayriladi (ledger'da teskari yozuv). To'langanini bekor qilib bo'lmaydi; 1 000 000 so'm va undan kattasini Boshqaruv markazida ikki xodim bekor qiladi.")
                    ->schema([
                        Textarea::make('reason')->label('Sabab')->required()->maxLength(500),
                    ])
                    ->action(function (SubscriptionInvoice $record, array $data): void {
                        // Review W2: a void of the four-eyes threshold or more takes two members: only in the
                        // Boshqaruv markazi (a paid invoice is refused by voidInvoice itself)
                        $threshold = \App\Services\Finance\Payouts\PayoutService::threshold();
                        if ($record->total()->isGreaterThanOrEqualTo($threshold) && Money::of($record->paid_amount ?? 0)->isZero() && $record->status === SubscriptionInvoiceStatus::ISSUED) {
                            Notification::make()->title(\App\Services\Finance\Payouts\PayoutService::uzs($threshold) . " va undan katta hisob-fakturani Boshqaruv markazida ikki xodim bekor qiladi.")->danger()->send();

                            return;
                        }
                        try {
                            app(SubscriptionService::class)->voidInvoice($record, self::actor(), (string) $data['reason']);
                        } catch (\App\Services\Finance\InvoiceVoidException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('Hisob-faktura bekor qilindi')->success()->send();
                    }),
            ]);
    }

    protected static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionInvoices::route('/'),
        ];
    }
}
