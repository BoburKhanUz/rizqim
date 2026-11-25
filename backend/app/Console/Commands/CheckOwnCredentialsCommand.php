<?php

namespace App\Console\Commands;

use App\Services\Payment\PlatformCredentialGuard;
use Illuminate\Console\Command;

/**
 * Owner decision 1: the stores that hold one of the platform's own provider credentials as their own (saved before the
 * rule refused it, or the platform's value changed to one a store holds). It only reads: nothing is changed (the owner
 * enters the store's own credentials, docs/OPERATIONS.md 30-bo'lim). Prints the count, each store id and the field
 * names, never a value. The low-entropy logins (Paylov username / callback login, Click merchant_user_id) are not
 * judged (PlatformCredentialGuard::FIELDS).
 *
 * Exit code: 0 nothing found; 1 stores found; 2 an error — a store's settings or a provider's credentials row
 * (payment_providers) this install cannot decrypt (APP_KEY / APP_PREVIOUS_KEYS). A provider row that cannot be read is
 * checked against config (.env) instead, so the stores are still listed.
 */
class CheckOwnCredentialsCommand extends Command
{
    public const FOUND = 1;
    public const ERROR = 2;

    protected $signature = 'payments:own-credentials-check';

    protected $description = "Platformaning to'lov tizimi kalitini o'z kaliti sifatida saqlagan do'konlarni ko'rsatadi (faqat o'qiydi)";

    public function handle(): int
    {
        $unreadable = 0;
        $unreadableProviders = [];
        $found = PlatformCredentialGuard::existing($unreadable, $unreadableProviders);

        foreach ($unreadableProviders as $provider) {
            $this->error("Xato: {$provider} to'lov tizimining platforma kalitlarini o'qib bo'lmadi (provider {$provider} credentials unreadable: APP_KEY / APP_PREVIOUS_KEYS). Ular o'rniga .env qiymatlari bilan tekshirildi; Tizim → To'lov tizimlari'da {$provider} kalitlarini qayta kiriting.");
        }

        $this->info('Platforma kalitini o\'z kaliti sifatida saqlagan do\'konlar: ' . count($found) . ' ta.');
        foreach ($found as $storeId => $fields) {
            $this->line("  do'kon #{$storeId}: " . implode(', ', $fields));
        }
        if ($found !== []) {
            $this->warn("Bu do'konlar mijozlarining puli platforma hisobiga tushadi va kassada Payme QR ishlamaydi. Egasi hamkor panelida o'z kalitlarini kiritishi kerak (OPERATIONS.md, 30-bo'lim). Ma'lumot o'zgartirilmadi.");
        }
        if ($unreadable > 0) {
            $this->error("Xato: {$unreadable} ta do'konning sozlamalarini o'qib bo'lmadi (APP_KEY / APP_PREVIOUS_KEYS): ular tekshirilmadi.");
        }

        if ($unreadable > 0 || $unreadableProviders !== []) {
            return self::ERROR;
        }

        return $found === [] ? self::SUCCESS : self::FOUND;
    }
}
