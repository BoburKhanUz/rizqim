<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /docs/api and /docs/api.json are open in local/testing, and elsewhere only when API_DOCS_PUBLIC
 * (scramble.access.public) opens them. P4-33: no session opens them any more (the Filament panels' sign-in is gone).
 */
class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    private const DOC_URLS = ['/docs/api', '/docs/api.json'];

    public function test_docs_are_not_public_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach (self::DOC_URLS as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_signed_in_non_admin_cannot_read_them_in_production(): void
    {
        $this->app['env'] = 'production';
        $owner = User::factory()->create(['role' => UserRole::OWNER]);

        foreach (self::DOC_URLS as $url) {
            $this->actingAs($owner)->get($url)->assertForbidden();
        }
    }

    public function test_a_superadmin_session_does_not_open_them_in_production(): void
    {
        $this->app['env'] = 'production';
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        foreach (self::DOC_URLS as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    public function test_the_public_flag_opens_them(): void
    {
        $this->app['env'] = 'production';
        config(['scramble.access.public' => true]);

        $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'Rizqim API');
    }

    public function test_a_sanctum_token_alone_does_not_open_them(): void
    {
        $this->app['env'] = 'production';
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $token = $admin->createToken('test')->plainTextToken;

        // Nor does an API token
        $this->withToken($token)->getJson('/docs/api.json')->assertForbidden();
    }
}
