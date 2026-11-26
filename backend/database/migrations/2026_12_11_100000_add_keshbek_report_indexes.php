<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CREATE / DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const MOVEMENTS = 'store_transactions_keshbek_movements_index';

    private const REFERRALS = 'users_referred_by_index';

    /**
     * Keshbek review M3. KeshbekLiabilityReport reads every keshbek row of store_transactions (the platform's referral
     * bonuses and clawbacks, the legacy store-paid bonuses: KeshbekLiabilityReport::LEDGER_ROWS, word for word) in one
     * pass, of all time (opening, month, after): without an index that is a sequential scan of the whole journal, where
     * these rows are a small minority. The partial index holds only them, with type and amount included, so the pass is
     * an index-only scan; MonthlyRevenueReport's and the referral cap's referral_bonus month reads use it too
     * (type = 'referral_bonus' implies the predicate). Review H1: users.referred_by (ON DELETE SET NULL, no index) is
     * asked before an employee or an invite row is deleted (User::holdsKeshbek), and by the referral and customer card
     * reads. Both built CONCURRENTLY (writes go on); an INVALID leftover of an interrupted build is rebuilt. Neither
     * table is partitioned. Touches no cache.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (ConcurrentIndex::isInvalid(self::MOVEMENTS)) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::MOVEMENTS);
        }
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::MOVEMENTS . ' ON store_transactions (created_at) INCLUDE (type, amount) '
            . "WHERE type = 'referral_bonus' OR (type = 'commission' AND notes LIKE 'Referral cashback reward cost%')");

        ConcurrentIndex::create(self::REFERRALS, 'users', ['referred_by']);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::MOVEMENTS);
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::REFERRALS);
    }
};
