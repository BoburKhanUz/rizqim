<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\Permission;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Support\StorePanelScope;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\PermissionService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The store's harakatlar jurnali, read-only (audit.view: owners by default).
 *
 * @extends Resource<AuditLog>
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Jurnal yozuvi';
    protected static ?string $pluralModelLabel = 'Harakatlar jurnali';
    protected static ?string $navigationLabel = 'Harakatlar jurnali';
    protected static string|\UnitEnum|null $navigationGroup = 'Xodimlar va ruxsatlar';
    protected static ?int $navigationSort = 7;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, Permission::AUDIT_VIEW);
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

    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();
        $query = AuditLog::query()->with(['user:id,name,role', 'device:id,name,type', 'branch:id,name']);

        return $storeId === null ? $query->whereRaw('1 = 0') : $query->where('store_id', $storeId);
    }

    /**
     * @return array<string, string> action => Uzbek label
     */
    public static function actionLabels(): array
    {
        return [
            'order.cancelled' => 'Buyurtma bekor qilindi',
            'order.rejected' => 'Buyurtma rad etildi',
            'order.refunded' => 'Pul qaytarildi',
            'order.status_changed' => "Buyurtma holati o'zgardi",
            'product.price_changed' => "Narx o'zgardi",
            'staff.role_changed' => "Xodim roli o'zgardi",
            'staff.branch_changed' => "Xodim filiali o'zgardi",
            'staff.pin_set' => "PIN o'rnatildi",
            'staff.pin_changed' => "PIN almashtirildi",
            'staff.pin_reset' => "PIN o'chirildi",
            'staff.session_started' => 'Terminalga kirish (PIN)',
            // P1-36: the partner panel / a staff app, by SMS code
            'staff.signed_in' => 'Xodim SMS kod bilan kirdi',
            'permissions.changed' => "Ruxsat o'zgardi",
            'device.pairing_code_created' => 'Ulash kodi yaratildi',
            'device.paired' => 'Terminal ulandi',
            'device.renamed' => "Qurilma nomi o'zgardi",
            'device.revoked' => "Qurilma o'chirildi",
            'employee_transfer' => "Xodim boshqa filialga o'tkazildi",
            // P1-21: menu
            'menu.stop_list_added' => "Stop-listga qo'shildi",
            'menu.stop_list_removed' => 'Stop-listdan olindi',
            'menu.batch_opened' => 'Partiya (qozon) ochildi',
            'menu.batch_adjusted' => "Partiya qoldig'i o'zgardi",
            'menu.batch_closed' => 'Partiya yopildi',
            'product.channel_prices_changed' => "Kanal narxlari o'zgardi",
            'settings.service_fees_changed' => "Xizmat haqi sozlamalari o'zgardi",
            // P1-23: tariffs (docs/BILLING.md)
            'subscription.started' => 'Tarif biriktirildi',
            'subscription.plan_change_scheduled' => "Tarif keyingi oydan o'zgaradi",
            'subscription.plan_changed' => "Tarif o'zgardi",
            'subscription.cancelled' => 'Obuna bekor qilindi',
            'subscription_invoice.voided' => 'Hisob-faktura bekor qilindi',
            'subscription_invoice.paid' => "Hisob-faktura to'landi",
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Vaqt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Harakat')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::actionLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match (true) {
                        in_array($state, ['order.cancelled', 'order.rejected', 'order.refunded', 'device.revoked', 'staff.pin_reset'], true) => 'danger',
                        str_starts_with($state, 'permissions.') || str_starts_with($state, 'staff.role') => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Kim')
                    ->placeholder('Tizim'),
                TextColumn::make('device.name')
                    ->label('Qurilma')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Filial')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('model_id')
                    ->label('Obyekt')
                    ->formatStateUsing(fn ($state, AuditLog $record) => trim(($record->subjectName() ?? '') . ' #' . $state)),
                TextColumn::make('changes')
                    ->label("O'zgarish")
                    ->getStateUsing(fn (AuditLog $record) => static::describe($record))
                    ->wrap(),
                // Staff and terminal addresses only (customers' are never stored); the owner's and superadmin's eyes only
                TextColumn::make('ip')
                    ->label('IP')
                    ->visible(fn () => in_array(auth()->user()?->role, [\App\Enums\UserRole::OWNER, \App\Enums\UserRole::SUPER_ADMIN], true))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('Harakat')
                    ->options(static::actionLabels()),
                SelectFilter::make('user_id')
                    ->label('Xodim')
                    ->options(fn () => User::where('store_id', StorePanelScope::storeId())->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dan'),
                        DatePicker::make('until')->label('Gacha'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    /**
     * "status: new → canceled; reason: out_of_stock" from the before/after diff and the context.
     */
    public static function describe(AuditLog $record): string
    {
        $parts = [];
        foreach ((array) $record->after as $key => $value) {
            $old = ((array) $record->before)[$key] ?? null;
            $parts[] = "{$key}: " . static::scalar($old) . ' → ' . static::scalar($value);
        }
        foreach ((array) $record->payload_json as $key => $value) {
            $parts[] = "{$key}: " . static::scalar($value);
        }

        return implode('; ', $parts);
    }

    protected static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'ha' : "yo'q",
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
