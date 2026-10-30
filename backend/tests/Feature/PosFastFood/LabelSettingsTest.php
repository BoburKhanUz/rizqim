<?php

namespace Tests\Feature\PosFastFood;

use App\Domain\Pos\Labels\LabelSettings;
use App\Domain\Pos\Setup\FormatProfile;
use App\Models\AuditLog;
use App\Models\BranchLabelSettings;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosRestaurant\PosRestaurantFixtures;
use Tests\TestCase;

/**
 * P6-32 (B7): a branch's sticker and label settings: the owner API (defaults, partial saves, validation, audit), the
 * `labels` module gate, who may change them, and the bootstrap block the till prints from.
 */
class LabelSettingsTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant(FormatProfile::QAHVAXONA);
    }

    private function url(): string
    {
        return "/owner/branches/{$this->branch->id}/label-settings";
    }

    private function categoryId(string $name): int
    {
        return (int) Category::create(['name' => $name, 'slug' => $name . '-' . uniqid(), 'is_active' => true])->id;
    }

    public function test_nothing_saved_reads_as_the_defaults(): void
    {
        $data = $this->pos($this->owner, 'GET', $this->url())->assertOk()->json('data');

        $this->assertSame([
            'branch_id' => $this->branch->id, 'configured' => false, 'size' => '40x30', 'size_label' => '40 × 30 mm',
            'width_mm' => 40, 'height_mm' => 30, 'scope' => 'all', 'scope_label' => 'Hamma taomlarga', 'category_ids' => [],
            'per_unit' => true, 'bag_label' => true, 'updated_at' => null,
        ], $data);
    }

    public function test_a_partial_save_keeps_the_rest_and_is_audited(): void
    {
        $drinks = $this->categoryId('Ichimliklar');

        $first = $this->pos($this->owner, 'PUT', $this->url(), ['size' => '58x40', 'scope' => 'categories', 'category_ids' => [$drinks]])->assertOk();
        $this->assertSame('58x40', $first->json('data.size'));
        $this->assertSame(58, $first->json('data.width_mm'));
        $this->assertSame(40, $first->json('data.height_mm'));
        $this->assertSame([$drinks], $first->json('data.category_ids'));
        $this->assertTrue($first->json('data.configured'));

        $second = $this->pos($this->owner, 'PUT', $this->url(), ['per_unit' => false, 'bag_label' => false])->assertOk();
        $this->assertSame('58x40', $second->json('data.size'));
        $this->assertSame('categories', $second->json('data.scope'));
        $this->assertFalse($second->json('data.per_unit'));
        $this->assertFalse($second->json('data.bag_label'));

        $this->assertSame(1, BranchLabelSettings::count());
        $logs = AuditLog::where('action', 'settings.label_settings_changed')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('58x40', $logs[0]->after['size']);
        $this->assertSame(['per_unit' => true, 'bag_label' => true], array_intersect_key($logs[1]->before, ['per_unit' => 1, 'bag_label' => 1]));
        $this->assertSame($this->branch->id, (int) $logs[0]->branch_id);
    }

    public function test_all_clears_the_categories_and_categories_need_some(): void
    {
        $drinks = $this->categoryId('Ichimliklar');
        $this->pos($this->owner, 'PUT', $this->url(), ['scope' => 'categories', 'category_ids' => [$drinks]])->assertOk();

        $this->pos($this->owner, 'PUT', $this->url(), ['scope' => 'all'])->assertOk()->assertJsonPath('data.category_ids', []);
        $this->assertNull(BranchLabelSettings::firstOrFail()->category_ids);

        $this->pos($this->owner, 'PUT', $this->url(), ['scope' => 'categories'])->assertStatus(422)->assertJsonValidationErrors('category_ids');
        $this->pos($this->owner, 'PUT', $this->url(), ['scope' => 'categories', 'category_ids' => [999999]])->assertStatus(422)->assertJsonValidationErrors('category_ids');
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->pos($this->owner, 'PUT', $this->url(), ['size' => '100x100'])->assertStatus(422)->assertJsonValidationErrors('size');
        $this->pos($this->owner, 'PUT', $this->url(), ['scope' => 'some'])->assertStatus(422)->assertJsonValidationErrors('scope');
        $this->pos($this->owner, 'PUT', $this->url(), ['per_unit' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors('per_unit');
        $this->assertSame(0, BranchLabelSettings::count());
    }

    public function test_the_labels_module_gates_the_settings(): void
    {
        $this->profile(FormatProfile::CHOYXONA);
        $this->pos($this->owner, 'GET', $this->url())->assertStatus(403)->assertJsonPath('code', 'module_disabled');
        $this->pos($this->owner, 'PUT', $this->url(), ['size' => '58x40'])->assertStatus(403);

        // The owner can switch it on over the profile
        $this->profile(FormatProfile::CHOYXONA, ['labels' => true]);
        $this->pos($this->owner, 'PUT', $this->url(), ['size' => '58x40'])->assertOk();
    }

    public function test_only_settings_managers_of_the_store_may_change_them(): void
    {
        $this->pos($this->cashier, 'GET', $this->url())->assertStatus(403);
        $this->pos($this->cashier, 'PUT', $this->url(), ['size' => '58x40'])->assertStatus(403);

        $foreign = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $this->makeStore('Foreign Grill'));
        $this->pos($foreign, 'GET', $this->url())->assertStatus(404);
        $this->pos($foreign, 'PUT', $this->url(), ['size' => '58x40'])->assertStatus(404);
        $this->assertSame(0, BranchLabelSettings::count());
    }

    public function test_the_bootstrap_carries_the_labels_block_only_with_the_module(): void
    {
        $drinks = $this->categoryId('Ichimliklar');
        $this->pos($this->owner, 'PUT', $this->url(), ['size' => '58x40', 'scope' => 'categories', 'category_ids' => [$drinks]])->assertOk();

        $labels = $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk()->json('data.labels');
        $this->assertSame('58x40', $labels['size']);
        $this->assertSame([$drinks], $labels['category_ids']);

        $this->profile(FormatProfile::CHOYXONA);
        $response = $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk();
        $this->assertArrayHasKey('labels', $response->json('data'));
        $this->assertNull($response->json('data.labels'));
    }

    public function test_the_scope_decides_which_categories_get_a_sticker(): void
    {
        $drinks = $this->categoryId('Ichimliklar');
        $food = $this->categoryId('Taomlar');

        $all = ['scope' => 'all', 'category_ids' => []];
        $some = ['scope' => 'categories', 'category_ids' => [$drinks]];

        $this->assertTrue(LabelSettings::appliesTo($all, $food));
        $this->assertTrue(LabelSettings::appliesTo($all, null));
        $this->assertTrue(LabelSettings::appliesTo($some, $drinks));
        $this->assertFalse(LabelSettings::appliesTo($some, $food));
        $this->assertFalse(LabelSettings::appliesTo($some, null));
    }
}
