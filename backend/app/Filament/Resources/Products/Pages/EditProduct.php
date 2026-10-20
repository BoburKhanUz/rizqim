<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * P1-21: a new image is on the menu image disk and gets new thumbnails (queued); the old one's are dropped.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof \App\Models\Product && $record->wasChanged('image')) {
            $images = app(\App\Services\Catalog\MenuImageService::class);
            $record->forceFill(['image_disk' => $record->image ? $images->disk() : null, 'image_thumbs' => null])->saveQuietly();
            $images->queueThumbnails($record);
        }
    }
}
