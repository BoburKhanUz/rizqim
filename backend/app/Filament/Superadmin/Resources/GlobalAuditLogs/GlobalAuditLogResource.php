<?php

namespace App\Filament\Superadmin\Resources\GlobalAuditLogs;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Superadmin\Resources\GlobalAuditLogs\Pages\ListGlobalAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The whole platform's audit log for the super admin, read-only.
 *
 * @extends Resource<AuditLog>
 */
class GlobalAuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $modelLabel = 'Jurnal yozuvi';
    protected static ?string $pluralModelLabel = 'Harakatlar jurnali';
    protected static ?string $navigationLabel = 'Harakatlar jurnali';
    protected static string|\UnitEnum|null $navigationGroup = 'Xodimlar va ruxsatlar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

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
        return parent::getEloquentQuery()->with(['user:id,name,role', 'device:id,name,type', 'store:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('store.name')->label("Do'kon")->placeholder('-'),
                TextColumn::make('action')
                    ->label('Harakat')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AuditLogResource::actionLabels()[$state] ?? $state),
                TextColumn::make('user.name')->label('Kim')->placeholder('Tizim'),
                TextColumn::make('device.name')->label('Qurilma')->placeholder('-')->toggleable(),
                TextColumn::make('model_id')
                    ->label('Obyekt')
                    ->formatStateUsing(fn ($state, AuditLog $record) => trim(($record->subjectName() ?? '') . ' #' . $state)),
                TextColumn::make('changes')
                    ->label("O'zgarish")
                    ->getStateUsing(fn (AuditLog $record) => AuditLogResource::describe($record))
                    ->wrap(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('store_id')->label("Do'kon")->relationship('store', 'name')->searchable(),
                SelectFilter::make('action')->label('Harakat')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalAuditLogs::route('/'),
        ];
    }
}
