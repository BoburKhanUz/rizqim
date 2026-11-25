<?php

use App\Services\Payment\PaymeCashbox;
use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security review F6: a Payme call signed with a restaurant's own cashbox key (GetStatement names no account, and
 * PerformTransaction / CancelTransaction / CheckTransaction name only a transaction id) is matched to its stores through
 * `stores.payme_key_digest` (App\Services\Payment\PaymeCashbox) instead of decrypting every store's payment_settings
 * per unauthenticated request.
 *
 * Steps, each short, safe while the application runs, and resumable:
 * 1. a nullable column (metadata only, a brief ACCESS EXCLUSIVE waiting at most 500 ms);
 * 2. the index built CONCURRENTLY (writes go on; an INVALID leftover of an interrupted build is rebuilt);
 * 3. the digest of EVERY store recomputed from its key (PaymeCashbox::recomputeAll; idempotent, so running it again
 *    also repairs a digest left stale by code that does not keep it, third review M1): store by store, a row whose
 *    digest is already right is not written, every other one is a single-row UPDATE of that column only (row lock on
 *    that store, no table lock) that waits at most 5 s for a store row a ledger transaction holds. A store whose
 *    settings cannot be decrypted is left NULL (third review L3: still matched, PaymeCashbox checks NULL digests, and
 *    retried on the next run), never `none`; the count is printed.
 * Reads payment_settings with APP_KEY (as 2026_12_01_100000 wrote it); touches no cache.
 */
return new class extends Migration
{
    // CREATE INDEX CONCURRENTLY cannot run inside a transaction; the backfill commits per store
    public $withinTransaction = false;

    private const INDEX = 'stores_payme_key_digest_index';

    public function up(): void
    {
        if (!Schema::hasColumn('stores', 'payme_key_digest')) {
            $this->locked(fn () => Schema::table('stores', fn (Blueprint $table) => $table->string('payme_key_digest', 64)->nullable()), '500ms');
        }

        ConcurrentIndex::create(self::INDEX, 'stores', ['payme_key_digest']);

        [, , $failed] = PaymeCashbox::recomputeAll('5s');
        if ($failed > 0) {
            fwrite(STDERR, "DIQQAT: {$failed} ta do'konning Payme kaliti dayjesti yozilmadi (sozlamalarini o'qib bo'lmadi yoki qator band edi): NULL qoldi, to'lovlari baribir topiladi; 'php artisan payments:payme-digests' bilan qayta urinib ko'ring.\n");
        }
    }

    public function down(): void
    {
        ConcurrentIndex::drop(self::INDEX, 'stores');
        if (Schema::hasColumn('stores', 'payme_key_digest')) {
            $this->locked(fn () => Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('payme_key_digest')), '500ms');
        }
    }

    private function locked(callable $step, string $timeout): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $step();

            return;
        }
        DB::transaction(function () use ($step, $timeout) {
            DB::statement("SET LOCAL lock_timeout = '{$timeout}'");
            $step();
        });
    }
};
