<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckProductionConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail (exit 1) on production-unsafe settings: APP_ENV, APP_DEBUG, TELEGRAM_ALLOW_MOCK_AUTH, TELEGRAM_WEBHOOK_SECRET, default super admin password';

    private const DEFAULT_SEED_PASSWORD = '12345678';

    public function handle(): int
    {
        $problems = [];

        if (config('app.env') !== 'production') {
            $problems[] = 'APP_ENV="' . config('app.env') . '" — "production" bo\'lishi shart';
        }

        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG=true — false bo\'lishi shart (xatolik sahifalari maxfiy ma\'lumotlarni ochib beradi)';
        }

        if (config('telegram.allow_mock_auth')) {
            $problems[] = 'TELEGRAM_ALLOW_MOCK_AUTH=true — false bo\'lishi shart (imzosiz Telegram login)';
        }

        $webhookSecret = (string) config('services.telegram.webhook_secret');
        if ($webhookSecret === '') {
            $problems[] = 'TELEGRAM_WEBHOOK_SECRET o\'rnatilmagan — Telegram webhook barcha yangilanishlarni rad etadi (403)';
        } elseif (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $webhookSecret)) {
            $problems[] = 'TELEGRAM_WEBHOOK_SECRET faqat A-Z, a-z, 0-9, "_" va "-" belgilaridan iborat bo\'lishi kerak (1-256 belgi)';
        }

        $problems = array_merge($problems, $this->accountProblems());

        if ($problems !== []) {
            $this->error('XAVFLI SOZLAMALAR: production uchun ishga tushirish rad etildi.');
            foreach ($problems as $problem) {
                $this->line('  - ' . $problem);
            }
            $this->line('backend/.env ni tuzating (keyin: php artisan config:clear).');

            return self::FAILURE;
        }

        $this->info('Production sozlamalari tekshiruvdan o\'tdi.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function accountProblems(): array
    {
        try {
            if (!Schema::hasTable('users')) {
                $this->warn('OGOHLANTIRISH: users jadvali hali yo\'q (migratsiyadan oldin) — hisob tekshiruvlari o\'tkazib yuborildi.');

                return [];
            }

            $problems = [];

            // password_verify: same check as Hash::check, but never throws on a foreign hash algorithm
            $weakAdmins = User::where('role', UserRole::SUPER_ADMIN->value)
                ->whereNotNull('password')
                ->get()
                ->filter(fn (User $admin) => password_verify(self::DEFAULT_SEED_PASSWORD, $admin->password));

            if ($weakAdmins->isNotEmpty()) {
                $problems[] = 'Super admin hisobida standart parol (' . self::DEFAULT_SEED_PASSWORD . ') turibdi: '
                    . $weakAdmins->map(fn (User $admin) => $admin->email ?? "#{$admin->id}")->implode(', ')
                    . ' — parolni darhol almashtiring';
            }

            $storelessStaff = User::whereIn('role', [UserRole::OWNER->value, UserRole::MANAGER->value, UserRole::COURIER->value])
                ->whereNull('store_id')
                ->count();

            if ($storelessStaff > 0) {
                $this->warn("OGOHLANTIRISH: {$storelessStaff} ta xodim hisobi hech qaysi do'konga biriktirilmagan (API ularni rad etadi) — php artisan user:assign-role bilan tuzating.");
            }

            return $problems;
        } catch (\Throwable $e) {
            return ['Ma\'lumotlar bazasini tekshirib bo\'lmadi: ' . $e->getMessage()];
        }
    }
}
