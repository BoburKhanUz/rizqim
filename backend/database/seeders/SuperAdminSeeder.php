<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    private const MIN_PASSWORD_LENGTH = 12;

    public function run(): void
    {
        $email = config('auth.superadmin.email');
        $password = config('auth.superadmin.password');

        if (!app()->environment(['local', 'testing'])) {
            if (blank($email) || blank($password)) {
                throw new RuntimeException('SuperAdminSeeder: SUPERADMIN_EMAIL va SUPERADMIN_PASSWORD .env da berilishi shart.');
            }

            if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                throw new RuntimeException('SuperAdminSeeder: SUPERADMIN_PASSWORD kamida ' . self::MIN_PASSWORD_LENGTH . ' belgidan iborat bo\'lishi shart.');
            }
        }

        // Weak fallbacks are only reachable on a developer machine (local/testing)
        $email = $email ?: 'admin@gmail.com';
        $password = $password ?: '12345678';

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->command?->warn("SuperAdminSeeder: {$email} allaqachon mavjud — parol va rol o'zgartirilmadi.");

            if ($existing->password && Hash::check('12345678', $existing->password)) {
                $this->command?->error("SuperAdminSeeder: {$email} hisobida zaif standart parol turibdi — darhol almashtiring!");
            }

            return;
        }

        User::create([
            'email' => $email,
            'name' => 'Super Admin',
            'password' => Hash::make($password),
            'role' => UserRole::SUPER_ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("SuperAdminSeeder: {$email} super admin sifatida yaratildi.");
    }
}
