<?php

namespace App\Filament\Resources\MenuBatches\Pages;

use App\Enums\Permission;
use App\Filament\Resources\MenuBatches\MenuBatchResource;
use App\Filament\Support\MenuPanelAccess;
use App\Filament\Support\StorePanelScope;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\BatchService;
use App\Support\Quantity;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class ManageMenuBatches extends ManageRecords
{
    protected static string $resource = MenuBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Partiya ochish')
                ->icon('heroicon-m-fire')
                ->visible(fn () => MenuPanelAccess::has(Permission::BATCHES_MANAGE))
                ->schema([
                    Select::make('branch_id')
                        ->label('Filial')
                        ->options(fn () => MenuPanelAccess::branchOptions())
                        ->default(fn () => MenuPanelAccess::ownBranchId())
                        ->required(),
                    Select::make('product_id')
                        ->label('Taom')
                        ->options(fn () => Product::where('store_id', StorePanelScope::storeId())->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->required(),
                    Select::make('product_variant_id')
                        ->label('Variant')
                        ->placeholder('Barcha variantlar (har biri o\'z ulushi bilan)')
                        ->options(fn (Get $get) => $get('product_id') ? ProductVariant::where('product_id', $get('product_id'))->pluck('name', 'id')->all() : []),
                    TextInput::make('quantity')->label('Miqdor (porsiya yoki kg)')->numeric()->minValue(0.001)->maxValue(999999)->required(),
                    TextInput::make('label')->label('Belgi')->placeholder('Masalan: 2-qozon')->maxLength(100),
                ])
                ->action(function (array $data) {
                    $branchId = (int) ($data['branch_id'] ?? 0);
                    abort_unless(MenuPanelAccess::allowsBranch(Permission::BATCHES_MANAGE, $branchId), 403);

                    $storeId = StorePanelScope::storeId();
                    $branch = Branch::where('store_id', $storeId)->findOrFail($branchId);
                    $product = Product::where('store_id', $storeId)->findOrFail((int) $data['product_id']);
                    $variant = !empty($data['product_variant_id']) ? ProductVariant::where('product_id', $product->id)->findOrFail((int) $data['product_variant_id']) : null;
                    $quantity = Quantity::tryOf((string) $data['quantity']);
                    if ($quantity === null) {
                        Notification::make()->title("Miqdor ko'pi bilan 3 ta kasr xonali son bo'lishi kerak")->danger()->send();

                        return;
                    }

                    try {
                        app(BatchService::class)->open($branch, $product, $variant, $quantity, $data['label'] ?? null, MenuPanelAccess::user());
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Partiya ochildi')->success()->send();
                }),
        ];
    }
}
