<?php

namespace App\Filament\Superadmin\Resources\Complaints;

use App\Filament\Superadmin\Resources\Complaints\Pages\EditComplaint;
use App\Filament\Superadmin\Resources\Complaints\Pages\ListComplaints;
use App\Models\Complaint;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'E\'tirozlar';
    protected static ?string $modelLabel = 'E\'tiroz';
    protected static ?string $pluralModelLabel = 'E\'tirozlar';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('E\'tiroz Tafsilotlari')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('customer_name')
                            ->label('Foydalanuvchi')
                            ->content(fn ($record) => $record->user->name ?? 'No User'),
                        \Filament\Forms\Components\Placeholder::make('order_code')
                            ->label('Buyurtma ID')
                            ->content(fn ($record) => $record->order->short_code ?? 'No Order'),
                        \Filament\Forms\Components\Placeholder::make('complaint_type')
                            ->label('E\'tiroz Turi')
                            ->content(fn ($record) => $record->type ?? 'Other'),
                        \Filament\Forms\Components\Placeholder::make('description')
                            ->label('Tavsif')
                            ->content(fn ($record) => $record->description ?? ''),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Menejer/Do\'kon Javobi')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('owner_reply')
                            ->label('Do\'kon Javobi')
                            ->content(fn ($record) => $record->reply ?? 'Javob berilmagan'),
                    ]),

                \Filament\Schemas\Components\Section::make('SuperAdmin Hal Qilishi')
                    ->schema([
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'owner_replied' => 'Owner Replied',
                                'in_review' => 'In Review (SuperAdmin)',
                                'closed' => 'Closed',
                            ])
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('resolution_comment')
                            ->label('Hal Qilish Izohi')
                            ->rows(3)
                            ->placeholder('Muammo qanday hal etilganligi haqida izoh...'),
                        \Filament\Forms\Components\Hidden::make('resolved_by')
                            ->default(fn () => auth()->id()),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Mijoz')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order.short_code')
                    ->label('Buyurtma')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'WRONG_ITEM' => 'danger',
                        'ORDER_NOT_RECEIVED' => 'danger',
                        'QUALITY_ISSUE' => 'warning',
                        'COURIER_ISSUE' => 'warning',
                        'PAYMENT_ISSUE' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Tavsif')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'gray',
                        'owner_replied' => 'info',
                        'in_review' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan Vaqt')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'edit' => EditComplaint::route('/{record}/edit'),
        ];
    }
}
