<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizer
{
    /**
     * Resizes and compresses an uploaded image, converts it to WebP format,
     * and saves it to the public storage disk.
     *
     * @param UploadedFile $file The uploaded file object.
     * @param string $directory The subdirectory inside public storage (e.g. 'products', 'stores/logos').
     * @param int $maxWidth The maximum allowed width of the output image.
     * @param int $quality The WebP compression quality (0-100).
     * @return string The stored file path relative to public storage.
     */
    public static function optimizeAndStore(UploadedFile $file, string $directory, int $maxWidth = 1000, int $quality = 75): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $tempPath = $file->getRealPath();

        // Create image resource from source
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $image = @imagecreatefromjpeg($tempPath);
                break;
            case 'png':
                $image = @imagecreatefrompng($tempPath);
                break;
            case 'gif':
                $image = @imagecreatefromgif($tempPath);
                break;
            case 'webp':
                $image = @imagecreatefromwebp($tempPath);
                break;
            default:
                $image = false;
        }

        // If GD creation failed or is not a compatible type, store original as fallback
        if (!$image) {
            return $file->store($directory, 'public');
        }

        // Get original dimensions
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        $width = $origWidth;
        $height = $origHeight;

        // Scale down if width exceeds max
        if ($width > $maxWidth) {
            $height = (int) (($maxWidth / $width) * $height);
            $width = $maxWidth;
        }

        // Create new truecolor image container
        $resizedImage = imagecreatetruecolor($width, $height);

        // Preserve alpha transparency for PNG / WebP
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);

        // Perform resample
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);

        // Generate unique filename in webp format
        $filename = Str::random(40) . '.webp';
        $relativeFilePath = $directory . '/' . $filename;
        $absoluteStoragePath = storage_path('app/public/' . $directory);

        if (!file_exists($absoluteStoragePath)) {
            @mkdir($absoluteStoragePath, 0755, true);
        }

        $destPath = $absoluteStoragePath . '/' . $filename;

        // Save image as webp
        imagewebp($resizedImage, $destPath, $quality);

        // Free resources
        imagedestroy($image);
        imagedestroy($resizedImage);

        return $relativeFilePath;
    }
}
