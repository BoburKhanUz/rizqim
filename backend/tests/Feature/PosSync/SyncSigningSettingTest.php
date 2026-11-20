<?php

namespace Tests\Feature\PosSync;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4, owner answer 4): the owner's switch between observe and enforce
 * (GET / PUT /owner/pos/sync-signing). Store-wide, audited, never from a PIN session; turning it on while a till does
 * not sign yet needs a second yes.
 */
class SyncSigningSettingTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;
    use SignsOperations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(User $user, string $method, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->json($method, '/api/v1/owner/pos/sync-signing', $data);
    }

    public function test_the_owner_sees_the_mode_and_turns_enforce_on_and_off_audited(): void
    {
        $this->as($this->owner, 'GET')->assertOk()
            ->assertJsonPath('data.enforce', false)
            ->assertJsonPath('data.signing_tills', 1)
            ->assertJsonPath('data.unsigned_tills', []);

        $this->as($this->owner, 'PUT', ['enforce' => true])->assertOk()->assertJsonPath('data.enforce', true);
        $this->assertTrue((bool) $this->store->fresh()->pos_sync_enforce_signatures);
        $audit = AuditLog::where('action', 'settings.pos_sync_signatures_changed')->sole();
        $this->assertSame([$this->owner->id, $this->store->id, ['enforce' => false], ['enforce' => true]], [$audit->user_id, $audit->store_id, $audit->before, $audit->after]);

        // Saying the same again changes and audits nothing
        $this->as($this->owner, 'PUT', ['enforce' => true])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'settings.pos_sync_signatures_changed')->count());

        $this->as($this->owner, 'PUT', ['enforce' => false])->assertOk()->assertJsonPath('data.enforce', false);
        $this->assertFalse((bool) $this->store->fresh()->pos_sync_enforce_signatures);
        $this->assertSame(2, AuditLog::where('action', 'settings.pos_sync_signatures_changed')->count());

        $this->as($this->owner, 'PUT', [])->assertStatus(422);
    }

    public function test_turning_enforce_on_while_a_till_does_not_sign_needs_a_second_yes(): void
    {
        // The till's app is from before signing: what it sent last came unsigned
        $this->sync($this->walkInSale()['ops'])->assertOk();

        $view = $this->as($this->owner, 'GET')->assertOk()->assertJsonPath('data.signing_tills', 0)->json('data.unsigned_tills');
        $this->assertSame([[
            'uuid' => (string) $this->till['device']->uuid, 'name' => (string) $this->till['device']->name, 'branch_id' => $this->branch->id,
            'has_key' => true, 'last_sig_status' => 'missing',
        ]], $view);

        $this->as($this->owner, 'PUT', ['enforce' => true])->assertStatus(409)
            ->assertJsonPath('data.reason', 'tills_not_signing')
            ->assertJsonPath('data.unsigned_tills.0.uuid', (string) $this->till['device']->uuid);
        $this->assertFalse((bool) $this->store->fresh()->pos_sync_enforce_signatures);
        $this->assertSame(0, AuditLog::where('action', 'settings.pos_sync_signatures_changed')->count());

        $this->as($this->owner, 'PUT', ['enforce' => true, 'confirm_unsigned' => true])->assertOk()->assertJsonPath('data.enforce', true);
        $audit = AuditLog::where('action', 'settings.pos_sync_signatures_changed')->sole();
        $this->assertSame([(string) $this->till['device']->uuid], $audit->payload_json['unsigned_tills']);
        // Turning it off never asks
        $this->as($this->owner, 'PUT', ['enforce' => false])->assertOk();

        // Once the till signs it is no longer in the way
        $this->sync($this->signed($this->walkInSale('K1-002')['ops'], $this->till['sync_key'], (string) $this->till['device']->uuid))->assertOk();
        $this->as($this->owner, 'GET')->assertOk()->assertJsonPath('data.unsigned_tills', [])->assertJsonPath('data.signing_tills', 1);
        $this->as($this->owner, 'PUT', ['enforce' => true])->assertOk();
    }

    public function test_only_the_owner_side_of_the_web_panel_changes_it_and_the_key_is_never_shown(): void
    {
        $this->as($this->cashier, 'GET')->assertStatus(403);
        $this->as($this->cashier, 'PUT', ['enforce' => true])->assertStatus(403);
        $this->as($this->manager, 'PUT', ['enforce' => true])->assertStatus(403);

        // A PIN session on the till, the manager's too
        $this->tillAs('PUT', '/owner/pos/sync-signing', ['enforce' => true])->assertStatus(403);
        $this->tillAs('PUT', '/owner/pos/sync-signing', ['enforce' => true], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertStatus(403);
        $this->assertFalse((bool) $this->store->fresh()->pos_sync_enforce_signatures);

        $body = $this->as($this->owner, 'PUT', ['enforce' => true])->assertOk()->getContent();
        $this->assertStringNotContainsString($this->till['sync_key']['key'], $body);
        $this->assertStringNotContainsString('sync_key', $body);
        $this->assertStringNotContainsString($this->till['sync_key']['key'], (string) json_encode(AuditLog::where('action', 'settings.pos_sync_signatures_changed')->sole()->getAttributes()));
    }
}
