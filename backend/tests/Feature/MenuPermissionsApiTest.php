<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Product;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P2-30: the owner's product and category endpoints by permission (menu.view reads, menu.manage writes) instead of the
 * owner role, a product's modifier group links (and saving the attach list no longer wiping their rules), removing a
 * product image, a new variant's stock, and Uzbek errors in the standard shape.
 */
class MenuPermissionsApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpManagement();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productForm(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Lavash',
            'category_id' => $this->product->category_id,
            'is_active' => true,
            'variants' => json_encode([['name' => 'Oddiy', 'price' => 32000]]),
        ];
    }

    public function test_menu_view_reads_the_menu_and_menu_manage_writes_it(): void
    {
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        // menu.view: owner, manager, cashier, waiter, cook
        foreach ([$this->owner, $this->manager, $cook, $cashier] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/products')->assertOk()->assertJsonPath('data.0.id', $this->product->id);
            $this->as($user, 'GET', '/api/v1/owner/categories')->assertOk()->assertJsonPath('success', true);
        }
        foreach ([$this->courier, $this->customer] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/products')->assertForbidden()->assertExactJson(['success' => false, 'message' => 'Ruxsat etilmagan', 'data' => []]);
            $this->as($user, 'GET', '/api/v1/owner/categories')->assertForbidden();
        }

        // menu.manage: the owner only by default
        foreach ([$this->manager, $cook, $cashier] as $user) {
            $this->as($user, 'POST', '/api/v1/owner/products', $this->productForm())->assertForbidden();
            $this->as($user, 'POST', "/api/v1/owner/products/{$this->product->id}?toggle_active=1")->assertForbidden();
            $this->as($user, 'DELETE', "/api/v1/owner/products/{$this->product->id}")->assertForbidden();
            $this->as($user, 'DELETE', "/api/v1/owner/products/{$this->product->id}/image")->assertForbidden();
        }
        $this->assertSame('active', $this->product->fresh()->status);
        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$this->product->id}?toggle_active=1")->assertOk()->assertJsonPath('data.status', 'inactive');

        // An owner may grant it: the manager then edits the store's menu
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::MENU_MANAGE, true);
        $this->as($this->manager, 'POST', '/api/v1/owner/products', $this->productForm())->assertOk()->assertJsonPath('data.store_id', $this->store->id);

        // Categories are created by the super admin only
        $this->as($this->owner, 'POST', '/api/v1/owner/categories', ['name' => 'Yangi'])->assertForbidden();
    }

    public function test_products_stay_in_their_store(): void
    {
        $foreign = Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Foreign', 'slug' => 'foreign-x', 'status' => 'active']);

        $this->assertNotContains($foreign->id, array_column($this->as($this->owner, 'GET', '/api/v1/owner/products')->json('data'), 'id'));
        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$foreign->id}", $this->productForm())->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Mahsulot topilmadi.', 'data' => []]);
        $this->as($this->owner, 'DELETE', "/api/v1/owner/products/{$foreign->id}")->assertNotFound();
        $this->as($this->owner, 'DELETE', "/api/v1/owner/products/{$foreign->id}/image")->assertNotFound();
        $this->as($this->owner, 'POST', '/api/v1/owner/products/99999999999999999999')->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => $foreign->id, 'name' => 'Foreign', 'deleted_at' => null]);

        // A super admin has every permission but no store: refused, not someone's menu
        $superAdmin = \App\Models\User::create(['name' => 'Root', 'phone' => '+998900000777', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('secret-password')]);
        $this->as($superAdmin, 'GET', '/api/v1/owner/products')->assertForbidden()->assertJsonPath('data.reason', 'store_required');
    }

    public function test_a_new_variant_takes_its_stock(): void
    {
        $id = $this->as($this->owner, 'POST', '/api/v1/owner/products', $this->productForm([
            'variants' => json_encode([
                ['name' => 'Kichik', 'price' => 20000, 'stock' => 12],
                ['name' => 'Katta', 'price' => 30000],
                ['name' => 'Vazn', 'price' => 90000, 'unit' => 'kg', 'stock' => '2.5'],
            ]),
        ]))->assertOk()->json('data.id');

        $variants = Product::findOrFail($id)->variants()->orderBy('id')->get();
        $this->assertEquals([12, 999, 2.5], $variants->map(fn ($variant) => (float) $variant->stock)->all());

        // A variant added on an update takes it too; an existing one keeps its stock (orders move it)
        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$id}", $this->productForm([
            'variants' => json_encode([
                ['id' => $variants[0]->id, 'name' => 'Kichik', 'price' => 20000, 'stock' => 500],
                ['name' => 'Yangi', 'price' => 25000, 'stock' => 7],
            ]),
        ]))->assertOk();
        $this->assertEquals([12, 7], Product::findOrFail($id)->variants()->orderBy('id')->get()->map(fn ($variant) => (float) $variant->stock)->all());

        // A piece variant counts whole pieces; the stock is a number in range
        $this->as($this->owner, 'POST', '/api/v1/owner/products', $this->productForm(['variants' => json_encode([['name' => 'X', 'price' => 1000, 'stock' => 1.5]])]))
            ->assertUnprocessable()->assertJsonPath('errors', ['variants.0.stock' => ["Donali taomning qoldig'i butun son bo'lishi kerak."]]);
        $errors = $this->as($this->owner, 'POST', '/api/v1/owner/products', $this->productForm(['variants' => json_encode([['name' => 'X', 'price' => 1000, 'stock' => -1]])]))
            ->assertUnprocessable()->json('errors');
        $this->assertSame(["Qoldiq kamida 0 bo'lishi kerak."], $errors['variants.0.stock']);
    }

    public function test_the_product_list_carries_its_modifier_group_links_and_the_attach_list_keeps_them(): void
    {
        $sauce = $this->as($this->owner, 'POST', '/api/v1/owner/modifier-groups', ['name' => 'Sous', 'modifiers' => [['name' => 'Achchiq', 'price_delta' => 2000]]])->assertCreated()->json('data.id');
        $extra = $this->as($this->owner, 'POST', '/api/v1/owner/modifier-groups', ['name' => "Qo'shimcha", 'max_select' => 3])->assertCreated()->json('data.id');

        $this->as($this->owner, 'PUT', "/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => [
            ['modifier_group_id' => $sauce, 'min_select' => 1, 'max_select' => 1, 'is_required' => true],
            ['modifier_group_id' => $extra],
        ]])->assertOk()->assertJsonPath('data.modifier_groups.0.product_rules', ['min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 0]);

        $links = fn () => collect(collect($this->as($this->manager, 'GET', '/api/v1/owner/products')->assertOk()->json('data'))->firstWhere('id', $this->product->id)['modifier_group_links'])
            ->map(fn (array $link) => array_intersect_key($link, array_flip(['modifier_group_id', 'min_select', 'max_select', 'is_required', 'sort_order'])))
            ->map(fn (array $link) => ['modifier_group_id' => $link['modifier_group_id'], 'min_select' => $link['min_select'], 'max_select' => $link['max_select'], 'is_required' => $link['is_required'], 'sort_order' => $link['sort_order']])
            ->all();
        $this->assertSame([
            ['modifier_group_id' => $sauce, 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 0],
            ['modifier_group_id' => $extra, 'min_select' => null, 'max_select' => null, 'is_required' => null, 'sort_order' => 1],
        ], $links());

        // Saving only the attach list (the order changed, no rules sent) keeps the product's rules
        $this->as($this->owner, 'PUT', "/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => [
            ['modifier_group_id' => $extra],
            ['modifier_group_id' => $sauce],
        ]])->assertOk();
        $this->assertSame([
            ['modifier_group_id' => $extra, 'min_select' => null, 'max_select' => null, 'is_required' => null, 'sort_order' => 0],
            ['modifier_group_id' => $sauce, 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 1],
        ], $links());

        // null resets a rule to the group's; a rule kept from before is still checked against the new one
        $this->as($this->owner, 'PUT', "/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => [
            ['modifier_group_id' => $sauce, 'is_required' => null, 'max_select' => null],
        ]])->assertOk();
        $this->assertSame([['modifier_group_id' => $sauce, 'min_select' => 1, 'max_select' => null, 'is_required' => null, 'sort_order' => 0]], $links());
        $this->as($this->owner, 'PUT', "/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => [
            ['modifier_group_id' => $sauce, 'min_select' => 3, 'max_select' => 2],
        ]])->assertUnprocessable()->assertJsonValidationErrors('groups.0.max_select');
    }

    public function test_the_owner_removes_a_product_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/lavash.webp', 'image');
        Storage::disk('public')->put('products/thumbs/lavash-sm.webp', 'thumb');
        $this->product->forceFill(['image' => 'products/lavash.webp', 'image_disk' => 'public', 'image_thumbs' => ['sm' => 'products/thumbs/lavash-sm.webp']])->save();

        $this->as($this->owner, 'DELETE', "/api/v1/owner/products/{$this->product->id}/image")->assertOk()
            ->assertJsonPath('message', "Rasm o'chirildi.")
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.image_url', null);

        Storage::disk('public')->assertMissing('products/lavash.webp');
        Storage::disk('public')->assertMissing('products/thumbs/lavash-sm.webp');
        $this->assertNull($this->product->fresh()->image_thumbs);

        // Again (no image): fine, nothing changes
        $this->as($this->owner, 'DELETE', "/api/v1/owner/products/{$this->product->id}/image")->assertOk();
    }

    public function test_product_errors_are_uzbek_in_the_standard_shape(): void
    {
        $response = $this->as($this->owner, 'POST', '/api/v1/owner/products', ['variants' => 'x'])->assertUnprocessable();
        $this->assertSame('Nomi majburiy.', $response->json('errors.name.0'));
        $this->assertSame('Kategoriya majburiy.', $response->json('errors.category_id.0'));
        $this->assertSame("Variantlar to'g'ri JSON matn bo'lishi kerak.", $response->json('errors.variants.0'));
        $this->assertSame('Nomi majburiy. (va yana 2 ta xato)', $response->json('message'));

        $this->as($this->owner, 'POST', '/api/v1/owner/products', $this->productForm(['category_id' => 999999]))
            ->assertUnprocessable()->assertJsonPath('errors.category_id.0', 'Kategoriya topilmadi.');
    }
}
