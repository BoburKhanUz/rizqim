<?php

namespace Tests\Feature;

use App\Jobs\GenerateMenuImageThumbnails;
use App\Models\Product;
use App\Services\Catalog\MenuImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P1-21 menu images: stored as WebP on the menu image disk, sm/md thumbnails made by a queued job, URLs in the API.
 */
class MenuImagesTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        if (!MenuImageService::gdAvailable()) {
            $this->markTestSkipped('GD with WebP is not available.');
        }

        $this->setUpLifecycleStore();
        Storage::fake('public');
    }

    public function test_an_uploaded_product_image_gets_thumbnails_and_urls(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->post('/api/v1/owner/products', [
            'name' => 'Lavash',
            'category_id' => $this->product->category_id,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('lavash.jpg', 1600, 1000),
            'variants' => json_encode([['name' => 'Oddiy', 'price' => 32000]]),
        ], ['Accept' => 'application/json'])->assertOk();

        $product = Product::findOrFail($response->json('data.id'));
        $this->assertStringEndsWith('.webp', $product->image);
        $this->assertSame('public', $product->image_disk);
        Storage::disk('public')->assertExists($product->image);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($product->image));
        $this->assertSame(1200, $width);

        // The queue is sync in tests: the job already ran
        $this->assertEqualsCanonicalizing(['sm', 'md'], array_keys($product->image_thumbs));
        foreach (['sm' => 160, 'md' => 480] as $size => $expected) {
            Storage::disk('public')->assertExists($product->image_thumbs[$size]);
            [$width] = getimagesizefromstring(Storage::disk('public')->get($product->image_thumbs[$size]));
            $this->assertSame($expected, $width);
        }

        $catalog = collect($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->json('data.products'))
            ->firstWhere('id', $product->id);
        $this->assertSame(asset('storage/' . $product->image), $catalog['image_url']);
        $this->assertSame(asset('storage/' . $product->image_thumbs['md']), $catalog['image_thumb_urls']['md']);
        $this->assertArrayNotHasKey('image_thumbs', $catalog);

        // A new image replaces the old one and its thumbnails
        $old = [$product->image, ...array_values($product->image_thumbs)];
        $this->actingAs($this->owner, 'sanctum')->post("/api/v1/owner/products/{$product->id}", [
            'name' => 'Lavash',
            'category_id' => $this->product->category_id,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.png', 300, 300),
            'variants' => json_encode([['id' => $product->variants()->first()->id, 'name' => 'Oddiy', 'price' => 32000]]),
        ], ['Accept' => 'application/json'])->assertOk();
        foreach ($old as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $product->refresh();
        Storage::disk('public')->assertExists($product->image_thumbs['sm']);
    }

    public function test_thumbnails_are_queued_after_commit_and_old_images_keep_working(): void
    {
        Queue::fake();
        $legacy = $this->makeProduct('Eski', ['image' => 'products/old.jpg']);
        $external = $this->makeProduct('Tashqi', ['image' => 'https://cdn.example.com/a.jpg']);

        $this->assertSame(asset('storage/products/old.jpg'), $legacy->image_url);
        $this->assertSame(['sm' => null, 'md' => null], $legacy->image_thumb_urls);
        $this->assertSame('https://cdn.example.com/a.jpg', $external->image_url);

        app(MenuImageService::class)->queueThumbnails($legacy);
        app(MenuImageService::class)->queueThumbnails($external);
        Queue::assertPushed(GenerateMenuImageThumbnails::class, 1);
    }

    public function test_an_s3_disk_without_its_adapter_falls_back_to_the_public_disk(): void
    {
        config(['menu.images.disk' => 's3', 'filesystems.disks.s3.driver' => 's3']);

        $expected = class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class) ? 's3' : 'public';
        $this->assertSame($expected, app(MenuImageService::class)->disk());

        config(['menu.images.disk' => 'missing-disk']);
        $this->assertSame('public', app(MenuImageService::class)->disk());
    }
}
