<?php

namespace App\Console\Commands;

use App\Services\Payment\PlatformCredentialGuard;
use Illuminate\Console\Command;

/**
 * Owner decision 1: the stores that hold one of the platform's own provider credentials as their own (saved before the
 * rule refused it, or the platform's value changed to one a store holds). It only reads: nothing is changed (the owner
 * enters the store's own credentials, docs/OPERATIONS.md 30-bo'lim). Prints the count, each store id and the field
 * names, never a value. Exit code 1 when any is found (or some settings could not be read), 0 otherwise.
 */
class CheckOwnCredentialsCommand extends Command
{
    protected $signature = 'payments:own-credentials-check';

    protected $description = "Platformaning to'lov tizimi kalitini o'z kaliti sifatida saqlagan do'konlarni ko'rsatadi (faqat o'qiydi)";

    public function handle(): int
    {
        $unreadable = 0;
        $found = PlatformCredentialGuard::existing($unreadable);

        $this->info('Platforma kalitini o\'z kaliti sifatida saqlagan do\'konlar: ' . count($found) . ' ta.');
        foreach ($found as $storeId => $fields) {
            $this->line("  do'kon #{$storeId}: " . implode(', ', $fields));
        }
        if ($found !== []) {
            $this->warn("Bu do'konlar mijozlarining puli platforma hisobiga tushadi va kassada Payme QR ishlamaydi. Egasi hamkor panelida o'z kalitlarini kiritishi kerak (OPERATIONS.md, 30-bo'lim). Ma'lumot o'zgartirilmadi.");
        }
        if ($unreadable > 0) {
            $this->warn("{$unreadable} ta do'konning sozlamalarini o'qib bo'lmadi (APP_KEY / APP_PREVIOUS_KEYS): ular tekshirilmadi.");
        }

        return $found === [] && $unreadable === 0 ? self::SUCCESS : self::FAILURE;
    }
}
