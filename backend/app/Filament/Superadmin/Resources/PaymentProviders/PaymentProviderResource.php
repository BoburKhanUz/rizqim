<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders;

use App\Filament\Superadmin\Resources\PaymentProviders\Pages\CreatePaymentProvider;
use App\Filament\Superadmin\Resources\PaymentProviders\Pages\EditPaymentProvider;
use App\Filament\Superadmin\Resources\PaymentProviders\Pages\ListPaymentProviders;
use App\Filament\Superadmin\Resources\PaymentProviders\Schemas\PaymentProviderForm;
use App\Filament\Superadmin\Resources\PaymentProviders\Tables\PaymentProvidersTable;
use App\Models\PaymentProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProvider::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;
    protected static string|\UnitEnum|null $navigationGroup = 'Sozlamalar';
    protected static ?string $navigationLabel = 'To\'lov Tizimlari';
    protected static ?string $modelLabel = 'To\'lov Tizimi';
    protected static ?string $pluralModelLabel = 'To\'lov Tizimlari';
    protected static ?int $navigationSort = 110;

    public static function form(Schema $schema): Schema
    {
        return PaymentProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentProvidersTable::configure($table);
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
            'index' => ListPaymentProviders::route('/'),
            'create' => CreatePaymentProvider::route('/create'),
            'edit' => EditPaymentProvider::route('/{record}/edit'),
        ];
    }
}
