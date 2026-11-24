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

    public function test_every_paylov_credential_equal_to_the_platforms_is_refused(): void
    {
        foreach (array_keys($this->platform['paylov']) as $field) {
            $paylov = ['enabled' => true] + self::ownPaylov();
            $paylov[$field] = $this->platform['paylov'][$field];
            $response = $this->save(['paylov' => $paylov])->assertStatus(422)
                ->assertJsonValidationErrors("payment_settings.paylov.{$field}")
                ->assertJsonPath('data.fields', ["payment_settings.paylov.{$field}"]);
            $this->assertNoValueLeaked($response->getContent());
        }
        $this->assertArrayNotHasKey('paylov', $this->store->fresh()->payment_settings ?? []);

        // Every field at once: each is named
        $this->save(['paylov' => ['enabled' => true] + $this->platform['paylov']])->assertStatus(422)
            ->assertJsonValidationErrors(array_map(fn (string $field) => "payment_settings.paylov.{$field}", array_keys($this->platform['paylov'])));

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
        $refused(fn () => $this->store->fresh()->forceFill(['payment_settings' => ['click' => ['merchant_user_id' => $this->platform['click']['merchant_user_id']]]])->save());
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
}
