<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-30: the API answers in Uzbek (docs/API.md §4): Laravel's own validation messages of an /api request come from
 * lang/uz whatever APP_LOCALE the panels use; an endpoint's own messages still win.
 */
class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        config(['app.locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_laravels_rule_messages_arrive_in_uzbek(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/payouts', ['amount' => 10, 'card_number' => '123'])->assertUnprocessable();
        $this->assertSame(['Summa kamida 1000 bo\'lishi kerak.'], $response->json('errors.amount'));
        // The endpoint's own message wins
        $this->assertSame(["Karta raqami 16 ta raqamdan iborat bo'lishi kerak."], $response->json('errors.card_number'));
        $this->assertSame("Summa kamida 1000 bo'lishi kerak. (va yana 1 ta xato)", $response->json('message'));

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/modifier-groups', ['max_select' => 'x'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Nomi majburiy.')
            ->assertJsonPath('errors.max_select.0', "Eng ko'p tanlov butun son bo'lishi kerak.");
    }

    public function test_api_locale_can_be_configured(): void
    {
        config(['app.api_locale' => 'en']);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/modifier-groups', [])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'The name field is required.');
    }
}
