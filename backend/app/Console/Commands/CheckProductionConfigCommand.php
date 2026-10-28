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
    protected $description = 'Fail (exit 1) on production-unsafe settings (APP_ENV production or staging): APP_DEBUG, APP_KEY, TELEGRAM_ALLOW_MOCK_AUTH, TELEGRAM_WEBHOOK_SECRET, TELEGRAM_WEBAPP_URL, Reverb keys, default super admin password';

    private const DEFAULT_SEED_PASSWORD = '12345678';

    /**
     * Deployed environments (Docker). Staging runs the same image and passes every check below; the only
     * production-only rule is the APP_ENV value itself ("production"). Everything else (debug off, APP_KEY, Telegram
     * secrets, HTTPS Mini App URL, Reverb keys, no default super admin password) is enforced on staging as well, because
     * staging is reachable from the internet and holds real-looking test data.
     */
    private const DEPLOYED_ENVIRONMENTS = ['production', 'staging'];

    public function handle(): int
    {
        $problems = [];

        if (!in_array(config('app.env'), self::DEPLOYED_ENVIRONMENTS, true)) {
            $problems[] = 'APP_ENV="' . config('app.env') . '" — "production" (staging serverida "staging") bo\'lishi shart';
        }

        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG=true — false bo\'lishi shart (xatolik sahifalari maxfiy ma\'lumotlarni ochib beradi)';
        }

        // One key for every process (web, queue, scheduler; each Docker container): sessions, cookies and encrypted
        // data written by one must be readable by the others, so production never generates its own
        if (blank(config('app.key'))) {
            $problems[] = 'APP_KEY o\'rnatilmagan — bir marta yarating va backend/.env ga yozing (php artisan key:generate --show, yoki: echo "base64:$(openssl rand -base64 32)"); productionda kalit avtomatik yaratilmaydi';
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

        // Every bot button opens the Mini App at this address; without it the buttons are left out
        $webAppUrl = trim((string) config('services.telegram.webapp_url'));
        if ($webAppUrl === '') {
            $problems[] = 'TELEGRAM_WEBAPP_URL o\'rnatilmagan — bot xabarlarida Mini App tugmalari bo\'lmaydi (Mini App\'ning https:// manzilini yozing)';
        } elseif (!str_starts_with(strtolower($webAppUrl), 'https://')) {
            $problems[] = 'TELEGRAM_WEBAPP_URL https:// bilan boshlanishi kerak — Telegram Mini App\'ni faqat HTTPS manzildan ochadi';
        }

        // Public API docs (Scramble) are fine on staging but list every endpoint to anyone on production
        if (config('app.env') === 'production' && config('scramble.access.public') === true) {
            $this->warn('OGOHLANTIRISH: API_DOCS_PUBLIC=true — /docs/api va spetsifikatsiya hammaga ochiq; productionda o\'chiring (o\'chiq bo\'lsa hujjat faqat repozitoriyda: packages/api-spec/openapi.json).');
        }

        $problems = array_merge($problems, $this->reverbProblems(), $this->accountProblems());

        if ($problems !== []) {
            $this->error('XAVFLI SOZLAMALAR: production uchun ishga tushirish rad etildi.');
            foreach ($problems as $problem) {
                $this->line('  - ' . $problem);
            }
            $this->line('backend/.env ni tuzating (keyin: php artisan config:clear).');

            return self::FAILURE;
        }

        $this->info(config('app.env') === 'staging'
            ? 'Staging sozlamalari tekshiruvdan o\'tdi (production bilan bir xil xavfsizlik shartlari).'
            : 'Production sozlamalari tekshiruvdan o\'tdi.');

        return self::SUCCESS;
    }

    /**
     * With blank keys every private channel signature (POST /api/broadcasting/auth) is an HMAC with an empty secret
     * that anyone can forge, so any socket could listen to any store's, courier's or customer's orders.
     *
     * @return list<string>
     */
    private function reverbProblems(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return [];
        }

        $problems = [];
        foreach (['app_id' => 'REVERB_APP_ID', 'key' => 'REVERB_APP_KEY', 'secret' => 'REVERB_APP_SECRET'] as $option => $name) {
            if (blank(config("broadcasting.connections.reverb.{$option}"))) {
                $problems[] = "BROADCAST_CONNECTION=reverb, lekin {$name} o'rnatilmagan — bo'sh kalitlar bilan istalgan kishi private kanal imzosini soxtalashtira oladi (ildiz .env ga yozing, docs/DEPLOY.md 9-bo'lim) yoki BROADCAST_CONNECTION=null qiling";
            }
        }

        if (in_array('*', (array) config('reverb.apps.apps.0.allowed_origins', []), true)) {
            // P2-33: every screen that opens the socket (docs/DEPLOY.md §9)
            $this->warn('OGOHLANTIRISH: REVERB_ALLOWED_ORIGINS=* — WebSocket istalgan saytdan ochiladi. Vergul bilan WebSocket ochadigan hamma hostlarni yozing: Mini App hosti (<domen>), hamkor paneli (panel.<domen>) va oshxona ekrani (KDS) uchun API hosti (RIZQIM_API_URL xosti, Mini App hosti bilan bir xil bo\'lsa shart emas), masalan: example.uz,panel.example.uz (docs/DEPLOY.md, 9-bo\'lim).');
        }

        return $problems;
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
