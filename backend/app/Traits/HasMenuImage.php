<?php

namespace App\Traits;

use App\Services\Catalog\MenuImageService;

/**
 * A product's or category's image: `image` is a path on `image_disk` (null: the public disk, as every image stored
 * before P1-21) or a full URL; `image_thumbs` holds the thumbnails a queued job made ({"sm": path, "md": path}).
 * The API shows image_url (unchanged) and image_thumb_urls ({sm, md}, null until made).
 */
trait HasMenuImage
{
    public function initializeHasMenuImage(): void
    {
        $this->mergeCasts(['image_thumbs' => 'array']);
        $this->append('image_thumb_urls');
        $this->makeHidden(['image_disk', 'image_thumbs']);
    }

    public function getImageUrlAttribute(): ?string
    {
        return app(MenuImageService::class)->url($this->image, $this->image_disk);
    }

    /**
     * @return array{sm: ?string, md: ?string}
     */
    public function getImageThumbUrlsAttribute(): array
    {
        $images = app(MenuImageService::class);
        $thumbs = is_array($this->image_thumbs) ? $this->image_thumbs : [];
        $urls = [];

        foreach (array_keys(MenuImageService::thumbnailSizes()) as $size) {
            $path = $thumbs[$size] ?? null;
            $urls[$size] = is_string($path) ? $images->url($path, $this->image_disk) : null;
        }

        return ['sm' => $urls['sm'] ?? null, 'md' => $urls['md'] ?? null];
    }
}
