<?php

namespace Tests\Feature\PosLan;

use App\Domain\Pos\Lan\LanKeys;
use App\Enums\DeviceType;
use App\Models\AuditLog;
use App\Models\BranchLanSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5, pos/packages/pos_lan/README.md §2–3): the branch's LAN keys reach the
 * terminals of the branch only — the hub gets the secret, a client only its own key — never a normal staff or owner
 * sign-in; the owner picks the hub and rotates the key (audited, never the key itself).
 */
class PosLanKeysTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    private function secret(): string
    {
        return (string) base64_decode((string) BranchLanSettings::where('branch_id', $this->branch->id)->firstOrFail()->secret, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lanOf(string $token): ?array
    {
        return $this->tillAs('GET', '/pos/bootstrap', [], $token)->assertOk()->json('data.lan');
    }

    public function test_the_first_till_is_the_hub_and_gets_the_secret_the_terminals_and_a_signed_grant(): void
    {
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $phone = $this->pairTerminal($this->owner, $this->branch);
        $this->pairTerminal($this->owner, $this->otherBranch);

        $lan = $this->lanOf($this->cashierToken);
        $this->assertNotNull($lan);
        $secret = $this->secret();
        $this->assertSame(32, strlen($secret));
        $this->assertSame('hub', $lan['role']);
        $this->assertSame((string) $this->till['device']->uuid, $lan['device']);
        $this->assertSame(base64_encode($secret), $lan['hub_secret']);
        $this->assertSame(base64_encode(LanKeys::device($secret, (string) $this->till['device']->uuid)), $lan['device_key']);
        $this->assertSame(base64_encode(LanKeys::beacon($secret)), $lan['beacon_key']);
        $this->assertSame(['device' => (string) $this->till['device']->uuid, 'name' => 'Kassa', 'port' => 18480], $lan['hub']);
        $this->assertSame(1, $lan['key_id']);
        $this->assertSame(120, $lan['clock_window_seconds']);
        // The hub lets in the branch's terminals only
        $this->assertEqualsCanonicalizing(
            [(string) $this->till['device']->uuid, (string) $kds['device']->uuid, (string) $phone['device']->uuid],
            array_column($lan['devices'], 'uuid'),
        );

        $grant = LanKeys::verifyGrant($secret, (string) $lan['grant']);
        $this->assertNotNull($grant);
        $this->assertSame($this->cashier->id, $grant['staff_id']);
        $this->assertSame('cashier', $grant['role']);
        $this->assertSame((string) $this->till['device']->uuid, $grant['device']);
        $this->assertSame($this->branch->id, $grant['branch_id']);
        $this->assertContains('pos.take_payment', $grant['permissions']);
        $this->assertSame(36 * 3600, $grant['exp'] - $grant['iat']);
        // A grant is not signed with what a client holds: its own device key or the beacon key do not verify it
        $this->assertNull(LanKeys::verifyGrant(LanKeys::device($secret, (string) $this->till['device']->uuid), (string) $lan['grant']));
        $this->assertNull(LanKeys::verifyGrant($secret, (string) $lan['grant'] . 'x'));
    }

    public function test_a_client_gets_its_own_key_and_never_the_secret_and_a_normal_sign_in_gets_nothing(): void
    {
        $phone = $this->pairTerminal($this->owner, $this->branch);
        $waiterToken = $this->pinSession($phone, $this->waiter);

        $lan = $this->lanOf($waiterToken);
        $secret = $this->secret();
        $this->assertSame('client', $lan['role']);
        $this->assertNull($lan['hub_secret']);
        $this->assertNull($lan['devices']);
        $this->assertSame((string) $this->till['device']->uuid, $lan['hub']['device']);
        $this->assertSame(base64_encode(LanKeys::device($secret, (string) $phone['device']->uuid)), $lan['device_key']);
        $this->assertSame($this->waiter->id, LanKeys::verifyGrant($secret, (string) $lan['grant'])['staff_id'] ?? null);

        // A normal sign-in of the same people (web, Mini App): no keys at all
        $this->withBearer($this->manager->createToken('web')->plainTextToken);
        $this->getJson('/api/v1/pos/bootstrap')->assertOk()->assertJsonPath('data.lan', null);
        $this->withBearer($this->owner->createToken('web')->plainTextToken);
        $this->getJson('/api/v1/pos/bootstrap?branch_id=' . $this->branch->id)->assertOk()->assertJsonPath('data.lan', null);
    }

    public function test_a_display_reads_its_keys_with_its_own_token_without_a_grant(): void
    {
        $display = $this->pairTerminal($this->owner, $this->branch, DeviceType::DISPLAY);

        $this->withBearer($display['token']);
        $lan = $this->getJson('/api/v1/pos/lan')->assertOk()->json('data');
        $secret = $this->secret();
        $this->assertSame('client', $lan['role']);
        $this->assertNull($lan['grant']);
        $this->assertNull($lan['hub_secret']);
        $this->assertSame(base64_encode(LanKeys::device($secret, (string) $display['device']->uuid)), $lan['device_key']);

        // A staff session token is not a terminal's own token
        $this->withBearer($this->cashierToken);
        $this->getJson('/api/v1/pos/lan')->assertUnauthorized();
        // A store-wide terminal belongs to no branch's LAN
        $storeWide = $this->pairTerminal($this->owner, null);
        $this->withBearer($storeWide['token']);
        $this->getJson('/api/v1/pos/lan')->assertOk()->assertJsonPath('data', null);
    }

    public function test_the_owner_picks_the_hub_and_rotates_the_key_audited_without_the_key(): void
    {
        $second = $this->pairTerminal($this->owner, $this->branch);
        $secondToken = $this->pinSession($second, $this->manager, self::MANAGER_PIN);
        $other = $this->pairTerminal($this->owner, $this->otherBranch);
        $ownerToken = $this->owner->createToken('web')->plainTextToken;
        $branch = ['branch_id' => $this->branch->id];

        $this->withBearer($ownerToken);
        $view = $this->getJson('/api/v1/owner/pos/lan?branch_id=' . $this->branch->id)->assertOk()->json('data');
        $this->assertSame((string) $this->till['device']->uuid, $view['hub']['device']);
        $this->assertFalse($view['hub']['chosen']);
        $this->assertCount(2, $view['candidates']);
        $this->assertArrayNotHasKey('secret', $view);

        // Another branch's till cannot be the hub
        $this->withBearer($ownerToken);
        $this->putJson('/api/v1/owner/pos/lan', $branch + ['hub_device_id' => (string) $other['device']->uuid])->assertUnprocessable()->assertJsonValidationErrors('hub_device_id');
        $this->withBearer($ownerToken);
        $this->putJson('/api/v1/owner/pos/lan', $branch + ['hub_device_id' => (string) $second['device']->uuid, 'port' => 19000])
            ->assertOk()->assertJsonPath('data.hub.device', (string) $second['device']->uuid)->assertJsonPath('data.hub.chosen', true)->assertJsonPath('data.port', 19000);
        $this->assertSame('client', $this->lanOf($this->cashierToken)['role']);
        $this->assertSame('hub', $this->lanOf($secondToken)['role']);
        $this->assertSame(19000, $this->lanOf($secondToken)['hub']['port']);

        $before = $this->secret();
        $keyBefore = $this->lanOf($secondToken)['device_key'];
        $this->withBearer($ownerToken);
        $this->postJson('/api/v1/owner/pos/lan/rotate', $branch)->assertOk()->assertJsonPath('data.key_id', 2);
        $this->assertNotSame($before, $this->secret());
        $after = $this->lanOf($secondToken);
        $this->assertSame(2, $after['key_id']);
        $this->assertNotSame($keyBefore, $after['device_key']);

        $audit = AuditLog::where('action', 'pos.lan_key_rotated')->sole();
        $this->assertSame($this->owner->id, (int) $audit->user_id);
        $this->assertStringNotContainsString(base64_encode($this->secret()), (string) json_encode($audit->toArray()));
        $this->assertSame(1, AuditLog::where('action', 'settings.pos_lan_changed')->count());

        // A PIN session on a till never reaches the owner's endpoints (the manager there neither)
        $this->withBearer($secondToken);
        $this->postJson('/api/v1/owner/pos/lan/rotate', $branch)->assertForbidden();
        $this->withBearer($this->cashierToken);
        $this->getJson('/api/v1/owner/pos/lan?branch_id=' . $this->branch->id)->assertForbidden();
    }

    public function test_the_printing_till_is_the_default_hub(): void
    {
        $printer = $this->pairTerminal($this->owner, $this->branch);
        $printerToken = $this->pinSession($printer, $this->manager, self::MANAGER_PIN);
        $this->branch->forceFill(['pos_settings' => ['printing_device_id' => (string) $printer['device']->uuid]])->save();

        $this->assertSame('hub', $this->lanOf($printerToken)['role']);
        $this->assertSame('client', $this->lanOf($this->cashierToken)['role']);
        // A revoked hub gives way to the next till
        $printer['device']->forceFill(['revoked_at' => now()])->save();
        $this->assertSame('hub', $this->lanOf($this->cashierToken)['role']);
        $this->assertSame(1, BranchLanSettings::count());
        $this->assertTrue(Str::isUuid((string) $this->lanOf($this->cashierToken)['hub']['device']));
    }
}
