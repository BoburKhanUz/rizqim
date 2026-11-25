<?php

namespace Tests\Feature\PaymentStore;

use App\Models\PaymentProvider;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Owner decision (payment decisions, decision 1): a store may not save the platform's own provider credentials as its
 * own. Its customers' money would be paid into RIZQIM's merchant account and the store's POS QR could never be routed
 * (docs/OPERATIONS.md, 29-bo'lim). Every own-account credential a store can hold (Store::OWN_MERCHANT_CREDENTIALS and
 * Click's merchant_user_id) equal to the platform's same credential — Tizim → To'lov tizimlari, else .env — is refused
 * at save time, whichever path saves it (the Store model), 422 `platform_credential` on the field; neither value is
 * ever echoed or logged. Stores already holding one are left as they are and reported by
 * `payments:own-credentials-check` (count and store ids only).
 */
class PlatformCredentialRefusalTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    /** @var array<string, array<string, string>> the platform's credentials, made at runtime */
    private array $platform = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();

        $this->platform = [
            'payme' => ['merchant_id' => 'pm' . Str::random(20), 'secret_key' => 'pk' . Str::random(30)],
            'click' => ['service_id' => (string) random_int(10000, 99999), 'merchant_id' => (string) random_int(10000, 99999), 'secret_key' => 'ck' . Str::random(30), 'merchant_user_id' => (string) random_int(10000, 99999)],
            'paylov' => [
                'merchant_id' => (string) Str::uuid(),
                'consumer_key' => 'ck' . Str::random(24),
                'consumer_secret' => Str::random(44),
                'username' => 'user' . Str::random(6),
                'password' => Str::random(20),
                'callback_login' => 'cb' . Str::random(8),
                'callback_password' => Str::random(28),
            ],
        ];
        foreach ($this->platform as $provider => $keys) {
            foreach ($keys as $key => $value) {
                config(["services.{$provider}.{$key}" => $value]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $paymentSettings
     */
    private function save(array $paymentSettings): \Illuminate\Testing\TestResponse
    {
        // Each request reads the store afresh, as a real one does (never the instance an earlier request left)
        $this->owner->unsetRelation('store');

        return $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', ['is_open' => true, 'payment_settings' => $paymentSettings]);
    }

    /**
     * @return array<string, string>
     */
    private static function ownPaylov(): array
    {
        return [
            'merchant_id' => (string) Str::uuid(),
            'consumer_key' => 'ck' . Str::random(24),
            'consumer_secret' => Str::random(44),
            'username' => 'user' . Str::random(6),
            'password' => Str::random(20),
            'callback_login' => 'cb' . Str::random(8),
            'callback_password' => Str::random(28),
        ];
    }

    private function assertNoValueLeaked(string $content): void
    {
        foreach ($this->platform as $keys) {
            foreach ($keys as $value) {
                $this->assertStringNotContainsString($value, $content);
            }
        }
    }

    public function test_the_owner_cannot_save_the_platforms_payme_key_or_merchant_id(): void
    {
        $before = $this->store->fresh()->payment_settings;
        Log::spy();

        $response = $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->platform['payme']['secret_key']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_settings.payme.secret_key')
            ->assertJsonPath('code', 'platform_credential')
            ->assertJsonPath('data.reason', 'platform_credential')
            ->assertJsonPath('data.fields', ['payment_settings.payme.secret_key']);
        $this->assertStringContainsString('RIZQIM', $response->json('errors')['payment_settings.payme.secret_key'][0]);
        $this->assertNoValueLeaked($response->getContent());

        // A padded copy is the same key
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => '  ' . $this->platform['payme']['merchant_id'] . ' ', 'secret_key' => 'own-' . Str::random(30)]])
            ->assertStatus(422)->assertJsonValidationErrors('payment_settings.payme.merchant_id');

        // Nothing was saved, and neither value reached a log
        $this->assertEquals($before, $this->store->fresh()->payment_settings);
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            Log::shouldNotHaveReceived($level, fn (...$args) => str_contains((string) json_encode($args), $this->platform['payme']['secret_key']) || str_contains((string) json_encode($args), $this->platform['payme']['merchant_id']));
        }

        // The store's own credentials save as before
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => 'own-' . Str::random(30)]])->assertOk();
    }

    public function test_every_click_credential_equal_to_the_platforms_is_refused(): void
    {
        foreach (['service_id', 'merchant_id', 'secret_key'] as $field) {
            $click = ['enabled' => true, 'service_id' => (string) random_int(100000, 999999), 'merchant_id' => (string) random_int(100000, 999999), 'secret_key' => 'own-' . Str::random(30)];
            $click[$field] = $this->platform['click'][$field];
            $response = $this->save(['click' => $click])->assertStatus(422)
                ->assertJsonValidationErrors("payment_settings.click.{$field}")
                ->assertJsonPath('code', 'platform_credential');
            $this->assertNoValueLeaked($response->getContent());
        }
        $this->assertArrayNotHasKey('service_id', $this->store->fresh()->payment_settings['click'] ?? []);
    }

    /** Review: Paylov's two logins are low-entropy and never judged alone (a guess oracle otherwise) */
    private const PAYLOV_JUDGED = ['merchant_id', 'consumer_key', 'consumer_secret', 'password', 'callback_password'];

    public function test_every_paylov_credential_equal_to_the_platforms_is_refused(): void
    {
        foreach (self::PAYLOV_JUDGED as $field) {
            $paylov = ['enabled' => true] + self::ownPaylov();
            $paylov[$field] = $this->platform['paylov'][$field];
            $response = $this->save(['paylov' => $paylov])->assertStatus(422)
                ->assertJsonValidationErrors("payment_settings.paylov.{$field}")
                ->assertJsonPath('data.fields', ["payment_settings.paylov.{$field}"]);
            $this->assertNoValueLeaked($response->getContent());
        }
        $this->assertArrayNotHasKey('paylov', $this->store->fresh()->payment_settings ?? []);

        // Every field at once: each judged one is named, never a login (past the per-store refusal limit, cleared here)
        \Illuminate\Support\Facades\RateLimiter::clear('platform-credential-refusals:' . $this->store->id);
        $this->save(['paylov' => ['enabled' => true] + $this->platform['paylov']])->assertStatus(422)
            ->assertJsonValidationErrors(array_map(fn (string $field) => "payment_settings.paylov.{$field}", self::PAYLOV_JUDGED))
            ->assertJsonMissingValidationErrors(['payment_settings.paylov.username', 'payment_settings.paylov.callback_login']);

        $this->save(['paylov' => ['enabled' => true] + self::ownPaylov()])->assertOk();
    }

    public function test_the_platform_credential_set_in_the_control_centre_counts_over_the_env(): void
    {
        $stored = 'stored-' . Str::random(30);
        PaymentProvider::create(['name' => 'Payme', 'slug' => 'payme', 'is_active' => true, 'credentials' => ['secret_key' => $stored]]);

        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $stored]])
            ->assertStatus(422)->assertJsonValidationErrors('payment_settings.payme.secret_key');
        // The .env value is not the platform's key any more: no reason to refuse it
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->platform['payme']['secret_key']]])->assertOk();
    }

    public function test_no_write_path_can_skip_the_rule(): void
    {
        // The model refuses it, whoever saves: a seeder, an import, an admin tool, a console command
        $settings = ['payme' => ['enabled' => true, 'merchant_id' => 'own-1', 'secret_key' => $this->platform['payme']['secret_key']]];
        $refused = fn (\Closure $write) => $this->assertThrows($write, ValidationException::class);

        $refused(fn () => Store::forceCreate(['name' => 'Seeded', 'slug' => 'seeded-' . Str::random(5), 'status' => 'active', 'payment_account_type' => 'own_account', 'payment_settings' => $settings]));
        $refused(fn () => $this->store->fresh()->update(['payment_settings' => $settings]));
        $refused(fn () => $this->store->fresh()->forceFill(['payment_settings' => ['click' => ['service_id' => $this->platform['click']['service_id']]]])->save());
        $refused(function () {
            $store = $this->store->fresh();
            $store->active_payment_methods = ['cash'];
            $store->payment_settings = array_merge($store->payment_settings ?? [], ['paylov' => ['consumer_secret' => $this->platform['paylov']['consumer_secret']]]);
            $store->save();
        });
        // Even on a store collecting into the platform's account (switching it to its own account later would use them)
        $this->store->forceFill(['payment_account_type' => 'platform_account'])->save();
        $refused(fn () => $this->store->fresh()->update(['payment_settings' => $settings]));

        $this->assertSame(0, Store::where('name', 'Seeded')->count());
        $this->assertArrayNotHasKey('secret_key', $this->store->fresh()->payment_settings['payme'] ?? []);
    }

    public function test_a_store_already_holding_the_platforms_key_is_left_as_it_is_and_reported(): void
    {
        // Written before the rule (a raw write, as an old release would have left it)
        $bad = $this->makeStore('Old Mistake');
        $settings = ['payme' => ['enabled' => true, 'merchant_id' => 'own-1', 'secret_key' => $this->platform['payme']['secret_key']], 'cash' => ['enabled' => true]];
        DB::table('stores')->where('id', $bad->id)->update(['payment_account_type' => 'own_account', 'payment_settings' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode($settings))]);
        $other = $this->makeStore('Old Paylov Mistake');
        DB::table('stores')->where('id', $other->id)->update(['payment_settings' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['paylov' => ['callback_password' => $this->platform['paylov']['callback_password']]]))]);

        // Saves that do not submit that value still work: an unrelated column, the settings with the secret left blank
        $bad->refresh()->forceFill(['debt_balance' => 1200])->save();
        $bad->refresh()->update(['payment_settings' => array_merge($bad->payment_settings, ['delivery_fee' => 5000, 'payme' => ['enabled' => true, 'merchant_id' => 'own-1', 'secret_key' => '']])]);
        $this->assertSame($this->platform['payme']['secret_key'], $bad->fresh()->payment_settings['payme']['secret_key'], 'existing data was changed');

        $ciphertext = DB::table('stores')->where('id', $bad->id)->value('payment_settings');

        // Reported: the count and the store ids, never a value; exit code 1 so a deploy check sees it
        $exit = Artisan::call('payments:own-credentials-check');
        $output = Artisan::output();
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('2', $output);
        $this->assertStringContainsString("#{$bad->id}", $output);
        $this->assertStringContainsString("#{$other->id}", $output);
        $this->assertStringContainsString('payme.secret_key', $output);
        $this->assertStringContainsString('paylov.callback_password', $output);
        $this->assertStringNotContainsString("#{$this->store->id}", $output);
        $this->assertNoValueLeaked($output);

        // The check itself changes nothing
        $this->assertSame($this->platform['payme']['secret_key'], $bad->fresh()->payment_settings['payme']['secret_key']);
        $this->assertSame($ciphertext, DB::table('stores')->where('id', $bad->id)->value('payment_settings'));

        // Once the owner enters its own key, nothing is left to report
        $bad->refresh()->update(['payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-1', 'secret_key' => 'own-' . Str::random(30)]]]);
        DB::table('stores')->where('id', $other->id)->update(['payment_settings' => null]);
        $this->assertSame(0, Artisan::call('payments:own-credentials-check'));
        $this->assertStringContainsString('0', Artisan::output());
    }

    // ── Review fixes ────────────────────────────────────────────────────────────────────────────────────────────────

    /** An undecryptable provider row (APP_KEY rotated without APP_PREVIOUS_KEYS) */
    private function unreadableProviderRow(string $slug, bool $active = false): void
    {
        DB::table('payment_providers')->where('slug', $slug)->delete();
        DB::table('payment_providers')->insert([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_active' => $active,
            'credentials' => 'eyJpdiI6Im5vdC1hLWNpcGhlcnRleHQifQ==',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_unreadable_provider_row_never_breaks_a_save_or_the_check(): void
    {
        // An inactive Uzum row this install cannot decrypt, and Payme's too: Payme falls back to .env
        $this->unreadableProviderRow('uzum');
        $this->unreadableProviderRow('payme', true);
        Log::spy();

        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => 'own-' . Str::random(30)]])->assertOk();
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->platform['payme']['secret_key']]])
            ->assertStatus(422)->assertJsonPath('data.fields', ['payment_settings.payme.secret_key']);

        // A warning names the provider only
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => ($context ?? []) === ['provider' => 'uzum']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => ($context ?? []) === ['provider' => 'payme']);

        // The check reports it as an error, not a crash (exit 2), still checking every store against .env
        $exit = Artisan::call('payments:own-credentials-check');
        $output = Artisan::output();
        $this->assertSame(2, $exit);
        $this->assertStringContainsString('uzum', $output);
        $this->assertStringContainsString('payme', $output);
        $this->assertNoValueLeaked($output);
    }

    public function test_the_check_exit_codes(): void
    {
        // 0: nothing found
        $this->assertSame(0, Artisan::call('payments:own-credentials-check'));

        // 1: a store found
        $bad = $this->makeStore('Old Mistake');
        DB::table('stores')->where('id', $bad->id)->update(['payment_settings' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['payme' => ['secret_key' => $this->platform['payme']['secret_key']]]))]);
        $this->assertSame(1, Artisan::call('payments:own-credentials-check'));

        // 2: a store's settings cannot be read (an error, whatever else was found)
        $unreadable = $this->makeStore('Unreadable');
        DB::table('stores')->where('id', $unreadable->id)->update(['payment_settings' => 'not-a-ciphertext']);
        $this->assertSame(2, Artisan::call('payments:own-credentials-check'));
        $this->assertStringContainsString("#{$bad->id}", Artisan::output());
    }

    public function test_low_entropy_logins_alone_are_never_grounds_for_refusal(): void
    {
        // Paylov's username and callback login, Click's Merchant API user: equal to the platform's, saved
        $paylov = ['enabled' => true] + self::ownPaylov();
        $paylov['username'] = $this->platform['paylov']['username'];
        $paylov['callback_login'] = $this->platform['paylov']['callback_login'];
        $this->save(['paylov' => $paylov])->assertOk();
        $this->assertSame($this->platform['paylov']['username'], $this->store->fresh()->payment_settings['paylov']['username']);

        $this->store->fresh()->forceFill(['payment_settings' => ['click' => ['merchant_user_id' => $this->platform['click']['merchant_user_id']]]])->save();
        $this->assertSame($this->platform['click']['merchant_user_id'], $this->store->fresh()->payment_settings['click']['merchant_user_id']);

        // Nor reported
        $this->assertSame(0, Artisan::call('payments:own-credentials-check'));
    }

    public function test_refusals_are_limited_per_store_and_logged_by_field_name(): void
    {
        Log::spy();
        $refused = ['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->platform['payme']['secret_key']]];
        for ($i = 0; $i < 5; $i++) {
            $this->save($refused)->assertStatus(422)->assertJsonPath('code', 'platform_credential');
        }
        Log::shouldHaveReceived('warning')->times(5)->withArgs(fn ($message, $context = []) => ($context ?? []) === ['store_id' => $this->store->id, 'fields' => ['payment_settings.payme.secret_key']]);

        // The sixth credential-changing save is a 429, even one with the store's own values: no more guesses
        $own = ['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => 'own-' . Str::random(30)]];
        foreach ([$refused, $own] as $settings) {
            $response = $this->save($settings)->assertStatus(429)
                ->assertJsonPath('code', 'platform_credential_limited')
                ->assertJsonPath('data.reason', 'platform_credential_limited');
            $this->assertGreaterThan(0, $response->json('data.retry_after'));
            $this->assertNoValueLeaked($response->getContent());
        }
        $this->assertArrayNotHasKey('secret_key', $this->store->fresh()->payment_settings['payme'] ?? []);

        // A save that changes no credential is not limited: a switch, the secret left blank or masked
        $this->save(['delivery_fee' => 3000, 'payme' => ['enabled' => true, 'secret_key' => '']])->assertOk();
        $this->save(['payme' => ['enabled' => false, 'secret_key' => '••••••']])->assertOk();

        // Another store is not limited
        $other = $this->makeStore('Other');
        $other->forceFill(['payment_account_type' => 'own_account'])->save();
        $otherOwner = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $other);
        $this->actingAs($otherOwner)->postJson('/api/v1/owner/settings', ['is_open' => true, 'payment_settings' => $own])->assertOk();

        // After the window the store's own values save
        $this->travel(24)->hours();
        $this->travel(1)->minutes();
        $this->save($own)->assertOk();
    }

    public function test_identifiers_are_compared_canonically_and_secrets_exactly(): void
    {
        // Another case, a leading zero of a numeric id: still the platform's
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => strtoupper($this->platform['payme']['merchant_id']), 'secret_key' => 'own-' . Str::random(30)]])
            ->assertStatus(422)->assertJsonPath('data.fields', ['payment_settings.payme.merchant_id']);
        $this->save(['click' => ['enabled' => true, 'service_id' => '00' . $this->platform['click']['service_id'], 'merchant_id' => (string) random_int(100000, 999999), 'secret_key' => 'own-' . Str::random(30)]])
            ->assertStatus(422)->assertJsonPath('data.fields', ['payment_settings.click.service_id']);
        $paylov = ['enabled' => true] + self::ownPaylov();
        $paylov['merchant_id'] = strtoupper($this->platform['paylov']['merchant_id']);
        $paylov['consumer_key'] = strtoupper($this->platform['paylov']['consumer_key']);
        $this->save(['paylov' => $paylov])->assertStatus(422)
            ->assertJsonPath('data.fields', ['payment_settings.paylov.merchant_id', 'payment_settings.paylov.consumer_key']);

        // Invisible characters, whichever path writes them (the model: no request validation in the way)
        $refused = fn (\Closure $write) => $this->assertThrows($write, \App\Services\Payment\PlatformCredentialException::class);
        $refused(fn () => $this->store->fresh()->update(['payment_settings' => ['payme' => ['merchant_id' => "\u{200B}" . substr($this->platform['payme']['merchant_id'], 0, 5) . "\u{00A0}" . substr($this->platform['payme']['merchant_id'], 5) . "\u{FEFF}"]]]));
        $refused(fn () => $this->store->fresh()->update(['payment_settings' => ['click' => ['merchant_id' => "\t0" . $this->platform['click']['merchant_id'] . "\u{2007}"]]]));

        // ...on the platform's side too
        config(['services.click.merchant_id' => "\u{00A0}" . $this->platform['click']['merchant_id'] . "\u{200B}"]);
        $refused(fn () => $this->store->fresh()->update(['payment_settings' => ['click' => ['merchant_id' => $this->platform['click']['merchant_id']]]]));

        // A secret stays exact: another case is another key
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => strtoupper($this->platform['payme']['secret_key'])]])->assertOk();
    }

    public function test_an_identifier_with_whitespace_invisible_or_link_characters_is_refused(): void
    {
        $cases = [' ', '&', ';', '=', '?', '#', '/', "\u{200B}", "\u{00A0}", "\t", "\u{0007}"];
        foreach ($cases as $char) {
            $id = 'own' . $char . 'x';
            $this->save(['payme' => ['enabled' => true, 'merchant_id' => $id, 'secret_key' => 'own-' . Str::random(30)]])
                ->assertStatus(422)->assertJsonValidationErrors('payment_settings.payme.merchant_id')
                ->assertJsonPath('data.reason', 'validation');
            $this->save(['click' => ['enabled' => true, 'service_id' => $id, 'merchant_id' => $id, 'secret_key' => 'own-' . Str::random(30)]])
                ->assertStatus(422)->assertJsonValidationErrors(['payment_settings.click.service_id', 'payment_settings.click.merchant_id']);
            $paylov = ['enabled' => true] + self::ownPaylov();
            $paylov['consumer_key'] = $id;
            $paylov['merchant_id'] = $id;
            $this->save(['paylov' => $paylov])
                ->assertStatus(422)->assertJsonValidationErrors(['payment_settings.paylov.consumer_key', 'payment_settings.paylov.merchant_id']);
        }
        // A secret may hold any of them; no provider-specific format is imposed on an id
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'Own-Id_' . Str::random(8) . '.:+-', 'secret_key' => 'a&b;c=d?e#f/g' . Str::random(20)]])->assertOk();

        // A value already stored that would fail now never blocks a save that sends it back unchanged
        $settings = $this->store->fresh()->payment_settings;
        $settings['payme']['merchant_id'] = 'old/id&x';
        DB::table('stores')->where('id', $this->store->id)->update(['payment_settings' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode($settings))]);
        $this->save(['delivery_fee' => 7000, 'payme' => ['enabled' => true, 'merchant_id' => 'old/id&x', 'secret_key' => '']])->assertOk();
        $this->assertSame('old/id&x', $this->store->fresh()->payment_settings['payme']['merchant_id']);
        $this->assertEquals(7000, $this->store->fresh()->payment_settings['delivery_fee']);
        // A new one is judged
        $this->save(['payme' => ['enabled' => true, 'merchant_id' => 'new/id', 'secret_key' => '']])->assertStatus(422)->assertJsonValidationErrors('payment_settings.payme.merchant_id');
    }
}
