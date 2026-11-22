<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Go-live review: a restaurant's own Click / Payme keys (stores.payment_settings) are encrypted at rest (APP_KEY), as
 * the platform's are; every reader still gets the array, and nothing serialized or shown to the owner carries a key.
 * Migration 2026_12_01_100000 encrypts existing values in place (reversible, idempotent).
 */
class StorePaymentSettingsEncryptionTest extends TestCase
{
    // Does DDL itself (the migration's ALTER COLUMN): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    private const SETTINGS = [
        'cash' => ['enabled' => true],
        'payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox-42', 'secret_key' => 'very-secret-payme-key'],
        'click' => ['enabled' => true, 'service_id' => 'svc-7', 'merchant_id' => 'mer-7', 'secret_key' => 'very-secret-click-key'],
        'delivery_fee' => 5000,
    ];

    private function raw(Store $store): ?string
    {
        return DB::table('stores')->where('id', $store->id)->value('payment_settings');
    }

    private function columnType(): string
    {
        return (string) DB::scalar("SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'stores' AND column_name = 'payment_settings'");
    }

    public function test_payment_settings_are_stored_encrypted_and_read_back_as_the_array(): void
    {
        $store = Store::forceCreate(['name' => 'Cipher Cafe', 'status' => 'active', 'payment_account_type' => 'own_account', 'payment_settings' => self::SETTINGS]);

        $raw = $this->raw($store);
        $this->assertSame('text', $this->columnType());
        foreach (['very-secret-payme-key', 'very-secret-click-key', 'own-cashbox-42', 'merchant_id'] as $plain) {
            $this->assertStringNotContainsString($plain, (string) $raw);
        }
        $this->assertEquals(self::SETTINGS['payme'], json_decode(Crypt::decryptString($raw), true)['payme']);

        $fresh = Store::findOrFail($store->id);
        $this->assertSame('very-secret-payme-key', $fresh->payment_settings['payme']['secret_key']);
        $this->assertTrue($fresh->collectsOwnPayments('payme'));
        $this->assertTrue($fresh->collectsOwnPayments('click'));
        $this->assertTrue((bool) $fresh->accept_payme);

        // A save merges and keeps the stored secret when a form sends it masked
        $fresh->update(['payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox-43', 'secret_key' => '••••']]]);
        $merged = Store::findOrFail($store->id)->payment_settings;
        $this->assertSame('very-secret-payme-key', $merged['payme']['secret_key']);
        $this->assertSame('own-cashbox-43', $merged['payme']['merchant_id']);
        $this->assertSame(5000, $merged['delivery_fee']);

        // Serialized and shown to the owner without a key
        $this->assertStringNotContainsString('very-secret', json_encode(Store::findOrFail($store->id)->toArray()));
        $owner = User::create(['name' => 'Owner', 'phone' => '+998901117788', 'role' => UserRole::OWNER->value, 'store_id' => $store->id, 'password' => bcrypt('x')]);
        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/owner/settings')->assertOk();
        $this->assertStringNotContainsString('very-secret', $response->getContent());
        $response->assertJsonPath('data.payment_settings.payme.has_secret_key', true);
        $response->assertJsonPath('data.payment_settings.payme.merchant_id', 'own-cashbox-43');
    }

    public function test_the_migration_encrypts_plaintext_in_place_reversibly_and_idempotently(): void
    {
        $migration = require database_path('migrations/2026_12_01_100000_encrypt_store_payment_settings.php');
        $store = Store::forceCreate(['name' => 'Legacy Cafe', 'status' => 'active', 'payment_account_type' => 'own_account', 'payment_settings' => self::SETTINGS]);
        $bare = Store::forceCreate(['name' => 'Bare Cafe', 'status' => 'active']);
        DB::table('stores')->where('id', $bare->id)->update(['payment_settings' => null]);

        // Back to the plain jsonb column, twice (idempotent)
        $migration->down();
        $migration->down();
        $this->assertSame('jsonb', $this->columnType());
        $this->assertEquals(self::SETTINGS, json_decode($this->raw($store), true));
        $this->assertNull($this->raw($bare));

        // A plaintext row as it was before the change, then up() twice: encrypted once, the same settings
        $migration->up();
        $once = $this->raw($store);
        $migration->up();
        $this->assertSame('text', $this->columnType());
        $this->assertSame($once, $this->raw($store));
        $this->assertStringNotContainsString('very-secret-payme-key', $once);
        $this->assertEquals(self::SETTINGS, json_decode(Crypt::decryptString($once), true));
        $this->assertEquals(self::SETTINGS, Store::findOrFail($store->id)->payment_settings);
        $this->assertNull($this->raw($bare));
        $this->assertNull(Store::findOrFail($bare->id)->payment_settings);
    }
}
