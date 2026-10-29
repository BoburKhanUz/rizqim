<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-33: the backend serves no web panel. Its own address sends people to the partner web panel (PARTNER_WEB_URL),
 * else a plain 404; the old Filament panels and their Livewire endpoints are gone.
 */
class WebRootTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_goes_to_the_partner_panel_or_is_not_found(): void
    {
        config(['services.partner_web.url' => null]);
        $this->get('/')->assertNotFound();

        config(['services.partner_web.url' => 'https://panel.rizqim.test']);
        $this->get('/')->assertRedirect('https://panel.rizqim.test');
    }

    public function test_the_old_panels_are_gone(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        foreach (['/admin', '/admin/login', '/superadmin', '/superadmin/login', '/superadmin/global-stores'] as $uri) {
            $this->get($uri)->assertNotFound();
            $this->actingAs($superadmin)->get($uri)->assertNotFound();
        }

        $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();
        $this->assertSame([], array_values(array_filter($routes, fn (string $uri) => preg_match('#^(admin|superadmin|livewire)#', $uri) === 1)));
        $this->assertFalse(class_exists(\Filament\Facades\Filament::class));
    }
}
