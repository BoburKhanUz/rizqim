<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-12: the store settings the panel edits that the API lacked (name, timezone), and the owner's audit log read
 * with bounded ids and zone entries.
 */
class ManagementSettingsApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function settings(array $overrides = []): array
    {
        return array_merge(['is_open' => true, 'payment_settings' => ['cash' => ['enabled' => true]]], $overrides);
    }

    public function test_the_owner_renames_the_store_and_sets_its_timezone(): void
    {
        $this->as($this->owner, 'GET', '/api/v1/owner/settings')->assertOk()->assertJsonPath('data.timezone', 'Asia/Tashkent');

        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $this->settings(['name' => 'Yangi nom', 'timezone' => 'Asia/Samarkand']))->assertOk();
        $this->store->refresh();
        $this->assertSame('Yangi nom', $this->store->name);
        $this->assertSame('Asia/Samarkand', $this->store->timezone);

        // Left out: kept (older clients never send them)
        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $this->settings())->assertOk();
        $this->assertSame('Yangi nom', $this->store->refresh()->name);

        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $this->settings(['timezone' => 'Mars/Olympus']))
            ->assertStatus(422)->assertJsonPath('errors.timezone.0', "Vaqt mintaqasi noto'g'ri (masalan Asia/Tashkent).");
        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $this->settings(['name' => '']))
            ->assertStatus(422)->assertJsonPath('errors.name.0', "Do'kon nomini kiriting.");

        $this->as($this->manager, 'POST', '/api/v1/owner/settings', $this->settings(['name' => 'Mine']))->assertForbidden();
        $this->assertSame('Asia/Samarkand', $this->store->refresh()->timezone);
    }

    public function test_the_product_api_sets_the_menu_position_like_the_panel(): void
    {
        $variants = json_encode([['name' => 'Oddiy', 'price' => 30000]]);

        $id = $this->as($this->owner, 'POST', '/api/v1/owner/products', [
            'name' => 'Lavash', 'category_id' => $this->product->category_id, 'is_active' => true, 'variants' => $variants, 'sort_order' => 7,
        ])->assertOk()->json('data.id');
        $this->assertSame(7, (int) \App\Models\Product::findOrFail($id)->sort_order);

        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$id}", [
            'name' => 'Lavash', 'category_id' => $this->product->category_id, 'variants' => $variants, 'sort_order' => 2,
        ])->assertOk();
        $this->assertSame(2, (int) \App\Models\Product::findOrFail($id)->sort_order);

        $this->as($this->owner, 'POST', '/api/v1/owner/products', [
            'name' => 'X', 'category_id' => $this->product->category_id, 'variants' => $variants, 'sort_order' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('sort_order');
    }

    public function test_the_audit_log_filters_zone_entries_and_bounds_ids(): void
    {
        $zone = $this->makeZone();
        $zone->update(['delivery_price' => 15000]);

        $page = $this->as($this->owner, 'GET', "/api/v1/owner/audit-logs?subject_type=DeliveryZone&subject_id={$zone->id}")->assertOk()->json('data');
        $this->assertSame(2, $page['total']);
        $this->assertSame('delivery_zone.updated', $page['data'][0]['action']);
        $this->assertSame(['delivery_price' => '15000.00'], $page['data'][0]['after']);

        foreach (['user_id', 'branch_id', 'subject_id'] as $field) {
            $this->as($this->owner, 'GET', "/api/v1/owner/audit-logs?{$field}=99999999999999999999")->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $this->as($this->manager, 'GET', '/api/v1/owner/audit-logs')->assertForbidden();
        $this->assertSame(0, AuditLog::where('store_id', $this->foreignStore->id)->count());
    }
}
