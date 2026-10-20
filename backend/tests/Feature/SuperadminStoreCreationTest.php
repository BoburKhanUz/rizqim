<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\Branch;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SuperadminStoreCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Region $region;
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPER_ADMIN->value,
            'telegram_id' => '9001',
        ]);
        $this->region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $this->district = District::create(['region_id' => $this->region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
    }

    /**
     * The required fields, plus whichever of the optional phone, owner_phone and branch_phone are given.
     */
    private function payload(array $phones = []): array
    {
        return $phones + [
            'name' => 'Osh Markazi',
            'commission_rate' => 10,
            'owner_name' => 'Aziz',
            'branch_region_id' => $this->region->id,
            'branch_district_id' => $this->district->id,
            'branch_address' => 'Chilonzor 1-kvartal',
            'branch_lat' => 41.285,
            'branch_lng' => 69.204,
            'branch_radius' => 5,
        ];
    }

    public function test_store_is_created_without_the_optional_phones(): void
    {
        config(['services.telegram.bot_token' => '123456:store-test-token']);
        Queue::fake([SendTelegramRequest::class]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/superadmin/stores', $this->payload());

        $response->assertOk()->assertJsonPath('success', true);

        $store = Store::findOrFail($response->json('data.store_id'));
        $owner = User::where('store_id', $store->id)->where('role', UserRole::OWNER->value)->sole();
        $this->assertNull($owner->phone);
        $this->assertNull(Branch::where('store_id', $store->id)->sole()->phone);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '9001'
            && str_contains($job->params['text'], 'Aziz</b> ()'));
    }

    public function test_invite_links_use_the_configured_bot_username(): void
    {
        config(['services.telegram.bot_token' => '123456:store-test-token', 'services.telegram.bot_username' => 'rizqim_live_bot']);
        Queue::fake([SendTelegramRequest::class]);

        $phones = ['phone' => '+998901112233', 'owner_phone' => '+998901112244', 'branch_phone' => '+998901112255'];
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/superadmin/stores', $this->payload($phones))->assertOk();

        $token = User::where('role', UserRole::OWNER->value)->value('telegram_invite_token');
        $link = "https://t.me/rizqim_live_bot?start=invite_{$token}";

        $created->assertJsonPath('data.invite_link', $link);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => str_contains($job->params['text'], $link));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/superadmin/stores')
            ->assertOk()
            ->assertJsonPath('data.0.owner.invite_link', $link);
    }
}
