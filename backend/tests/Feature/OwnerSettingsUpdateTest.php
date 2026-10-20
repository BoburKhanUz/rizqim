<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /owner/settings as the Mini App's OwnerSettings page sends it.
 */
class OwnerSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();
        $this->store->update(['payment_settings' => ['click' => ['enabled' => true, 'merchant_id' => 'm-1', 'service_id' => 's-1', 'secret_key' => 'click-SECRET']]]);
    }

    /**
     * The page's payload (OwnerSettingsPayload in customer/miniapp/src/pages/OwnerSettings.tsx).
     */
    private function save(array $settings = [], array $fields = [])
    {
        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $fields + [
            'logo' => null,
            'banner' => null,
            'description' => 'Burgers',
            'address' => 'Amir Temur 1',
            'location_url' => null,
            'is_open' => true,
            'min_order_amount' => 0,
            'payment_settings' => $settings + [
                'delivery_fee' => 0,
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'working_hours' => ['open' => '00:00', 'close' => '00:00'],
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true],
                'payme' => ['enabled' => false],
                'uzum' => ['enabled' => false],
                'paynet' => ['enabled' => false],
            ],
        ]);
    }

    public function test_the_delivery_and_pickup_switches_are_saved_and_checkout_follows_them(): void
    {
        $this->save(['delivery_enabled' => false, 'pickup_enabled' => true])->assertOk();

        $settings = $this->store->fresh()->payment_settings;
        $this->assertFalse($settings['delivery_enabled']);
        $this->assertTrue($settings['pickup_enabled']);
        $this->assertSame('click-SECRET', $settings['click']['secret_key'], 'the secret-preserving merge still applies');
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/settings')
            ->assertJsonPath('data.payment_settings.delivery_enabled', false)
            ->assertJsonPath('data.payment_settings.pickup_enabled', true);

        $order = fn (string $type) => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => $type,
            'branch_id' => $this->branch->id,
            'address' => 'Amir Temur 1', 'latitude' => 41.311081, 'longitude' => 69.240562,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ]);
        $order('delivery')->assertStatus(400)->assertJsonPath('message', "Ushbu restoranda yetkazib berish xizmati vaqtincha to'xtatilgan.");
        $order('pickup')->assertCreated();

        $this->save(['delivery_enabled' => true, 'pickup_enabled' => false])->assertOk();
        $settings = $this->store->fresh()->payment_settings;
        $this->assertTrue($settings['delivery_enabled']);
        $this->assertFalse($settings['pickup_enabled']);
    }

    public function test_a_client_that_leaves_the_switches_out_keeps_them_and_garbage_is_refused(): void
    {
        $this->save(['delivery_enabled' => false])->assertOk();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['delivery_fee' => 5000],
        ])->assertOk();
        $this->assertFalse($this->store->fresh()->payment_settings['delivery_enabled']);

        $this->save(['pickup_enabled' => 'sometimes'])->assertStatus(422)->assertJsonValidationErrors('payment_settings.pickup_enabled');
    }

    public function test_the_main_branch_takes_the_point_a_map_link_shows_and_keeps_its_own_otherwise(): void
    {
        // Branches updated since the main one was created: "the first branch" must still be the main one
        $this->otherBranch->update(['name' => 'Second Branch (renamed)']);

        $links = [
            // The page's own "current location" link: longitude first
            'https://yandex.com/maps/?pt=69.279737,41.311151&z=16&l=map' => [41.311151, 69.279737],
            // A shared Yandex link: comma encoded, and an sll= that is not the point
            'https://yandex.uz/maps/10335/tashkent/?sll=60.1,40.1&ll=69.2401%2C41.2995&z=12' => [41.2995, 69.2401],
            // Google: latitude first
            'https://maps.google.com/?q=41.3275,69.2817' => [41.3275, 69.2817],
            'https://www.google.com/maps/@41.2856,69.2034,15z' => [41.2856, 69.2034],
        ];

        foreach ($links as $link => [$lat, $lng]) {
            $this->save([], ['location_url' => $link])->assertOk();

            $branch = $this->branch->fresh();
            $this->assertEqualsWithDelta([$lat, $lng], [(float) $branch->latitude, (float) $branch->longitude], 0.000001, $link);
            $this->assertSame('Amir Temur 1', $branch->address);
        }

        // No point in the link (or an impossible one): the branch keeps its location
        foreach (['https://yandex.uz/maps/org/rizqim/123456/', 'https://yandex.com/maps/?pt=200.5,41.3'] as $link) {
            $this->save([], ['location_url' => $link])->assertOk();
            $this->assertEqualsWithDelta([41.2856, 69.2034], [(float) $this->branch->fresh()->latitude, (float) $this->branch->fresh()->longitude], 0.000001, $link);
        }

        $this->assertEqualsWithDelta(41.311081, (float) Branch::find($this->otherBranch->id)->latitude, 0.000001);
    }
}
