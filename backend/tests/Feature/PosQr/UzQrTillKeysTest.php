<?php

namespace Tests\Feature\PosQr;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave P: each till's own UzQR API key (encrypted at rest, never returned: has_api_key and the last four only), set
 * by the owner or a manager with settings.manage, audited; the store's STIR comes from its fiscal settings.
 */
class UzQrTillKeysTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->setUpOperator();
    }

    public function test_the_owner_sets_a_tills_key_which_is_encrypted_and_never_returned(): void
    {
        $response = $this->setTillKey()->assertOk()
            ->assertJsonPath('data.uuid', (string) $this->till['device']->uuid)
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonPath('data.api_key_last4', substr($this->tillKey, -4))
            ->assertJsonPath('data.ready', true);
        $this->assertStringNotContainsString($this->tillKey, $response->getContent());

        $raw = (string) DB::table('devices')->where('id', $this->till['device']->id)->value('uzqr_api_key');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString($this->tillKey, $raw);
        $this->assertSame($this->tillKey, Device::findOrFail($this->till['device']->id)->uzqr_api_key);

        $audit = AuditLog::where('action', 'devices.uzqr_key_changed')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString($this->tillKey, json_encode($audit->toArray()));

        $index = $this->owner('GET', '/owner/fiscal/uzqr')->assertOk()
            ->assertJsonPath('data.driver', 'uzqr_operator')
            ->assertJsonPath('data.tin', '301234567')
            ->assertJsonPath('data.tills.0.has_api_key', true);
        $this->assertStringNotContainsString($this->tillKey, $index->getContent());

        // The till's own device API never shows it either
        $this->assertStringNotContainsString($this->tillKey, json_encode(Device::findOrFail($this->till['device']->id)->toArray()));
    }

    public function test_a_masked_or_empty_key_keeps_the_stored_one_and_remove_clears_it(): void
    {
        $this->setTillKey()->assertOk();

        foreach (['••••' . substr($this->tillKey, -4), '********', null] as $masked) {
            $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['api_key' => $masked])->assertOk()->assertJsonPath('data.has_api_key', true);
            $this->assertSame($this->tillKey, Device::findOrFail($this->till['device']->id)->uzqr_api_key);
        }

        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['remove_api_key' => true])->assertOk()
            ->assertJsonPath('data.has_api_key', false)->assertJsonPath('data.api_key_last4', null)->assertJsonPath('data.ready', false);
        $this->assertNull(Device::findOrFail($this->till['device']->id)->uzqr_api_key);
    }

    public function test_the_key_is_validated(): void
    {
        foreach (['short', 'has a space inside it 123456', str_repeat('k', 513)] as $bad) {
            $this->setTillKey(null, $bad)->assertStatus(422)->assertJsonValidationErrors(['api_key']);
        }
        $this->assertNull(Device::findOrFail($this->till['device']->id)->uzqr_api_key);
    }

    public function test_only_settings_managers_of_the_tills_branch_set_it(): void
    {
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['api_key' => $this->tillKey], $this->cashier)->assertStatus(403);

        // A till of another store: not found
        $otherStore = $this->makeStore('Other Cafe');
        $otherBranch = $this->makeBranch($otherStore, 'Other Branch');
        $other = $this->pairTerminal($this->makeStaff(UserRole::OWNER, null, $otherStore), $otherBranch)['device'];
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$other->uuid}", ['api_key' => $this->tillKey])->assertStatus(404);
        $this->assertNull(Device::findOrFail($other->id)->uzqr_api_key);

        // A manager (no settings.manage) cannot either
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['api_key' => $this->tillKey], $this->manager)->assertStatus(403);
        $this->assertNull(Device::findOrFail($this->till['device']->id)->uzqr_api_key);
    }
}
