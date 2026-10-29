<?php

namespace Tests\Feature\AdminMarketing;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P4-31: Marketing -> Kategoriyalar: permissions, the icon through MenuImageService, two levels, hiding, the whole-list
 * order, deleting, reasons and audit.
 */
class AdminMarketingCategoryTest extends TestCase
{
    use RefreshDatabase;
    use AdminMarketingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->setUpLifecycleStore();
    }

    /**
     * @return array<int, string>  name => id order of the admin list
     */
    private function names(string $token): array
    {
        return collect($this->adminJson('GET', '/marketing/categories', $token)->assertOk()->json('data.data'))->pluck('name')->all();
    }

    private function makeCategory(string $name, array $attributes = []): Category
    {
        return Category::create(array_merge(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'is_active' => true], $attributes));
    }

    public function test_permissions_viewer_reads_but_cannot_act_and_finance_sees_nothing(): void
    {
        $category = $this->makeCategory('Ichimliklar');
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $finance = $this->signIn($this->makeMember(PlatformRole::FINANCE));

        $this->adminJson('GET', '/marketing/categories', $viewer)->assertOk();
        $this->adminJson('POST', '/marketing/categories', $viewer, ['name' => 'Yangi', 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('POST', "/marketing/categories/{$category->id}", $viewer, ['is_active' => false, 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('PUT', '/marketing/categories/order', $viewer, ['ids' => [$category->id], 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('DELETE', "/marketing/categories/{$category->id}", $viewer, ['reason' => 'Sinov'])->assertForbidden();
        $this->assertTrue($category->refresh()->is_active);

        $this->adminJson('GET', '/marketing/categories', $finance)->assertForbidden();
    }

    public function test_list_shows_counts_in_the_catalog_order(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $parent = $this->makeCategory('Taomlar');
        $child = $this->makeCategory('Burgerlar', ['parent_id' => $parent->id]);

        $rows = collect($this->adminJson('GET', '/marketing/categories', $token)->assertOk()->json('data.data'))->keyBy('name');
        // The fixture's own category (Burgers) holds the product
        $this->assertSame(1, $rows['Burgers']['products_count']);
        $this->assertSame(1, $rows['Taomlar']['children_count']);
        $this->assertSame($parent->id, $rows['Burgerlar']['parent']['id']);
        $this->assertTrue($rows['Taomlar']['sort_order'] < $rows['Burgerlar']['sort_order']);
        $this->assertSame(['Burgers', 'Taomlar', 'Burgerlar'], $this->names($token));
    }

    public function test_create_with_an_icon_goes_last_and_is_audited(): void
    {
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminForm('/marketing/categories', $token, ['name' => 'Desertlar'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $response = $this->adminForm('/marketing/categories', $token, ['name' => '  Shirinliklar ', 'image' => $this->png('icon.png', 200, 200), 'reason' => 'Yangi bo\'lim'])->assertCreated();
        $response->assertJsonPath('data.name', 'Shirinliklar')->assertJsonPath('data.slug', 'shirinliklar')->assertJsonPath('data.is_active', true)->assertJsonPath('data.sort_order', 2);

        $category = Category::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($category->image);
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertStringContainsString("/marketing/categories/{$category->id}/image", $response->json('data.image_preview'));
        $this->assertNotEmpty($category->refresh()->image_thumbs);

        $audit = AuditLog::where('action', 'admin.marketing.categories.created')->sole();
        $this->assertSame($member->id, (int) $audit->user_id);
        $this->assertSame('Yangi bo\'lim', $audit->payload_json['reason']);
        $this->assertSame('Shirinliklar', $audit->after['name']);

        $this->adminForm('/marketing/categories', $token, ['name' => 'X', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->adminForm('/marketing/categories', $token, ['name' => 'Rasmsiz', 'reason' => 'Sinov', 'image' => \Illuminate\Http\UploadedFile::fake()->create('x.gif', 10, 'image/gif')])->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_two_levels_only(): void
    {
        $token = $this->signIn($this->makeMember());
        $parent = $this->makeCategory('Ota');
        $child = $this->makeCategory('Bola', ['parent_id' => $parent->id]);
        $other = $this->makeCategory('Boshqa');

        $this->adminForm('/marketing/categories', $token, ['name' => 'Nevara', 'parent_id' => $child->id, 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');
        $this->adminForm('/marketing/categories', $token, ['name' => 'Nevara', 'parent_id' => 99999, 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');
        $this->adminForm('/marketing/categories', $token, ['name' => 'Ichki', 'parent_id' => $parent->id, 'reason' => 'Sinov'])->assertCreated()->assertJsonPath('data.parent.id', $parent->id);

        $this->adminForm("/marketing/categories/{$parent->id}", $token, ['parent_id' => $parent->id, 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');
        // A category that has children cannot go inside another one
        $this->adminForm("/marketing/categories/{$parent->id}", $token, ['parent_id' => $other->id, 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');
        $this->adminForm("/marketing/categories/{$child->id}", $token, ['parent_id' => '', 'reason' => 'Ko\'tarish'])->assertOk()->assertJsonPath('data.parent', null);
    }

    public function test_hide_rename_and_remove_the_icon(): void
    {
        $category = $this->makeCategory('Eski nom');
        $token = $this->signIn($this->makeMember());

        $this->adminForm("/marketing/categories/{$category->id}", $token, ['is_active' => '0'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminForm("/marketing/categories/{$category->id}", $token, ['is_active' => '0', 'name' => 'Yangi nom', 'image' => $this->png('i.png', 100, 100), 'reason' => 'Mavsum tugadi'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Yangi nom')->assertJsonPath('data.slug', 'yangi-nom');

        $audit = AuditLog::where('action', 'admin.marketing.categories.updated')->sole();
        $this->assertTrue($audit->before['is_active']);
        $this->assertFalse($audit->after['is_active']);
        $this->assertSame('Eski nom', $audit->before['name']);

        // Hidden categories leave the Mini App's feed
        $this->app['auth']->forgetGuards();
        $region = \App\Models\Region::forceCreate(['name_uz' => 'Toshkent', 'is_active' => true]);
        $feed = collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?region_id=' . $region->id)->assertOk()->json('data.categories'))->pluck('id')->all();
        $this->assertNotContains($category->id, $feed);

        $path = $category->refresh()->image;
        Storage::disk('public')->assertExists($path);
        $this->adminForm("/marketing/categories/{$category->id}", $token, ['remove_image' => '1', 'reason' => 'Ikonka yoqmadi'])->assertOk()->assertJsonPath('data.image_url', null);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($category->refresh()->image);

        $this->adminForm('/marketing/categories/99999', $token, ['name' => 'Yo\'q', 'reason' => 'Sinov'])->assertNotFound();
    }

    public function test_reorder_saves_the_whole_list_and_refuses_a_stale_one(): void
    {
        $token = $this->signIn($this->makeMember());
        $burgers = Category::where('slug', 'like', 'burgers-%')->firstOrFail();
        $a = $this->makeCategory('A kategoriya');
        $b = $this->makeCategory('B kategoriya');
        $updatedBefore = $b->updated_at;

        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [$b->id, $a->id, $burgers->id]])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [$b->id, $a->id], 'reason' => 'Tartib'])->assertStatus(409)->assertJsonPath('code', 'order_stale');
        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [$b->id, $a->id, $burgers->id, 99999], 'reason' => 'Tartib'])->assertStatus(409);
        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [], 'reason' => 'Tartib'])->assertStatus(422);

        $this->travel(5)->minutes();
        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [$b->id, $a->id, $burgers->id], 'reason' => 'Yangi tartib'])->assertOk();
        $this->assertSame(['B kategoriya', 'A kategoriya', 'Burgers'], $this->names($token));
        $this->assertSame([1, 2, 3], [$b->refresh()->sort_order, $a->refresh()->sort_order, $burgers->refresh()->sort_order]);
        // The menu version (POS) follows updated_at
        $this->assertTrue($b->updated_at->gt($updatedBefore));

        $audit = AuditLog::where('action', 'admin.marketing.categories.reordered')->sole();
        $this->assertSame('Yangi tartib', $audit->payload_json['reason']);
        $this->assertSame(1, $audit->after['order'][$b->id]);

        // The same order again changes nothing and is not audited
        $this->adminJson('PUT', '/marketing/categories/order', $token, ['ids' => [$b->id, $a->id, $burgers->id], 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.marketing.categories.reordered')->count());
    }

    public function test_delete_needs_a_step_up_and_only_an_empty_category_goes(): void
    {
        $empty = $this->makeCategory('Bo\'sh');
        $withChild = $this->makeCategory('Ota');
        $this->makeCategory('Bola', ['parent_id' => $withChild->id]);
        $withProducts = $this->product->category;
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminJson('DELETE', "/marketing/categories/{$empty->id}", $token, ['reason' => 'Keraksiz'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($member, $token);
        $this->adminJson('DELETE', "/marketing/categories/{$empty->id}", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('DELETE', "/marketing/categories/{$withProducts->id}", $token, ['reason' => 'Keraksiz'])->assertStatus(409)->assertJsonPath('code', 'category_in_use')->assertJsonPath('data.products', 1);
        $this->adminJson('DELETE', "/marketing/categories/{$withChild->id}", $token, ['reason' => 'Keraksiz'])->assertStatus(409)->assertJsonPath('data.children', 1);

        $this->adminJson('DELETE', "/marketing/categories/{$empty->id}", $token, ['reason' => 'Keraksiz'])->assertOk();
        $this->assertSoftDeleted($empty);
        $this->assertSame('Bo\'sh', AuditLog::where('action', 'admin.marketing.categories.deleted')->sole()->before['name']);
    }

    public function test_icon_endpoint_serves_the_stored_file(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $category = $this->makeCategory('Rasmli');

        $this->adminJson('GET', "/marketing/categories/{$category->id}/image", $token)->assertNotFound()->assertJsonPath('code', 'no_image');

        app(\App\Services\Catalog\MenuImageService::class)->replace($category, $this->png('i.png', 80, 80), 'categories');
        $response = $this->adminJson('GET', "/marketing/categories/{$category->id}/image", $token)->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }
}
