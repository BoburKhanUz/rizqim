<?php

namespace Tests\Feature\AdminIntegrations;

use App\Domain\Integrations\ExternalRef;
use App\Domain\Integrations\ExternalRefEntity;
use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationConnectionStatus;
use App\Domain\Integrations\IntegrationDirection;
use App\Domain\Integrations\IntegrationEvent;
use App\Domain\Integrations\IntegrationProvider;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * P4-31 Integratsiyalar → Ulanishlar: list, filters, counts, the card (no credential values, redacted settings) and
 * pausing / resuming with integrations.manage, a step-up and a reason.
 */
class AdminIntegrationConnectionsTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Lazzat Oshxona', 'slug' => 'lazzat', 'commission_rate' => 10, 'status' => 'active']);
    }

    public function test_the_list_is_empty_and_calm_before_phase_5(): void
    {
        $data = $this->adminJson('GET', '/integrations/connections', $this->signIn($this->makeMember(PlatformRole::OPERATOR)))->assertOk()->json('data');

        $this->assertSame([[], 0, 0], [$data['data'], $data['total'], $data['stuck_orders']]);
        $this->assertSame(['total' => 0, 'configuring' => 0, 'active' => 0, 'paused' => 0, 'disconnected' => 0, 'attention' => 0], $data['counts']);
    }

    public function test_the_list_shows_health_sorts_the_weakest_first_and_filters(): void
    {
        $good = IntegrationConnection::factory()->create(['store_id' => $this->store->id, 'health_score' => 96]);
        $weak = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::IIKO, 'health_score' => 62, 'last_error' => 'Mijoz +998901234567 topilmadi', 'last_error_at' => now(), 'errors_today' => 4]);
        $down = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::YANDEX_EATS, 'direction' => IntegrationDirection::AGGREGATOR_TO_RIZQIM,
            'status' => IntegrationConnectionStatus::DISCONNECTED, 'health_score' => 10, 'mode' => 'production',
        ]);
        $new = IntegrationConnection::factory()->create(['status' => IntegrationConnectionStatus::CONFIGURING, 'health_score' => null]);

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $response = $this->adminJson('GET', '/integrations/connections', $token)->assertOk();
        $rows = $response->json('data.data');

        $this->assertSame([$down->uuid, $weak->uuid, $good->uuid, $new->uuid], array_column($rows, 'uuid'));
        $this->assertSame(['disconnected', 'attention', 'good', 'unknown'], array_column(array_column($rows, 'health'), 'verdict'));
        $this->assertSame(['total' => 4, 'configuring' => 1, 'active' => 2, 'paused' => 0, 'disconnected' => 1, 'attention' => 2], $response->json('data.counts'));
        $this->assertSame(['Yandex Eats', 'Agregator → Rizqim POS', 'Ishchi', 'Uzilgan'], [$rows[0]['provider_label'], $rows[0]['direction_label'], $rows[0]['mode_label'], $rows[0]['status_label']]);
        // Phone numbers in error texts are masked; credentials never in the list
        $this->assertSame('Mijoz +99890*****67 topilmadi', $rows[1]['health']['last_error']);
        $this->assertSame(4, $rows[1]['health']['errors_today']);
        $this->assertArrayNotHasKey('credentials', $rows[0]);
        $this->assertStringNotContainsString('sk_test_', $response->getContent());

        $uuids = fn (string $query) => array_column($this->adminJson('GET', '/integrations/connections' . $query, $token)->assertOk()->json('data.data'), 'uuid');
        $this->assertSame([$weak->uuid], $uuids('?provider=iiko'));
        $this->assertSame([$down->uuid], $uuids('?direction=aggregator_to_rizqim'));
        $this->assertSame([$new->uuid], $uuids('?status=configuring'));
        $this->assertSame([$weak->uuid], $uuids('?health=attention'));
        $this->assertSame([$new->uuid], $uuids('?health=unknown'));
        $this->assertSame([$good->uuid], $uuids("?store_id={$this->store->id}"));
        $this->assertSame([$good->uuid], $uuids('?q=lazzat'));
        $this->assertSame([$down->uuid], $uuids('?mode=production'));
        $this->adminJson('GET', '/integrations/connections?provider=nope', $token)->assertStatus(422);
    }

    public function test_reading_needs_integrations_view(): void
    {
        foreach ([PlatformRole::SUPPORT, PlatformRole::FINANCE, PlatformRole::SALES] as $role) {
            $this->adminJson('GET', '/integrations/connections', $this->signIn($this->makeMember($role)))->assertForbidden()->assertJsonPath('code', 'forbidden');
        }
        foreach ([PlatformRole::OPERATOR, PlatformRole::VIEWER, PlatformRole::SUPERADMIN] as $role) {
            $this->adminJson('GET', '/integrations/connections', $this->signIn($this->makeMember($role)))->assertOk();
        }
    }

    public function test_the_card_shows_credential_names_only_and_redacted_settings(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'store_id' => $this->store->id,
            'credentials' => ['api_login' => 'rizqim-api-login-4321', 'pin' => '1234'],
            'settings' => ['terminal_id' => 'T-9', 'webhook_secret' => 'whsec_abcdef', 'manager_phone' => '+998935550011', 'price_source' => 'pos'],
        ]);
        IntegrationEvent::factory()->count(12)->create(['integration_connection_id' => $connection->id]);
        ExternalRef::factory()->count(3)->create(['integration_connection_id' => $connection->id]);
        ExternalRef::factory()->create(['integration_connection_id' => $connection->id, 'entity_type' => ExternalRefEntity::CATEGORY]);

        $response = $this->adminJson('GET', "/integrations/connections/{$connection->uuid}", $this->signIn($this->makeMember(PlatformRole::OPERATOR)))->assertOk();
        $card = $response->json('data');

        // Review W3 S#6: an operator (integrations.view only) sees no characters of a credential
        $this->assertSame([
            ['key' => 'api_login', 'configured' => true, 'last4' => null],
            ['key' => 'pin', 'configured' => true, 'last4' => null],
        ], $card['credentials']);
        $this->assertSame(['terminal_id' => 'T-9', 'webhook_secret' => '[redacted]', 'manager_phone' => '+99893*****11', 'price_source' => 'pos'], $card['settings']);
        $this->assertCount(10, $card['recent_events']);
        $this->assertSame(['total' => 4, 'by_type' => ['category' => 1, 'product' => 3]], ['total' => $card['menu_map']['total'], 'by_type' => collect($card['menu_map']['by_type'])->sortKeys()->all()]);
        $this->assertSame(0, $card['stuck_orders']);
        $this->assertStringNotContainsString('rizqim-api-login', $response->getContent());
        $this->assertStringNotContainsString('whsec_abcdef', $response->getContent());

        // ... a member with integrations.manage sees the last 4 of a credential of 16+ characters, never of a short one
        $this->adminJson('GET', "/integrations/connections/{$connection->uuid}", $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk()
            ->assertJsonPath('data.credentials', [
                ['key' => 'api_login', 'configured' => true, 'last4' => '4321'],
                ['key' => 'pin', 'configured' => true, 'last4' => null],
            ]);

        $this->adminJson('GET', '/integrations/connections/' . \Illuminate\Support\Str::uuid(), $this->signIn($this->makeMember(PlatformRole::OPERATOR)))->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_pausing_and_resuming_need_manage_a_step_up_and_a_reason_and_are_audited(): void
    {
        $connection = IntegrationConnection::factory()->create(['store_id' => $this->store->id]);
        $uri = "/integrations/connections/{$connection->uuid}";

        // Operator and viewer read, they cannot act
        foreach ([PlatformRole::OPERATOR, PlatformRole::VIEWER] as $role) {
            $this->adminJson('POST', "{$uri}/pause", $this->confirmedSession($this->makeMember($role)), ['reason' => "Kassa o'chiq"])->assertForbidden()->assertJsonPath('code', 'forbidden');
        }

        $member = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($member);
        $this->adminJson('POST', "{$uri}/pause", $token, ['reason' => "Kassa o'chiq"])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($member, $token);
        $this->adminJson('POST', "{$uri}/pause", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame(IntegrationConnectionStatus::ACTIVE, $connection->refresh()->status);

        $this->adminJson('POST', "{$uri}/pause", $token, ['reason' => "Kassa o'chiq"])->assertOk()
            ->assertJsonPath('data.status', 'paused')->assertJsonPath('data.paused.reason', "Kassa o'chiq")->assertJsonPath('data.paused.by.id', $member->id);
        $connection->refresh();
        $this->assertSame([IntegrationConnectionStatus::PAUSED, IntegrationConnectionStatus::ACTIVE, $member->id], [$connection->status, $connection->status_before_pause, $connection->paused_by]);

        $audit = AuditLog::where('action', 'admin.integrations.connection_paused')->sole();
        $this->assertSame([['status' => 'active'], ['status' => 'paused'], $this->store->id, "Kassa o'chiq", 'alipos'], [$audit->before, $audit->after, $audit->store_id, $audit->payload_json['reason'], $audit->payload_json['provider']]);

        // Pausing again changes nothing
        $this->adminJson('POST', "{$uri}/pause", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.integrations.connection_paused')->count());

        $this->adminJson('POST', "{$uri}/resume", $token, ['reason' => 'Kassa yondi'])->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.paused', null);
        $connection->refresh();
        $this->assertSame([null, null, null], [$connection->status_before_pause, $connection->paused_at, $connection->pause_reason]);
        $this->assertSame([['status' => 'paused'], ['status' => 'active']], [AuditLog::where('action', 'admin.integrations.connection_resumed')->sole()->before, AuditLog::where('action', 'admin.integrations.connection_resumed')->sole()->after]);
        $this->adminJson('POST', "{$uri}/resume", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.integrations.connection_resumed')->count());

        // A connection still being set up goes back to that
        $configuring = IntegrationConnection::factory()->create(['status' => IntegrationConnectionStatus::CONFIGURING]);
        $this->adminJson('POST', "/integrations/connections/{$configuring->uuid}/pause", $token, ['reason' => 'Kutamiz'])->assertOk();
        $this->adminJson('POST', "/integrations/connections/{$configuring->uuid}/resume", $token, ['reason' => 'Davom'])->assertOk()->assertJsonPath('data.status', 'configuring');
    }
}
