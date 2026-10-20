<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\MenuImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Makes the sm/md WebP thumbnails of a product's or category's image (MenuImageService) and records them on the
 * model, unless the image was replaced meanwhile (that upload queues its own job).
 */
class GenerateMenuImageThumbnails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  class-string<Product|Category>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public int $modelId,
        public string $path,
    ) {}

    public function handle(MenuImageService $images): void
    {
        if (!in_array($this->modelClass, [Product::class, Category::class], true)) {
            return;
        }

        $model = $this->modelClass::withTrashed()->find($this->modelId);
        if (!$model || $model->image !== $this->path) {
            return;
        }

        $thumbs = $images->makeThumbnails($this->path, $model->image_disk);
        if ($thumbs === []) {
            return;
        }

        // Quietly: no audit entry or cache reset for a derived file
        $model->forceFill(['image_thumbs' => $thumbs])->saveQuietly();
    }
}
