<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Pages;

use App\Filament\Superadmin\Resources\GlobalStores\GlobalStoreResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Hidden;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CreateGlobalStore extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = GlobalStoreResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make('Store Configuration')
                ->description('Asosiy sozlamalar va komissiya')
                ->schema([
                    TextInput::make('name')
                        ->label('Store Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label("Do'kon telefoni")
                        ->tel()
                        ->maxLength(255),
                    // P4-21/P4-22: empty = the per-channel commission policy (RIZQIM courier 15%, own delivery 10%,
                    // pickup 5%, hall 0%); a value is a flat rate for every online channel
                    TextInput::make('commission_rate')
                        ->label('Commission Rate (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText("Bo'sh qoldiring: kanal bo'yicha komissiya (15/10/5/0%). Qiymat — barcha onlayn kanallar uchun bitta foiz."),
                    Select::make('payment_account_type')
                        ->options(\App\Enums\PaymentAccountType::class)
                        ->default(\App\Enums\PaymentAccountType::PLATFORM_ACCOUNT->value)
                        ->required(),

                    Hidden::make('status')
                        ->default(\App\Enums\StoreStatus::ACTIVE->value),
                    Hidden::make('is_active')
                        ->default(true),
                ]),
                
            Step::make('Owner Credentials')
                ->description('Egasi (Owner) ma\'lumotlari')
                ->schema([
                    TextInput::make('owner_name')
                        ->label('Owner Name')
                        ->required(),
                    // P1-36: the owner signs in to the partner panel and apps with an SMS code to this number
                    TextInput::make('owner_phone')
                        ->label('Phone Number (Login)')
                        ->tel()
                        ->placeholder('+998901234567')
                        ->regex(\App\Services\Auth\StaffPhoneLoginService::PHONE_PATTERN)
                        ->validationMessages(['regex' => "Raqam +998XXXXXXXXX ko'rinishida bo'lsin."])
                        // Another staff account may not hold it; the owner's own customer (Mini App) account may
                        ->unique(User::class, 'phone', modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->where(
                            fn ($query) => $query->where('role', '!=', \App\Enums\UserRole::CUSTOMER->value)
                        ))
                        ->required(),
                    TextInput::make('owner_password')
                        ->label('Password')
                        ->password()
                        ->required()
                        ->minLength(6),
                ]),
                
            Step::make('First Branch Details')
                ->description('Dastlabki filial')
                ->schema([
                    \Filament\Forms\Components\Select::make('branch_region_id')
                        ->label('Region')
                        ->options(\App\Models\Region::where('is_active', true)->pluck('name_uz', 'id'))
                        ->live()
                        ->required(),
                    \Filament\Forms\Components\Select::make('branch_district_id')
                        ->label('District')
                        ->options(fn ($get) => \App\Models\District::where('is_active', true)
                            ->where('region_id', $get('branch_region_id'))
                            ->pluck('name_uz', 'id')
                        )
                        ->required(),
                    Textarea::make('branch_address')
                        ->label('Street Address / Landmark')
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('branch_phone')
                        ->label('Branch Phone')
                        ->tel(),
                    TextInput::make('branch_radius')
                        ->label('Delivery Radius (km)')
                        ->numeric()
                        ->default(5)
                        ->required(),
                    \Filament\Forms\Components\ViewField::make('map_picker')
                        ->label('Xaritadan tanlang')
                        ->view('filament.forms.components.leaflet-map', ['latField' => 'data.branch_lat', 'lngField' => 'data.branch_lng'])
                        ->columnSpanFull(),
                    TextInput::make('branch_lat')
                        ->label('Latitude (Lat)')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->required(),
                    TextInput::make('branch_lng')
                        ->label('Longitude (Lng)')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->required(),
                    \Filament\Forms\Components\TimePicker::make('branch_open_time')
                        ->label('Open Time')
                        ->default('09:00'),
                    \Filament\Forms\Components\TimePicker::make('branch_close_time')
                        ->label('Close Time')
                        ->default('23:00'),
                ])->columns(2),
        ];
    }

    /**
     * P4-22: the same creation as the Boshqaruv markazi's "convert an application" (StoreProvisioner): store, owner with
     * a vouched login phone and a Telegram invite, main branch; the superadmins hear about it on Telegram.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(\App\Services\Admin\Stores\StoreProvisioner::class)->create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'commission_rate' => filled($data['commission_rate'] ?? null) ? $data['commission_rate'] : null,
                'payment_account_type' => $data['payment_account_type'],
                'payment_methods' => ['cash', 'click', 'payme'],
                'status' => $data['status'],
                'is_active' => (bool) $data['is_active'],
                'owner_name' => $data['owner_name'],
                'owner_phone' => $data['owner_phone'],
                'owner_password' => $data['owner_password'],
                'branch_region_id' => (int) $data['branch_region_id'],
                'branch_district_id' => (int) $data['branch_district_id'],
                'branch_address' => $data['branch_address'],
                'branch_phone' => $data['branch_phone'] ?? null,
                'branch_lat' => $data['branch_lat'],
                'branch_lng' => $data['branch_lng'],
                'branch_radius' => $data['branch_radius'],
                'branch_open_time' => $data['branch_open_time'] ?? null,
                'branch_close_time' => $data['branch_close_time'] ?? null,
            ], 'Filament')['store'];
        } catch (\App\Services\Admin\Stores\StoreAdminException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.owner_phone' => $e->getMessage()]);
        }
    }

  protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
