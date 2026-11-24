<?php

use App\Services\Payment\PaymeCashbox;
use App\Support\ConcurrentIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 * 3. the digest of every store whose digest is still NULL, store by store, each a single-row UPDATE of that column only
 *    that waits at most 5 s for a store row a ledger transaction holds (a store left NULL is retried on the next run,
 *    and is still matched meanwhile: PaymeCashbox also checks the stores whose digest is NULL).
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

        $failed = 0;
        DB::table('stores')->whereNull('payme_key_digest')->select(['id', 'payment_settings'])->orderBy('id')
            ->chunkById(200, function ($stores) use (&$failed) {
                foreach ($stores as $store) {
                    try {
                        $digest = PaymeCashbox::digestOf($this->settings($store->payment_settings));
                        $this->locked(fn () => DB::table('stores')->where('id', $store->id)->whereNull('payme_key_digest')->update(['payme_key_digest' => $digest]), '5s');
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('stores.payme_key_digest not filled for a store yet (retried on the next run)', ['store_id' => $store->id, 'error' => get_class($e)]);
                    }
                }
            });
        if ($failed > 0) {
            fwrite(STDERR, "DIQQAT: {$failed} ta do'konning Payme kaliti dayjesti yozilmadi; migratsiyani qayta ishga tushiring (to'lovlar bunda ham yo'qolmaydi).\n");
        }
    }

    public function down(): void
    {
        ConcurrentIndex::drop(self::INDEX, 'stores');
        if (Schema::hasColumn('stores', 'payme_key_digest')) {
            $this->locked(fn () => Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('payme_key_digest')), '500ms');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settings(mixed $stored): ?array
    {
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $plain = Crypt::decryptString($stored);
        } catch (DecryptException) {
            $plain = $stored; // not encrypted (an install that never ran 2026_12_01_100000)
        }
        $settings = json_decode($plain, true);

        return is_array($settings) ? $settings : null;
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
