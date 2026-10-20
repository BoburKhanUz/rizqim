<?php

/*
| P1-21: menu model settings (docs/MENU.md).
*/

$defaultDisk = env('FILESYSTEM_DISK') === 's3' ? 's3' : 'public';

return [

    'images' => [
        // Where product and category images go. "s3" needs the S3 adapter (league/flysystem-aws-s3-v3) installed; while
        // it is missing, images fall back to the public disk (App\Services\Catalog\MenuImageService::disk()).
        'disk' => env('MENU_IMAGE_DISK') ?: $defaultDisk,
        // The stored original is resized to at most this width and saved as WebP
        'max_width' => 1200,
        'quality' => 80,
        // Thumbnails made by App\Jobs\GenerateMenuImageThumbnails (width in px)
        'thumbnails' => [
            'sm' => 160,
            'md' => 480,
        ],
        'queue' => env('MENU_IMAGE_QUEUE', 'default'),
    ],

    'idempotency' => [
        // How long a stored response is replayed for the same Idempotency-Key
        'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
        // How long a repeat waits for the first request with its key to finish before answering 409
        'wait_seconds' => (int) env('IDEMPOTENCY_WAIT_SECONDS', 10),
        // A key still marked in progress after this long belongs to a request that died; the next one may take it
        'lock_seconds' => 60,
    ],

];
