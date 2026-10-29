<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\PaymentProvider;
use App\Services\Payment\PaymentProviderSwitch;
use Database\Seeders\PaymentProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P4-31 Tizim → To'lov tizimlari: the platform merchant accounts; credentials write-only (configured + last 4, the
 * source of the value the payment code would use), one key at a time, never in the audit.
 */
class AdminSystemPaymentProvidersTest extends TestCase
{
    use RefreshDatabase;
    use AdminSystemFixtures;

    private const SECRET = 'payme-secret-key-ABCD';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payme.secret_key' => null, 'services.click.secret_key' => 'click-env-secret-7777', 'services.click.merchant_user_id' => null]);
        $this->seed(PaymentProviderSeeder::class);
        PaymentProvider::where('slug', 'payme')->sole()->forceFill(['credentials' => ['merchant_id' => '65eb2b988f01b97b0a7c4912', 'secret_key' => self::SECRET, 'test_mode' => 'true']])->save();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function keys(array $provider): array
    {
        return array_column($provider['credentials'], null, 'key');
    }

    public function test_the_list_masks_credentials_and_says_where_each_comes_from(): void
    {
        $response = $this->adminJson('GET', '/system/payment-providers', $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk();
        $providers = array_column($response->json('data'), null, 'slug');

        $this->assertSame(['payme', 'click', 'uzum', 'paynet'], array_keys($providers));
        $this->assertSame([true, true, false], [$providers['payme']['wired'], $providers['click']['wired'], $providers['uzum']['wired']]);

        $payme = $this->keys($providers['payme']);
        // test_mode is not a credential (it is the `mode`)
        $this->assertSame(['merchant_id', 'secret_key'], array_keys($payme));
        $this->assertSame([true, 'ABCD', 'settings'], [$payme['secret_key']['configured'], $payme['secret_key']['last4'], $payme['secret_key']['source']]);
        $this->assertSame('4912', $payme['merchant_id']['last4']);

        $click = $this->keys($providers['click']);
        // Nothing stored: the payment code reads config/services.php
        $this->assertSame([true, '7777', 'env'], [$click['secret_key']['configured'], $click['secret_key']['last4'], $click['secret_key']['source']]);
        $this->assertSame([false, null, null], [$click['merchant_user_id']['configured'], $click['merchant_user_id']['last4'], $click['merchant_user_id']['source']]);
        $this->assertSame(['Merchant API foydalanuvchisi', true], [$click['merchant_user_id']['label'], $click['merchant_user_id']['known']]);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::SECRET, $body);
        $this->assertStringNotContainsString('click-env-secret', $body);
        $this->assertStringNotContainsString('65eb2b988f01b97b0a7c', $body);

        $this->adminJson('GET', '/system/payment-providers', $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertForbidden();
        $this->adminJson('GET', '/system/payment-providers', $this->signIn($this->makeMember(PlatformRole::VIEWER)))->assertForbidden();
    }

    /**
     * Review C#1: the old seeder wrote Payme's credentials with test_mode 'true', and the old panel's mode and on/off
     * switches did nothing. Now both route live payments, so the migration starts every row in production and on (the
     * team reviews Tizim → To'lov tizimlari after migrating, docs/DEPLOY.md) and only logs where test_mode was set.
     */
    public function test_the_migration_starts_every_provider_in_production_and_on(): void
    {
        PaymentProvider::where('slug', 'uzum')->sole()->forceFill(['is_active' => false])->save();
        $migration = require database_path('migrations/2026_11_06_300200_add_mode_to_payment_providers_table.php');
        $migration->down();

        Log::spy();
        $migration->up();

        $this->assertSame(
            ['payme' => ['production', true], 'click' => ['production', true], 'uzum' => ['production', true], 'paynet' => ['production', true]],
            DB::table('payment_providers')->orderBy('id')->get(['slug', 'mode', 'is_active'])->mapWithKeys(fn ($row) => [$row->slug => [$row->mode, (bool) $row->is_active]])->all(),
        );
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => str_contains($message, 'test_mode') && ($context['providers'] ?? null) === ['payme'])->once();
        // The credentials are untouched
        $this->assertSame(self::SECRET, PaymentProvider::where('slug', 'payme')->sole()->credentials['secret_key']);
    }

    public function test_after_the_migration_a_live_payme_checkout_opens_production(): void
    {
        $migration = require database_path('migrations/2026_11_06_300200_add_mode_to_payment_providers_table.php');
        $migration->down();
        $migration->up();
        PaymentProviderSwitch::forget();

        $this->assertSame('production', app(PaymentProviderSwitch::class)->mode('payme'));
        $this->assertSame('https://checkout.paycom.uz/', app(PaymentProviderSwitch::class)->paymeCheckoutBase());
    }

    public function test_the_seeder_starts_every_provider_in_production(): void
    {
        $this->assertSame(['production'], PaymentProvider::query()->distinct()->pluck('mode')->all());
        $this->assertSame([true], PaymentProvider::query()->distinct()->pluck('is_active')->map(fn ($active) => (bool) $active)->all());
    }

    /**
     * Review S#7: on production the sandbox is refused unless PAYMENTS_ALLOW_TEST_MODE=true; back to production and
     * the on/off switch always work.
     */
    public function test_production_refuses_test_mode_without_the_override(): void
    {
        config(['app.env' => 'production']);

        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['mode' => 'test', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'test_mode_not_allowed')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'PAYMENTS_ALLOW_TEST_MODE'));
        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['is_active' => false, 'mode' => 'test', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'test_mode_not_allowed');
        $this->assertSame(['production', true], [PaymentProvider::where('slug', 'payme')->value('mode'), (bool) PaymentProvider::where('slug', 'payme')->value('is_active')]);
        $this->assertSame(0, AuditLog::where('action', 'admin.system.payment_provider_updated')->count());

        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['is_active' => false, 'mode' => 'production', 'reason' => "Vaqtincha o'chiramiz"])
            ->assertOk()->assertJsonPath('data.is_active', false);

        config(['services.payments.allow_test_mode' => true]);
        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['mode' => 'test', 'reason' => 'Sinov'])->assertOk()->assertJsonPath('data.mode', 'test');

        // A row already in test (the override was on then) may still be switched back, and saved as it is
        config(['services.payments.allow_test_mode' => false]);
        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['mode' => 'test', 'is_active' => true, 'reason' => 'Yoqamiz'])->assertOk();
        $this->adminJson('PATCH', '/system/payment-providers/payme', $this->superToken(), ['mode' => 'production', 'reason' => 'Ishga tushirdik'])->assertOk()->assertJsonPath('data.mode', 'production');

        // Staging (and local) may test freely
        config(['app.env' => 'staging']);
        $this->adminJson('PATCH', '/system/payment-providers/click', $this->superToken(), ['mode' => 'test', 'reason' => 'Sinov'])->assertOk()->assertJsonPath('data.mode', 'test');
    }

    public function test_switching_a_provider_is_guarded_and_audited(): void
    {
        $this->assertGuardedWrite('PATCH', '/system/payment-providers/uzum', ['is_active' => false, 'reason' => 'Hali ulanmagan']);

        $this->adminJson('PATCH', '/system/payment-providers/uzum', $this->superToken(), ['is_active' => false, 'mode' => 'test', 'reason' => 'Hali ulanmagan'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.mode', 'test');
        $provider = PaymentProvider::where('slug', 'uzum')->sole();
        $this->assertSame([false, 'test'], [$provider->is_active, $provider->getAttribute('mode')]);

        $audit = AuditLog::where('action', 'admin.system.payment_provider_updated')->sole();
        $this->assertSame([['is_active' => true, 'mode' => 'production'], ['is_active' => false, 'mode' => 'test'], 'uzum', 'Hali ulanmagan'], [$audit->before, $audit->after, $audit->payload_json['provider'], $audit->payload_json['reason']]);

        $this->adminJson('PATCH', '/system/payment-providers/uzum', $this->superToken(), ['is_active' => false, 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.payment_provider_updated')->count());
        $this->adminJson('PATCH', '/system/payment-providers/uzum', $this->superToken(), ['mode' => 'live', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('mode');
        $this->adminJson('PATCH', '/system/payment-providers/nope', $this->superToken(), ['is_active' => true, 'reason' => 'Sinov'])->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_a_credential_is_replaced_and_cleared_without_its_value_reaching_the_audit(): void
    {
        $this->assertGuardedWrite('PUT', '/system/payment-providers/click/credentials/secret_key', ['value' => 'click-stored-secret-5555', 'reason' => 'Yangi kalit']);

        $response = $this->adminJson('PUT', '/system/payment-providers/click/credentials/secret_key', $this->superToken(), ['value' => '  click-stored-secret-5555 ', 'reason' => 'Yangi kalit'])
            ->assertOk()->assertJsonPath('message', 'Kalit almashtirildi.');
        $this->assertSame(['5555', 'settings'], [$this->keys($response->json('data'))['secret_key']['last4'], $this->keys($response->json('data'))['secret_key']['source']]);
        // The payment code now reads the stored value; other stored keys stay
        $this->assertSame('click-stored-secret-5555', PaymentProvider::platformCredential('click', 'secret_key'));
        $this->assertSame(self::SECRET, PaymentProvider::platformCredential('payme', 'secret_key'));
        // Encrypted at rest
        $this->assertStringNotContainsString('click-stored-secret', (string) DB::table('payment_providers')->where('slug', 'click')->value('credentials'));

        $audit = AuditLog::where('action', 'admin.system.payment_credential_replaced')->sole();
        $this->assertSame(['click', 'secret_key', false, false], [$audit->payload_json['provider'], $audit->payload_json['field'], $audit->payload_json['was_stored'], $audit->payload_json['cleared']]);
        $this->assertStringNotContainsString('click-stored-secret', (string) json_encode(AuditLog::all()->toArray()));

        $this->adminJson('DELETE', '/system/payment-providers/click/credentials/secret_key', $this->superToken(), ['reason' => 'Serverdagisi'])->assertOk();
        $this->assertSame('click-env-secret-7777', PaymentProvider::platformCredential('click', 'secret_key'));
        $this->adminJson('DELETE', '/system/payment-providers/click/credentials/secret_key', $this->superToken(), ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.payment_credential_cleared')->count());

        $this->adminJson('PUT', '/system/payment-providers/click/credentials/api_token', $this->superToken(), ['value' => 'x', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'unknown_key');
        $this->adminJson('PUT', '/system/payment-providers/payme/credentials/test_mode', $this->superToken(), ['value' => 'false', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'unknown_key');
        $this->adminJson('PUT', '/system/payment-providers/click/credentials/secret_key', $this->superToken(), ['value' => '', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('value');
    }
}
