<?php

namespace Tests\Feature\PosReleases;

use App\Models\PosAppRelease;
use Illuminate\Support\Str;

/**
 * Wave M5 S5-a: release rows written directly.
 */
trait PosReleaseFixtures
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function release(string $version, array $attributes = []): PosAppRelease
    {
        return PosAppRelease::forceCreate(array_merge([
            'app' => 'cashier',
            'platform' => 'android',
            'version' => $version,
            'build' => 1,
            'file_url' => 'https://pos.rizqim.uz/files/cashier/android/rizqim-cashier-' . $version . '.apk',
            'sha256' => hash('sha256', $version . Str::random(4)),
            'size_bytes' => 25_000_000,
            'notes' => 'Yangi versiya ' . $version,
            'released_at' => now()->subDay(),
            'is_minimum' => false,
        ], $attributes));
    }
}
