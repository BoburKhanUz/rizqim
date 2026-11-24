<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymeCashbox;
use Illuminate\Console\Command;

/**
 * Third review M1: recomputes every store's `payme_key_digest` from its Payme key (PaymeCashbox::recomputeAll). The
 * digest is only an index hint: code that does not keep it (the release before the column, after a code-only rollback)
 * leaves it stale when an owner changes the key, and that store's Payme calls then have to find it the slow way. The
 * backend entrypoint runs this after `migrate` on every deploy; run it by hand after a rollback (docs/OPERATIONS.md).
 * Idempotent; a store whose settings cannot be read is left NULL and counted (exit code 1).
 */
class RecomputePaymeDigestsCommand extends Command
{
    protected $signature = 'payments:payme-digests';

    protected $description = "Do'konlarning Payme kaliti dayjestini (stores.payme_key_digest) kalitdan qayta hisoblaydi";

    public function handle(): int
    {
        [$read, $changed, $failed] = PaymeCashbox::recomputeAll();
        $this->info("Payme kaliti dayjestlari: {$read} ta do'kon tekshirildi, yangilandi: {$changed}, yozilmadi: {$failed}.");
        if ($failed > 0) {
            $this->warn("{$failed} ta do'konning sozlamalarini o'qib bo'lmadi yoki qatori band edi: dayjesti yangilanmadi (o'qib bo'lmaganlariniki NULL: to'lovlari baribir topiladi). Logdagi store_id larni tekshiring va buyruqni qayta ishga tushiring.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
