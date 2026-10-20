<?php

namespace App\Filament\Superadmin\Pages;

use App\Features\Features;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Per-store switches for the feature flags declared in App\Features\Features. A store without an explicit switch
 * follows the flag's default; "Standartga qaytarish" removes the store's override.
 */
class FeatureFlags extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-flag';
    protected static \UnitEnum|string|null $navigationGroup = 'Sozlamalar';
    protected static ?string $navigationLabel = 'Feature flaglar';
    protected static ?string $title = 'Feature flaglar';
    protected static ?string $slug = 'feature-flags';
    protected string $view = 'filament.superadmin.pages.feature-flags';

    public function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('name')->label('Restoran')->searchable()->sortable(),
        ];

        foreach (Features::FLAGS as $flag => $definition) {
            // Column names are dot-notation state paths, so "new-checkout-copy" becomes a safe key
            $columns[] = ToggleColumn::make('flag_' . Str::slug($flag, '_'))
                ->label($definition['label'])
                ->tooltip($definition['description'])
                ->state(fn (Store $record): bool => Features::forStore($record)[$flag])
                ->updateStateUsing(function (Store $record, bool $state) use ($flag): void {
                    $state ? Feature::for($record)->activate($flag) : Feature::for($record)->deactivate($flag);
                });
        }

        return $table
            ->query(Store::query())
            ->columns($columns)
            ->recordActions([
                Action::make('reset')
                    ->label('Standartga qaytarish')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Store $record): void {
                        Feature::for($record)->forget(Features::names());
                        Notification::make()->title('Restoran flaglari standartga qaytarildi')->success()->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }
}
