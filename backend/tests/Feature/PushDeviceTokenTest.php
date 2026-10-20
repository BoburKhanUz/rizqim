<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushDeviceTokenTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush(configured: false);
        $this->setUpLifecycleStore();
    }

    public function test_registering_a_device_with_a_push_token_never_returns_the_token(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/devices', [
            'type' => 'customer_mobile',
            'push_token' => 'fcm-token-registered-1',
        ])->assertCreated()->assertJsonPath('data.has_push_token', true);

        $this->assertStringNotContainsString('fcm-token-registered-1', $response->getContent());
        $this->assertSame('fcm-token-registered-1', Device::first()->push_token);
    }

    public function test_the_app_can_set_refresh_and_clear_its_token(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, null);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'first-token-123456'])
            ->assertOk()->assertJsonPath('data.has_push_token', true);
        $this->assertSame('first-token-123456', $device->fresh()->push_token);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'second-token-12345'])
            ->assertOk();
        $this->assertStringNotContainsString('second-token-12345', $response->getContent());
        $this->assertSame('second-token-12345', $device->fresh()->push_token);

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/devices/{$device->uuid}/push-token")
            ->assertOk()->assertJsonPath('data.has_push_token', false);
        $this->assertNull($device->fresh()->push_token);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'third-token-123456'])->assertOk();
        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => null])->assertOk();
        $this->assertNull($device->fresh()->push_token);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", [])->assertUnprocessable();
    }

    public function test_nobody_can_touch_another_persons_device_token(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-token-12345');
        $stranger = $this->makeCustomer();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'hijacked-token-1234'])->assertNotFound();
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/devices/{$device->uuid}/push-token")->assertNotFound();
        // Staff of the same store do not get it either
        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/v1/devices/{$device->uuid}/push-token")->assertNotFound();

        $this->assertSame('customer-token-12345', $device->fresh()->push_token);
    }

    public function test_a_revoked_device_cannot_get_a_token(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, null);
        $device->forceFill(['revoked_at' => now()])->save();

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'late-token-1234567'])->assertNotFound();
    }

    public function test_a_token_belongs_to_one_install_only(): void
    {
        // Another person signs in on the same phone: the earlier person's row must stop receiving pushes
        $first = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, null);
        $second = $this->makeDevice($this->makeCustomer(), DeviceType::CUSTOMER_MOBILE, null);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/devices/{$first->uuid}/push-token", ['push_token' => 'shared-phone-token'])->assertOk();
        $this->actingAs($second->user, 'sanctum')
            ->putJson("/api/v1/devices/{$second->uuid}/push-token", ['push_token' => 'shared-phone-token'])->assertOk();

        $this->assertNull($first->fresh()->push_token);
        $this->assertSame('shared-phone-token', $second->fresh()->push_token);

        // The same through registration
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/devices', [
            'type' => 'customer_mobile', 'uuid' => $first->uuid, 'push_token' => 'shared-phone-token',
        ])->assertOk();
        $this->assertSame('shared-phone-token', $first->fresh()->push_token);
        $this->assertNull($second->fresh()->push_token);
    }

    public function test_signing_out_stops_pushes_to_that_install(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'logout-token-123456');
        $other = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'other-token-1234567');
        $login = app(\App\Services\Auth\TokenService::class)->issuePair($this->customer, $device);

        $this->bearerJson('POST', '/api/v1/auth/logout', $login->accessToken)->assertOk();
        $this->assertNull($device->fresh()->push_token);
        $this->assertSame('other-token-1234567', $other->fresh()->push_token);

        $device->forceFill(['push_token' => 'again-token-1234567'])->save();
        $everywhere = app(\App\Services\Auth\TokenService::class)->issuePair($this->customer, $device);
        $this->bearerJson('POST', '/api/v1/auth/logout-all', $everywhere->accessToken)->assertOk();
        $this->assertNull($device->fresh()->push_token);
        $this->assertNull($other->fresh()->push_token);
    }

    public function test_revoking_a_device_clears_its_token(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'revoke-token-123456');

        $this->actingAs($this->customer, 'sanctum')->deleteJson("/api/v1/devices/{$device->uuid}")->assertOk();

        $this->assertNull($device->fresh()->push_token);
    }

    public function test_the_token_endpoints_need_a_signed_in_user(): void
    {
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'x-token-123456789');

        $this->putJson("/api/v1/devices/{$device->uuid}/push-token", ['push_token' => 'y-token-123456789'])->assertUnauthorized();
        $this->deleteJson("/api/v1/devices/{$device->uuid}/push-token")->assertUnauthorized();
    }
}
