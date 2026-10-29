<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-31 Tizim → Sozlamalar: SMS settings from the `settings` table, Telegram and the app from the server's
 * configuration; secrets are never answered in full and are replaced on their own, without their value in the audit.
 */
class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;
    use AdminSystemFixtures;

    private const PASSWORD = 'eskiz-very-secret-9876';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'settings.eskiz_email' => null,
            'settings.eskiz_password' => null,
            'settings.sms_driver' => null,
            'settings.eskiz_from' => null,
            'services.telegram.bot_token' => '123456:AAEexampleTokenWXYZ',
            'services.telegram.bot_username' => 'rizqim_bot',
            'services.telegram.webhook_secret' => null,
            'services.telegram.superadmin_chat_id' => '777',
        ]);
        Setting::create(['key' => 'sms_driver', 'value' => 'log']);
        Setting::create(['key' => 'eskiz_email', 'value' => 'sms@rizqim.uz']);
        Setting::create(['key' => 'eskiz_password', 'value' => self::PASSWORD]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function items(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $items[$item['key']] = $item + ['group' => $group['key']];
            }
        }

        return $items;
    }

    public function test_the_groups_show_values_and_only_the_last_four_of_a_secret(): void
    {
        $response = $this->adminJson('GET', '/system/settings', $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk();

        $this->assertSame(['sms', 'telegram', 'general'], array_column($response->json('data.groups'), 'key'));
        $items = $this->items($response->json('data.groups'));

        $this->assertSame(['log', 'settings', true], [$items['sms_driver']['value'], $items['sms_driver']['source'], $items['sms_driver']['editable']]);
        $this->assertSame(['log', 'eskiz'], array_column($items['sms_driver']['options'], 'value'));
        $this->assertSame('sms@rizqim.uz', $items['eskiz_email']['value']);
        // Not stored: the code's default
        $this->assertSame(['4546', 'default'], [$items['eskiz_from']['value'], $items['eskiz_from']['source']]);
        $this->assertSame([null, true, '9876', true], [$items['eskiz_password']['value'], $items['eskiz_password']['configured'], $items['eskiz_password']['last4'], $items['eskiz_password']['secret']]);

        // From .env: shown, not editable; secrets masked, a short one without characters
        $this->assertSame([false, null, true, 'WXYZ', 'env'], [$items['telegram_bot_token']['editable'], $items['telegram_bot_token']['value'], $items['telegram_bot_token']['configured'], $items['telegram_bot_token']['last4'], $items['telegram_bot_token']['source']]);
        $this->assertSame([true, null], [$items['telegram_superadmin_chat_id']['configured'], $items['telegram_superadmin_chat_id']['last4']]);
        $this->assertSame([false, null], [$items['telegram_webhook_secret']['configured'], $items['telegram_webhook_secret']['source']]);
        $this->assertSame('rizqim_bot', $items['telegram_bot_username']['value']);
        $this->assertSame('general', $items['app_env']['group']);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::PASSWORD, $body);
        $this->assertStringNotContainsString('AAEexampleToken', $body);
    }

    public function test_only_system_manage_reads_the_settings(): void
    {
        foreach ([PlatformRole::VIEWER, PlatformRole::OPERATOR, PlatformRole::SUPPORT, PlatformRole::FINANCE, PlatformRole::SALES] as $role) {
            $this->adminJson('GET', '/system/settings', $this->signIn($this->makeMember($role)))->assertForbidden()->assertJsonPath('code', 'forbidden');
        }
        $this->adminJson('GET', '/system/settings')->assertUnauthorized();
    }

    public function test_changing_settings_needs_a_step_up_and_a_reason_and_is_audited(): void
    {
        $this->assertGuardedWrite('PATCH', '/system/settings', ['values' => ['eskiz_from' => 'RIZQIM'], 'reason' => "Jo'natuvchi nomi tasdiqlandi"]);

        $this->assertSame('4546', setting('eskiz_from', '4546'));
        $response = $this->adminJson('PATCH', '/system/settings', $this->superToken(), ['values' => ['eskiz_from' => 'RIZQIM', 'eskiz_email' => 'sms@rizqim.uz'], 'reason' => "Jo'natuvchi nomi tasdiqlandi"])
            ->assertOk()->assertJsonPath('message', 'Sozlamalar saqlandi.');
        $this->assertSame('RIZQIM', $this->items($response->json('data.groups'))['eskiz_from']['value']);
        // The setting() cache is forgotten
        $this->assertSame('RIZQIM', setting('eskiz_from', '4546'));

        $audit = AuditLog::where('action', 'admin.system.settings_updated')->sole();
        $this->assertSame([['eskiz_from' => null], ['eskiz_from' => 'RIZQIM']], [$audit->before, $audit->after]);
        $this->assertSame(["Jo'natuvchi nomi tasdiqlandi", ['eskiz_from']], [$audit->payload_json['reason'], $audit->payload_json['settings']]);
        $this->assertSame($this->superadmin?->id, $audit->user_id);

        // The same values again change nothing and write nothing
        $this->adminJson('PATCH', '/system/settings', $this->superToken(), ['values' => ['eskiz_from' => 'RIZQIM'], 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.settings_updated')->count());

        // Empty removes the stored value: the default applies again
        $this->adminJson('PATCH', '/system/settings', $this->superToken(), ['values' => ['eskiz_from' => ''], 'reason' => 'Standart'])->assertOk();
        $this->assertNull(Setting::where('key', 'eskiz_from')->value('value'));
        $this->assertSame('4546', setting('eskiz_from', '4546'));
    }

    public function test_the_plain_form_refuses_secrets_unknown_keys_and_bad_values(): void
    {
        $token = $this->superToken();

        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['eskiz_password' => 'new-one'], 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'secret_separate');
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['app_url' => 'https://x.uz'], 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'unknown_setting');
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['sms_driver' => 'twilio'], 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('values.sms_driver');
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['eskiz_email' => 'not-an-email'], 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('values.eskiz_email');
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => [], 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('values');
        $this->assertSame(self::PASSWORD, Setting::where('key', 'eskiz_password')->value('value'));
        $this->assertSame(0, AuditLog::where('action', 'admin.system.settings_updated')->count());
    }

    public function test_eskiz_needs_its_email_and_password(): void
    {
        $token = $this->superToken();
        Setting::where('key', 'eskiz_password')->update(['value' => null]);

        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['sms_driver' => 'eskiz'], 'reason' => 'Haqiqiy SMS'])->assertStatus(422)->assertJsonPath('code', 'eskiz_not_configured');
        $this->assertSame('log', Setting::where('key', 'sms_driver')->value('value'));

        $this->adminJson('PUT', '/system/settings/secrets/eskiz_password', $token, ['value' => self::PASSWORD, 'reason' => 'Parol berildi'])->assertOk();
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['sms_driver' => 'eskiz'], 'reason' => 'Haqiqiy SMS'])->assertOk();
        $this->assertSame('eskiz', setting('sms_driver', 'log'));

        // While Eskiz sends, its email cannot go empty and its password cannot be cleared
        $this->adminJson('PATCH', '/system/settings', $token, ['values' => ['eskiz_email' => ''], 'reason' => "Email o'chadi"])->assertStatus(422)->assertJsonPath('code', 'eskiz_not_configured');
        $this->adminJson('DELETE', '/system/settings/secrets/eskiz_password', $token, ['reason' => "Parol o'chadi"])->assertStatus(422)->assertJsonPath('code', 'in_use');
    }

    public function test_a_secret_is_replaced_on_its_own_and_its_value_never_reaches_the_audit(): void
    {
        $this->assertGuardedWrite('PUT', '/system/settings/secrets/eskiz_password', ['value' => 'brand-new-password-1234', 'reason' => 'Parol almashdi']);

        $response = $this->adminJson('PUT', '/system/settings/secrets/eskiz_password', $this->superToken(), ['value' => 'brand-new-password-1234', 'reason' => 'Parol almashdi'])
            ->assertOk()->assertJsonPath('message', 'Maxfiy kalit almashtirildi.');
        $this->assertSame('1234', $this->items($response->json('data.groups'))['eskiz_password']['last4']);
        $this->assertStringNotContainsString('brand-new-password', $response->getContent());
        $this->assertSame('brand-new-password-1234', setting('eskiz_password'));

        $audit = AuditLog::where('action', 'admin.system.setting_secret_replaced')->sole();
        $this->assertSame(['eskiz_password', true, false], [$audit->payload_json['setting'], $audit->payload_json['was_configured'], $audit->payload_json['cleared']]);
        $this->assertSame(Setting::where('key', 'eskiz_password')->value('id'), $audit->model_id);
        $everything = json_encode(AuditLog::all()->toArray());
        $this->assertStringNotContainsString('brand-new-password', (string) $everything);
        $this->assertStringNotContainsString(self::PASSWORD, (string) $everything);

        // Clearing: the .env value applies again
        config(['settings.eskiz_password' => 'from-env-password-0000']);
        $this->adminJson('DELETE', '/system/settings/secrets/eskiz_password', $this->superToken(), ['reason' => 'Serverdagisi ishlatilsin'])->assertOk();
        $this->assertSame('from-env-password-0000', setting('eskiz_password'));
        $this->assertSame(1, AuditLog::where('action', 'admin.system.setting_secret_cleared')->count());
        // Nothing stored to clear: no second entry
        $this->adminJson('DELETE', '/system/settings/secrets/eskiz_password', $this->superToken(), ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.setting_secret_cleared')->count());

        // Only secrets go through here
        $this->adminJson('PUT', '/system/settings/secrets/eskiz_email', $this->superToken(), ['value' => 'x@y.uz', 'reason' => 'Sinov'])->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->adminJson('PUT', '/system/settings/secrets/eskiz_password', $this->superToken(), ['value' => '', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('value');
    }
}
