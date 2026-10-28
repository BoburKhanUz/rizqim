<?php

namespace Tests\Feature\AdminIntegrations;

use App\Domain\Integrations\ExternalRef;
use App\Domain\Integrations\ExternalRefEntity;
use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P4-31 (docs/PHASE5_PLAN.md 4.3): the integration tables, their models and factories; credentials encrypted and never
 * serialised; external refs unique both ways; the migrations go back and forth; orders.fiscalized_by is empty.
 */
class IntegrationDataModelTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = [
        '2026_11_06_300300_create_integration_connections_table.php',
        '2026_11_06_300400_create_integration_events_table.php',
        '2026_11_06_300500_create_external_refs_table.php',
        '2026_11_06_300600_add_fiscalized_by_to_orders_table.php',
    ];

    public function test_credentials_are_encrypted_at_rest_and_never_serialised(): void
    {
        $connection = IntegrationConnection::factory()->create(['credentials' => ['api_key' => 'sk_live_supersecret_9999', 'login' => 'rizqim']]);

        $raw = (string) DB::table('integration_connections')->where('id', $connection->id)->value('credentials');
        $this->assertStringNotContainsString('supersecret', $raw);
        $this->assertStringNotContainsString('supersecret', $connection->toJson());
        $this->assertArrayNotHasKey('credentials', $connection->fresh()?->toArray() ?? []);

        $fresh = IntegrationConnection::query()->findOrFail($connection->id);
        $this->assertSame(['api_key', 'login'], $fresh->credentialKeys());
        $this->assertSame('sk_live_supersecret_9999', $fresh->credential('api_key'));
        $this->assertNull($fresh->credential('missing'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fresh->uuid);
    }

    public function test_external_refs_are_unique_both_ways_on_a_connection(): void
    {
        $connection = IntegrationConnection::factory()->create();
        ExternalRef::factory()->create(['integration_connection_id' => $connection->id, 'entity_type' => ExternalRefEntity::PRODUCT, 'entity_id' => 10, 'external_id' => 'dish-1']);

        // Another connection may map the same record and the same outside id
        ExternalRef::factory()->create(['entity_type' => ExternalRefEntity::PRODUCT, 'entity_id' => 10, 'external_id' => 'dish-1']);
        // The same outside id for another kind of record is fine too
        ExternalRef::factory()->create(['integration_connection_id' => $connection->id, 'entity_type' => ExternalRefEntity::CATEGORY, 'entity_id' => 10, 'external_id' => 'dish-1']);

        foreach ([['entity_id' => 10, 'external_id' => 'dish-2'], ['entity_id' => 11, 'external_id' => 'dish-1']] as $duplicate) {
            try {
                DB::transaction(fn () => ExternalRef::factory()->create(['integration_connection_id' => $connection->id, 'entity_type' => ExternalRefEntity::PRODUCT, ...$duplicate]));
                $this->fail('A duplicate ref was stored.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('external_refs_', $e->getMessage());
            }
        }
        $this->assertSame(2, $connection->externalRefs()->count());
    }

    public function test_an_idempotency_key_is_journaled_once_per_connection_and_direction(): void
    {
        $event = IntegrationEvent::factory()->create(['idempotency_key' => 'eats-123']);
        IntegrationEvent::factory()->create(['integration_connection_id' => $event->integration_connection_id, 'idempotency_key' => 'eats-123', 'direction' => 'inbound']);
        IntegrationEvent::factory()->count(2)->create(['integration_connection_id' => $event->integration_connection_id, 'idempotency_key' => null]);

        $this->expectException(QueryException::class);
        IntegrationEvent::factory()->create(['integration_connection_id' => $event->integration_connection_id, 'idempotency_key' => 'eats-123']);
    }

    public function test_the_migrations_go_back_and_forth_and_orders_start_without_fiscalized_by(): void
    {
        IntegrationConnection::factory()->create();
        $this->assertTrue(Schema::hasColumn('orders', 'fiscalized_by'));

        $migrations = array_map(fn (string $file) => require database_path("migrations/{$file}"), self::MIGRATIONS);
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $this->assertFalse(Schema::hasTable('integration_connections'));
        $this->assertFalse(Schema::hasTable('integration_events'));
        $this->assertFalse(Schema::hasTable('external_refs'));
        $this->assertFalse(Schema::hasColumn('orders', 'fiscalized_by'));

        foreach ($migrations as $migration) {
            $migration->up();
        }
        $this->assertTrue(Schema::hasTable('external_refs'));
        $this->assertTrue(Schema::hasColumn('orders', 'fiscalized_by'));
        $this->assertSame(0, DB::table('orders')->whereNotNull('fiscalized_by')->count());
    }
}
