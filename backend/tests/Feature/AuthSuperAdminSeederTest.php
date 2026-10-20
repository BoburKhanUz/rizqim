<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AuthSuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runSeederIn(string $environment, ?string $email, ?string $password): void
    {
        $this->app['env'] = $environment;
        config(['auth.superadmin.email' => $email, 'auth.superadmin.password' => $password]);

        $this->app->make(SuperAdminSeeder::class)->run();
    }

    public function test_production_refuses_to_seed_without_credentials(): void
    {
        try {
            $this->runSeederIn('production', null, null);
            $this->fail('Seeder must refuse to run in production without SUPERADMIN_EMAIL/SUPERADMIN_PASSWORD');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SUPERADMIN_EMAIL', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_refuses_a_short_password(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            $this->runSeederIn('production', 'root@rizqim.uz', '12345678');
        } finally {
            $this->assertDatabaseMissing('users', ['email' => 'root@rizqim.uz']);
        }
    }

    public function test_production_seeds_super_admin_from_env(): void
    {
        $this->runSeederIn('production', 'root@rizqim.uz', 'long-and-random-passphrase');

        $admin = User::where('email', 'root@rizqim.uz')->firstOrFail();
        $this->assertSame(UserRole::SUPER_ADMIN, $admin->role);
        $this->assertTrue(Hash::check('long-and-random-passphrase', $admin->password));
    }

    public function test_existing_account_password_and_role_are_never_overwritten(): void
    {
        $existing = User::create([
            'name' => 'Existing Admin',
            'email' => 'root@rizqim.uz',
            'password' => Hash::make('the-real-current-password'),
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $this->runSeederIn('production', 'root@rizqim.uz', 'another-long-password-123');

        $this->assertTrue(Hash::check('the-real-current-password', $existing->fresh()->password));
        $this->assertSame(1, User::where('email', 'root@rizqim.uz')->count());

        // The previously seeded default account keeps a password that was rotated after seeding
        $legacy = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('rotated-after-first-seed'),
            'role' => UserRole::OWNER,
        ]);

        $this->runSeederIn('local', null, null);

        $this->assertTrue(Hash::check('rotated-after-first-seed', $legacy->fresh()->password));
        $this->assertSame(UserRole::OWNER, $legacy->fresh()->role);
    }

    public function test_local_development_keeps_the_convenient_fallback(): void
    {
        $this->runSeederIn('local', null, null);

        $this->assertSame(UserRole::SUPER_ADMIN, User::where('email', 'admin@gmail.com')->firstOrFail()->role);
    }
}
