<?php

namespace App\Filament\Resources\StopList\Pages;

use App\Enums\Permission;
use App\Enums\StopListSubject;
use App\Filament\Resources\StopList\StopListResource;
use App\Filament\Support\MenuPanelAccess;
use App\Filament\Support\StorePanelScope;
use App\Models\Branch;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\StopListService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class ManageStopList extends ManageRecords
{
    protected static string $resource = StopListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label("Stop-listga qo'shish")
                ->icon('heroicon-m-no-symbol')
                ->color('danger')
                ->visible(fn () => MenuPanelAccess::has(Permission::STOPLIST_MANAGE))
                ->schema([
                    Select::make('branch_id')
                        ->label('Filial')
                        ->options(fn () => MenuPanelAccess::branchOptions())
                        ->default(fn () => MenuPanelAccess::ownBranchId())
                        ->required(),
                    Select::make('type')
                        ->label('Turi')
                        ->options([
                            StopListSubject::PRODUCT->value => 'Mahsulot (barcha variantlari)',
                            StopListSubject::VARIANT->value => 'Variant',
                            StopListSubject::MODIFIER->value => 'Modifikator',
                        ])
                        ->default(StopListSubject::PRODUCT->value)
                        ->live()
                        ->required(),
                    Select::make('subject_id')
                        ->label('Nima')
                        ->options(fn (Get $get) => static::subjectOptions((string) $get('type')))
                        ->searchable()
                        ->required(),
                    TextInput::make('reason')->label('Sabab')->placeholder('Masalan: tugadi')->maxLength(255),
                    DateTimePicker::make('until')->label('Qachongacha (ixtiyoriy)')->minDate(now())->seconds(false),
                ])
                ->action(function (array $data) {
                    $branchId = (int) ($data['branch_id'] ?? 0);
                    abort_unless(MenuPanelAccess::allowsBranch(Permission::STOPLIST_MANAGE, $branchId), 403);

                    $branch = Branch::where('store_id', StorePanelScope::storeId())->findOrFail($branchId);
                    $service = app(StopListService::class);
                    $type = StopListSubject::from((string) $data['type']);

                    try {
                        $subject = $service->resolveSubject($branch, $type, (int) $data['subject_id']);
                        $service->add($branch, $type, $subject, $data['reason'] ?? null, !empty($data['until']) ? Carbon::parse($data['until']) : null, MenuPanelAccess::user());
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title("Stop-listga qo'shildi")->success()->send();
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function subjectOptions(string $type): array
    {
        $storeId = StorePanelScope::storeId();

        return match (StopListSubject::tryFrom($type)) {
            StopListSubject::PRODUCT => Product::where('store_id', $storeId)->orderBy('name')->pluck('name', 'id')->all(),
            StopListSubject::VARIANT => ProductVariant::whereHas('product', fn ($query) => $query->where('store_id', $storeId))
                ->with('product:id,name')
                ->get()
                ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => trim(($variant->product->name ?? '') . " ({$variant->name})")])
                ->all(),
            StopListSubject::MODIFIER => Modifier::whereHas('group', fn ($query) => $query->where('store_id', $storeId))
                ->with('group:id,name')
                ->get()
                ->mapWithKeys(fn (Modifier $modifier) => [$modifier->id => "{$modifier->name} · {$modifier->group?->name}"])
                ->all(),
            default => [],
        };
    }
}
