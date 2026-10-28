<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-32: what the old store panel (/admin) gave owners that the partner web panel now reads from the API: the store's
 * Telegram group link and the owner's own terminal PIN.
 */
class PartnerPanelParityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    public function test_owner_settings_carry_the_store_group_link_and_whether_a_group_is_linked(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_bot']);

        $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.telegram.group_link', 'https://t.me/rizqim_bot?start=store_' . $this->store->uuid)
            ->assertJsonPath('data.telegram.group_linked', false);

        $this->store->forceFill(['telegram_admin_chat_id' => '-1001234567'])->save();
        $this->actingAs($this->owner->fresh())->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.telegram.group_linked', true)
            // The chat id itself never leaves the server
            ->assertJsonMissingPath('data.telegram_admin_chat_id');

        config(['services.telegram.bot_username' => null]);
        $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()->assertJsonPath('data.telegram.group_link', null);
    }

    public function test_the_owner_sets_their_own_terminal_pin(): void
    {
        $this->actingAs($this->owner)->putJson("/api/v1/owner/staff/{$this->owner->id}/pin", ['pin' => '1234'])->assertStatus(422);
        $this->actingAs($this->owner)->putJson("/api/v1/owner/staff/{$this->owner->id}/pin", ['pin' => '4827'])
            ->assertOk()->assertJsonPath('data.user_id', $this->owner->id);
        $this->assertNotNull($this->owner->fresh()->pin_set_at);

        // A manager sets their own too, never the owner's
        $this->actingAs($this->manager)->putJson("/api/v1/owner/staff/{$this->manager->id}/pin", ['pin' => '5938'])->assertOk();
        $this->actingAs($this->manager)->putJson("/api/v1/owner/staff/{$this->owner->id}/pin", ['pin' => '5938'])->assertNotFound();
        $this->assertSame(UserRole::OWNER, $this->owner->fresh()->role);
    }
}
