<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\SubscriptionInvoiceStatus;
use App\Filament\Support\StorePanelScope;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Finance\SubscriptionService;
use App\Support\Money;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * P1-23: the store's tariff plan, current period, terminals counted and invoices, read-only (finance.view;
 * docs/BILLING.md). Plans are assigned by the platform in the superadmin panel.
 */
class SubscriptionBilling extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationLabel = 'Tarif va hisob-fakturalar';
    protected static ?string $title = 'Tarif va hisob-fakturalar';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'subscription';
    protected string $view = 'filament.pages.subscription-billing';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, Permission::FINANCE_VIEW);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function overview(): ?array
    {
        $store = StorePanelScope::store();

        return $store ? app(SubscriptionService::class)->overview($store) : null;
    }

    public function uzs(float|int|string $amount): string
    {
        return number_format(Money::of($amount)->toFloat(), 0, '.', ' ') . ' UZS';
    }

    public function table(Table $table): Table
    {
        $storeId = StorePanelScope::storeId();

        return $table
            ->query(SubscriptionInvoice::query()
                ->when($storeId === null, fn ($query) => $query->whereRaw('1 = 0'))
                ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
                ->with('plan:id,name'))
            ->heading('Hisob-fakturalar')
            ->columns([
                TextColumn::make('number')->label('Raqam'),
                TextColumn::make('period')->label('Oy'),
                TextColumn::make('plan.name')->label('Tarif')->placeholder('—'),
                TextColumn::make('terminals_count')->label('Terminallar')
                    ->state(fn (SubscriptionInvoice $record) => "{$record->terminals_count} ({$record->included_terminals} tasi kiradi)"),
                TextColumn::make('amount')->label('Summa')->alignment('right')
                    ->formatStateUsing(fn ($state) => $this->uzs($state)),
                TextColumn::make('status')->label('Holati')->badge()
                    ->formatStateUsing(fn (SubscriptionInvoiceStatus $state) => $state->label())
                    ->color(fn (SubscriptionInvoiceStatus $state) => $state->color()),
                TextColumn::make('due_date')->label("To'lov muddati")->date('d.m.Y'),
            ])
            ->defaultSort('period', 'desc')
            ->emptyStateHeading("Hisob-fakturalar yo'q")
            ->emptyStateDescription("Oylik obuna hisob-fakturalari har oyning 1-sanasida shu yerda paydo bo'ladi.")
            ->paginated([12, 24, 48]);
    }
}
