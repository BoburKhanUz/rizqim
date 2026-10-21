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
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Branch;

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
                    TextInput::make('commission_rate')
                        ->label('Commission Rate (%)')
                        ->numeric()
                        ->default(10)
                        ->required(),
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

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $paymentSettings = \App\Models\Store::initialPaymentSettings(['cash', 'click', 'payme']);

            $store = \App\Models\Store::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'commission_rate' => $data['commission_rate'],
                'payment_account_type' => $data['payment_account_type'],
                'payment_settings' => $paymentSettings,
                'status' => $data['status'],
                'is_active' => $data['is_active'],
            ]);

            $ownerToken = \Illuminate\Support\Str::uuid()->toString();
            $owner = User::create([
                'name' => $data['owner_name'],
                'password' => bcrypt($data['owner_password']),
                'role' => \App\Enums\UserRole::OWNER->value,
                'store_id' => $store->id,
                'telegram_invite_token' => $ownerToken,
            ]);
            // The platform vouches for the owner's login phone: SMS sign-in to the partner panel (docs/AUTH.md)
            app(\App\Services\Auth\StaffPhoneLoginService::class)->vouch($owner, $data['owner_phone']);

            Branch::create([
                'store_id' => $store->id,
                'region_id' => $data['branch_region_id'],
                'district_id' => $data['branch_district_id'],
                'name' => 'Asosiy Filial',
                'address' => $data['branch_address'],
                'phone' => $data['branch_phone'],
                'latitude' => $data['branch_lat'],
                'longitude' => $data['branch_lng'],
                'radius_km' => $data['branch_radius'],
                'open_time' => $data['branch_open_time'] ?? null,
                'close_time' => $data['branch_close_time'] ?? null,
                'is_active' => true,
            ]);

            return $store;
        });
    }

  protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function afterCreate(): void
    {
        $store = $this->record;
        if (!$store instanceof \App\Models\Store) {
            return;
        }

        $owner = User::where('store_id', $store->id)->where('role', 'owner')->first();
        $branch = Branch::where('store_id', $store->id)->first();
        
        $botUsername = config('services.telegram.bot_username');
        $telegram = app(\App\Services\Telegram\TelegramMessenger::class);
        
        if ($owner && $telegram->isConfigured()) {
            $inviteLink = "https://t.me/{$botUsername}?start=invite_{$owner->telegram_invite_token}";
            
            $superAdmins = User::where('role', \App\Enums\UserRole::SUPER_ADMIN)
                ->whereNotNull('telegram_id')
                ->get();

            foreach ($superAdmins as $admin) {
                $telegram->sendMessage($admin->telegram_id, "🏢 <b>Yangi do'kon qo'shildi (Filament)!</b>\n\n" .
                    "Nomi: <b>" . htmlspecialchars($store->name) . "</b>\n" .
                    "Komissiya: <b>{$store->commission_rate}%</b>\n" .
                    "Egasi: <b>" . htmlspecialchars($owner->name) . "</b> (" . htmlspecialchars($owner->phone) . ")\n" .
                    "Filial: <b>" . htmlspecialchars($branch->address) . "</b>\n\n" .
                    "🔗 <b>Egasi uchun taklifnoma havolasi:</b>\n{$inviteLink}", ['parse_mode' => 'HTML']);
            }
        }
    }
}
