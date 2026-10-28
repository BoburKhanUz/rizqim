<?php

namespace Tests\Feature\AdminStores;

use App\Enums\PlatformRole;
use App\Models\StoreApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * P4-22: the public application form POST /api/v1/store-applications: no token, normalized phone, honeypot, one
 * application per phone a day, rate limits; the application shows in Bugun → "Yangi arizalar".
 */
class StoreApplicationFormTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('store-app:ip:127.0.0.1');
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Osh markazi',
            'format' => 'restaurant',
            'city' => 'Samarqand',
            'owner_name' => 'Aziz Karimov',
            'phone' => '90 123-45-67',
            'tin' => '123456789',
            'comment' => 'Ikki filial bor',
        ], $overrides);
    }

    public function test_an_application_lands_in_the_pipeline_without_a_token(): void
    {
        $region = $this->region('Samarqand viloyati');

        $this->postJson('/api/v1/store-applications', $this->form(['region_id' => $region->id]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', ['received' => true]);

        $application = StoreApplication::sole();
        $this->assertSame('+998901234567', $application->phone);
        $this->assertSame('application', $application->stage->value);
        $this->assertSame('website', $application->source);
        $this->assertSame($region->id, $application->region_id);
        $this->assertSame('127.0.0.1', $application->ip);
        $this->assertNotNull($application->due_on);

        // The team sees it in "Diqqat talab qiladi" (the operator has Bugun and stores.view)
        $member = $this->makeMember(PlatformRole::OPERATOR);
        $groups = collect($this->adminJson('GET', '/today/attention', $this->signIn($member))->json('data.groups'));
        $this->assertSame(1, $groups->firstWhere('kind', 'store_application_new')['count'] ?? null);
        $this->assertSame('/stores/applications/' . $application->uuid, $groups->firstWhere('kind', 'store_application_new')['items'][0]['href']);
    }

    public function test_the_same_phone_within_a_day_is_taken_once_and_the_honeypot_stores_nothing(): void
    {
        $this->postJson('/api/v1/store-applications', $this->form())->assertCreated();
        $this->postJson('/api/v1/store-applications', $this->form(['phone' => '+998 (90) 123 45 67', 'name' => 'Boshqa nom']))->assertCreated();
        $this->assertSame(1, StoreApplication::count());

        $this->postJson('/api/v1/store-applications', $this->form(['phone' => '+998911112233', 'website' => 'http://spam.example']))
            ->assertCreated()->assertJsonPath('data.received', true);
        $this->assertSame(1, StoreApplication::count());

        $this->travel(25)->hours();
        $this->postJson('/api/v1/store-applications', $this->form())->assertCreated();
        $this->assertSame(2, StoreApplication::count());
    }

    public function test_bad_fields_are_refused_in_uzbek(): void
    {
        $this->postJson('/api/v1/store-applications', $this->form(['phone' => '12345', 'format' => 'spaceship', 'tin' => '12']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'format', 'tin']);

        $inactive = \App\Models\Region::forceCreate(['name_uz' => 'Yopiq', 'is_active' => false]);
        $this->postJson('/api/v1/store-applications', $this->form(['region_id' => $inactive->id]))
            ->assertStatus(422)->assertJsonValidationErrors('region_id');
        $this->assertSame(0, StoreApplication::count());
    }

    public function test_it_is_rate_limited_per_address(): void
    {
        // 5 an hour per address, whatever the phone
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/store-applications', $this->form(['phone' => '+99893000000' . $i]))->assertCreated();
        }
        $this->postJson('/api/v1/store-applications', $this->form(['phone' => '+998930000009']))->assertStatus(429);
    }

    /**
     * Review F3-3: the phone is in the request, so anyone could use up a victim's phone allowance and make their real
     * application a 429. A phone is limited by what was accepted for it (one application a day), not by what was sent.
     */
    public function test_someone_elses_requests_cannot_block_a_phone(): void
    {
        $from = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        foreach (['10.1.0.1', '10.1.0.2', '10.1.0.3', '10.1.0.4'] as $ip) {
            $from($ip)->postJson('/api/v1/store-applications', $this->form(['phone' => '90 123-45-67', 'website' => 'http://spam.example']))->assertCreated();
        }
        $from('10.1.0.5')->postJson('/api/v1/store-applications', $this->form())->assertCreated();
        $this->assertSame(1, StoreApplication::count());
    }

    public function test_invalid_phones_do_not_share_one_bucket(): void
    {
        $from = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        foreach (['10.2.0.1', '10.2.0.2', '10.2.0.3', '10.2.0.4'] as $ip) {
            $from($ip)->postJson('/api/v1/store-applications', $this->form(['phone' => '12345']))->assertStatus(422);
        }
        // Not 429: garbage from other addresses is no reason to refuse a valid form
        $from('10.2.0.9')->postJson('/api/v1/store-applications', $this->form())->assertCreated();
        $from('10.2.0.10')->postJson('/api/v1/store-applications', $this->form(['phone' => 'x']))->assertStatus(422);
    }
}
