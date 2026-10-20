<?php

namespace App\Filament\Superadmin\Resources\StoreSubscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Superadmin\Resources\StoreSubscriptions\Pages\ListStoreSubscriptions;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P1-23: which plan each store is on, and the history (docs/BILLING.md). Assigning or changing a plan goes through
 * SubscriptionService: by default from next month, or immediately.
 */
class StoreSubscriptionResource extends Resource
{
    protected static ?string $model = StoreSubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Tariflar';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = "Do'kon obunalari";
    protected static ?string $modelLabel = 'Obuna';
    protected static ?string $pluralModelLabel = "Do'kon obunalari";

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store:id,name', 'plan:id,name,code', 'nextPlan:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')->label('Restoran')->searchable()->sortable(),
                TextColumn::make('plan.name')->label('Tarif')->badge(),
                TextColumn::make('status')->label('Holati')->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state) => $state->label())
                    ->color(fn (SubscriptionStatus $state) => $state->color()),
                TextColumn::make('current_period_end')->label('Joriy davr')
                    ->state(fn (StoreSubscription $record) => $record->current_period_start->timezone(self::tz())->format('d.m.Y') . ' — ' . $record->current_period_end->timezone(self::tz())->format('d.m.Y')),
                TextColumn::make('trial_ends_at')->label('Sinov tugashi')->dateTime('d.m.Y H:i', self::tz())->placeholder('—'),
                TextColumn::make('pending')->label('Keyingi oydan')
                    ->state(fn (StoreSubscription $record) => $record->cancel_at_period_end ? 'Tugatiladi' : $record->nextPlan?->name)
                    ->placeholder('—'),
                TextColumn::make('started_at')->label('Boshlangan')->dateTime('d.m.Y H:i', self::tz())->sortable(),
                TextColumn::make('cancelled_at')->label('Tugagan')->dateTime('d.m.Y H:i', self::tz())->placeholder('—')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('current')
                    ->label('Joriy obunalar')
                    ->placeholder('Hammasi (tarix bilan)')
                    ->trueLabel('Faqat joriy')
                    ->falseLabel('Faqat tugaganlar')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', '!=', SubscriptionStatus::CANCELLED->value),
                        false: fn (Builder $query) => $query->where('status', SubscriptionStatus::CANCELLED->value),
                    ),
                SelectFilter::make('status')->label('Holati')->options(SubscriptionStatus::options()),
                SelectFilter::make('tariff_plan_id')->label('Tarif')->relationship('plan', 'name'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Tarif biriktirish')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Select::make('store_id')->label('Restoran')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Store::where('name', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%')->limit(50)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value) => Store::find($value)?->name),
                        ...self::planFields(),
                    ])
                    ->action(fn (array $data) => self::assign((int) $data['store_id'], $data)),
            ])
            ->recordActions([
                Action::make('change')
                    ->label("Tarifni o'zgartirish")
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (StoreSubscription $record) => $record->isCurrent())
                    ->schema(self::planFields())
                    ->action(fn (StoreSubscription $record, array $data) => self::assign($record->store_id, $data)),
                Action::make('cancel')
                    ->label('Bekor qilish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StoreSubscription $record) => $record->isCurrent())
                    ->schema([
                        Radio::make('mode')->label('Qachon')->required()->default('period_end')->options([
                            'period_end' => "Oy oxirida (shu oy hisoblanadi, keyin tugaydi)",
                            'now' => "Darhol (shu oy uchun hisob-faktura chiqmaydi)",
                        ]),
                    ])
                    ->action(function (StoreSubscription $record, array $data): void {
                        app(SubscriptionService::class)->cancel($record, $data['mode'] === 'now', self::actor());
                        Notification::make()->title('Obuna bekor qilindi')->success()->send();
                    }),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Field>
     */
    protected static function planFields(): array
    {
        return [
            Select::make('tariff_plan_id')->label('Tarif')->required()
                ->options(fn () => TariffPlan::where('is_active', true)->orderBy('id')->get()->mapWithKeys(fn (TariffPlan $plan) => [$plan->id => "{$plan->name} — {$plan->summary()}"])->all()),
            Radio::make('mode')->label('Qachondan')->required()->default('next')->options([
                'next' => 'Keyingi oydan (joriy oy eski tarif bilan hisoblanadi)',
                'now' => "Darhol (joriy oy butunlay yangi tarif bilan hisoblanadi)",
            ])->helperText("Obunasi yo'q do'kon uchun tarif darhol boshlanadi."),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function assign(int $storeId, array $data): void
    {
        try {
            app(SubscriptionService::class)->assignPlan(
                Store::findOrFail($storeId),
                TariffPlan::findOrFail((int) $data['tariff_plan_id']),
                ($data['mode'] ?? 'next') === 'now',
                self::actor()
            );
        } catch (\DomainException $e) {
            Notification::make()->title('Xatolik')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Tarif saqlandi')->success()->send();
    }

    protected static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected static function tz(): string
    {
        return (string) config('billing.timezone', 'Asia/Tashkent');
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
            'index' => ListStoreSubscriptions::route('/'),
        ];
    }
}
