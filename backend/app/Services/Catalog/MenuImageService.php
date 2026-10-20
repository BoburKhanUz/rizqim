<?php

namespace App\Services\Catalog;

use App\Jobs\GenerateMenuImageThumbnails;
use App\Models\Category;
use App\Models\Product;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menu images (products, categories) on the configured disk (config('menu.images.disk'): an S3-compatible bucket
 * when FILESYSTEM_DISK=s3, else the public disk), resized to WebP with GD, with sm/md thumbnails made by a queued job.
 * Without GD the original file is stored as uploaded and no thumbnails are made.
 */
class MenuImageService
{
    private static bool $warnedAboutS3 = false;

    /**
     * @return array<string, int> size name => width in px
     */
    public static function thumbnailSizes(): array
    {
        $sizes = config('menu.images.thumbnails', ['sm' => 160, 'md' => 480]);

        return is_array($sizes) ? array_map('intval', $sizes) : ['sm' => 160, 'md' => 480];
    }

    /**
     * The disk new images go to. An "s3" disk needs league/flysystem-aws-s3-v3, which is not installed yet: until it
     * is, images fall back to the public disk (logged once) rather than failing every upload.
     */
    public function disk(): string
    {
        $disk = (string) config('menu.images.disk', 'public');

        if (config("filesystems.disks.{$disk}") === null) {
            return 'public';
        }

        if (config("filesystems.disks.{$disk}.driver") === 's3' && !class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            if (!self::$warnedAboutS3) {
                self::$warnedAboutS3 = true;
                Log::warning("Menu images: disk '{$disk}' needs league/flysystem-aws-s3-v3; storing on the public disk instead.");
            }

            return 'public';
        }

        return $disk;
    }

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagewebp');
    }

    /**
     * Stores an uploaded image (resized to WebP when GD can read it) and returns its path and disk.
     *
     * @return array{path: string, disk: string}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $disk = $this->disk();
        $image = self::gdAvailable() ? $this->read((string) file_get_contents((string) $file->getRealPath())) : null;

        if (!$image) {
            return ['path' => (string) $file->store($directory, $disk), 'disk' => $disk];
        }

        $path = trim($directory, '/') . '/' . Str::random(40) . '.webp';
        Storage::disk($disk)->put($path, $this->encode($image, (int) config('menu.images.max_width', 1200)), ['visibility' => 'public']);
        imagedestroy($image);

        return ['path' => $path, 'disk' => $disk];
    }

    /**
     * Sets a new image on a product or category (deleting the old one and its thumbnails) and queues its thumbnails.
     */
    public function replace(Product|Category $model, UploadedFile $file, string $directory): void
    {
        $this->delete($model);
        $stored = $this->store($file, $directory);

        $model->forceFill(['image' => $stored['path'], 'image_disk' => $stored['disk'], 'image_thumbs' => null])->save();
        $this->queueThumbnails($model);
    }

    /**
     * Deletes the model's stored image and thumbnails (not a full URL image, which is someone else's).
     */
    public function delete(Product|Category $model): void
    {
        $path = $model->image;
        if (!is_string($path) || $path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        $disk = $model->image_disk ?: 'public';
        $thumbs = is_array($model->image_thumbs) ? array_values(array_filter($model->image_thumbs, 'is_string')) : [];

        try {
            Storage::disk($disk)->delete([$path, ...$thumbs]);
        } catch (\Throwable $e) {
            Log::warning('Menu image could not be deleted', ['path' => $path, 'disk' => $disk, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Queues the thumbnails of the model's current image (after commit, so the job sees the saved path).
     */
    public function queueThumbnails(Product|Category $model): void
    {
        if (!self::gdAvailable() || !is_string($model->image) || $model->image === '' || filter_var($model->image, FILTER_VALIDATE_URL)) {
            return;
        }

        GenerateMenuImageThumbnails::dispatch($model::class, (int) $model->getKey(), $model->image)
            ->onQueue((string) config('menu.images.queue', 'default'))
            ->afterCommit();
    }

    /**
     * Makes the sm/md thumbnails of an image; returns size => path (empty when the image cannot be read).
     *
     * @return array<string, string>
     */
    public function makeThumbnails(string $path, ?string $disk): array
    {
        $disk = $disk ?: 'public';
        $storage = Storage::disk($disk);

        if (!self::gdAvailable() || !$storage->exists($path)) {
            return [];
        }

        $image = $this->read((string) $storage->get($path));
        if (!$image) {
            return [];
        }

        $directory = trim(dirname($path), './');
        $name = pathinfo($path, PATHINFO_FILENAME);
        $thumbs = [];

        foreach (self::thumbnailSizes() as $size => $width) {
            $thumbPath = ($directory !== '' ? $directory . '/' : '') . "thumbs/{$name}-{$size}.webp";
            $storage->put($thumbPath, $this->encode($image, $width), ['visibility' => 'public']);
            $thumbs[$size] = $thumbPath;
        }

        imagedestroy($image);

        return $thumbs;
    }

    /**
     * A stored image's public URL: a full URL is kept, the public disk keeps its old /storage/... form.
     */
    public function url(?string $path, ?string $disk): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if ($disk === null || $disk === '' || $disk === 'public') {
            return asset('storage/' . $path);
        }

        try {
            return Storage::disk($disk)->url($path);
        } catch (\Throwable) {
            return asset('storage/' . $path);
        }
    }

    private function read(string $contents): ?GdImage
    {
        if ($contents === '') {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * The image scaled down to at most $maxWidth (never up), as WebP bytes.
     */
    private function encode(GdImage $image, int $maxWidth): string
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxWidth > 0 && $width > $maxWidth) {
            $height = max(1, (int) round($height * $maxWidth / $width));
            $width = $maxWidth;
        }

        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagewebp($resized, null, (int) config('menu.images.quality', 80));
        $bytes = (string) ob_get_clean();
        imagedestroy($resized);

        return $bytes;
    }
}
