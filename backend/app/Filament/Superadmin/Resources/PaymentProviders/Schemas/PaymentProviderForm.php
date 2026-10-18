<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PaymentProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()->schema([
                    Section::make('General Information')
                        ->schema([
                            TextInput::make('name')
                                ->label('To\'lov Tizimi Nomi')
                                ->required()
                                ->maxLength(255)
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                                
                            TextInput::make('slug')
                                ->label('Slug (Kodi)')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                                
                            FileUpload::make('icon')
                                ->label('Ikonka / Logo')
                                ->image()
                                ->directory('payment-providers')
                                ->columnSpanFull(),
                                
                            Toggle::make('is_active')
                                ->label('Aktiv')
                                ->default(true)
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 1]),

                Group::make()->schema([
                    Section::make('API Kalitlari (Credentials)')
                        ->description('Ushbu to\'lov tizimi uchun kerakli API kalitlarni kiriting (masalan: merchant_id, secret_key)')
                        ->schema([
                            KeyValue::make('credentials')
                                ->label('Kalitlar')
                                ->keyLabel('Kalit (Key)')
                                ->valueLabel('Qiymat (Value)')
                                ->addActionLabel('Kalit qo\'shish'),
                        ]),
                ])->columnSpan(['lg' => 2]),
            ])
            ->columns(3);
    }
}
