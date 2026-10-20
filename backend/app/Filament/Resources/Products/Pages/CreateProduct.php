<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\Catalog\MenuImageService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * P1-21: the uploaded image is on the menu image disk; its thumbnails are made by a queued job.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Product && $record->image) {
            $images = app(MenuImageService::class);
            $record->forceFill(['image_disk' => $images->disk(), 'image_thumbs' => null])->saveQuietly();
            $images->queueThumbnails($record);
        }
    }
}
