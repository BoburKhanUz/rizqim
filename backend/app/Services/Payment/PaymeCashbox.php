<?php

namespace App\Services\Payment;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Security review F6 (and F2/F5): which Payme cashbox an HTTP Basic `Paycom:<key>` speaks for, without decrypting the
 * payment settings of every store on each (unauthenticated) request.
 *
 * Every store carries `payme_key_digest`, a keyed SHA-256 of its own Payme cashbox key (payment_settings.payme
 * .secret_key), kept by the Store model whenever payment_settings is saved, NONE when it has none, NULL only for a row
 * written by code that predates the column (the migration 2026_12_09_100000 fills every row). A key is matched through
 * the index on that column; the few candidates are then checked against their decrypted key in constant time, so a
 * stale digest can never authorise anything. Stores whose digest is still NULL are checked too (none once migrated),
 * so a row the digest missed never drops a Payme call.
 *
 * The HMAC key is a fixed label, deliberately not APP_KEY: rotating APP_KEY (old keys kept in APP_PREVIOUS_KEYS for
 * the encrypted settings) must not silently orphan every store's cashbox. A Payme cashbox key is a random secret Payme
 * issues, so its digest gives nothing a guess could use; it is never serialized (Store::$hidden).
 */
final class PaymeCashbox
{
    /** The digest of a store without its own Payme key. */
    public const NONE = 'none';

    private const HMAC_KEY = 'rizqim:payme-cashbox-key:v1';

    public static function digest(?string $key): string
    {
        $key = $key === null ? '' : trim($key);

        return $key === '' ? self::NONE : hash_hmac('sha256', $key, self::HMAC_KEY);
    }

    /**
     * @param  array<string, mixed>|null  $paymentSettings
     */
    public static function digestOf(?array $paymentSettings): string
    {
        $config = $paymentSettings['payme'] ?? null;
        $value = is_array($config) ? ($config['secret_key'] ?? null) : null;

        return self::digest(is_scalar($value) ? (string) $value : null);
    }

    /**
     * Security review F2: serialises, until the caller's transaction ends, every CreateTransaction of this Payme
     * transaction id — an order's, a top-up's and a POS QR's alike — so the one that commits first owns the id and the
     * other sees it (PaymeWebhookController::createTransaction, PaymePosQrWebhook::create). Callers are inside a
     * transaction and take it before any row lock.
     */
    public static function lockTransaction(string $transactionId): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['payme-transaction:' . $transactionId]);
        }
    }

    /**
     * The ids of the stores whose own Payme cashbox key this is (one cashbox may serve several stores of a chain, each
     * saving the same key), in id order; [] for none.
     *
     * @return list<int>
     */
    public static function storesWithKey(string $key): array
    {
        $digest = self::digest($key);
        if ($digest === self::NONE) {
            return [];
        }

        $ids = [];
        $candidates = Store::query()
            ->where(fn ($query) => $query->where('payme_key_digest', $digest)->orWhereNull('payme_key_digest'))
            ->whereNotNull('payment_settings')
            ->orderBy('id')
            ->get(['id', 'payment_settings']);
        foreach ($candidates as $store) {
            $own = \App\Domain\Pos\QrPayments\Providers\StoreMerchant::credential($store, 'payme', 'secret_key');
            if ($own !== null && hash_equals($own, trim($key))) {
                $ids[] = (int) $store->id;
            }
        }

        return $ids;
    }
}
