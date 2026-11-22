<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave P review 11: a UzQR keeps what it takes to finish it whatever happens to the till or the operator.
 *
 *  - `uzqr_api_key`: the till's UzQR API key that created it (encrypted at rest as devices.uzqr_api_key, never
 *    returned). The operator ties a key to its own QR ("Each API key works only with the QR to which it is linked",
 *    manual v1.1), so the status, cancel, refunds and the receipt link of this invoice use this key even after the
 *    owner removed or replaced the till's key (M2). Wiped once nothing more can be asked about an unpaid one.
 *  - `next_check_at` / `last_checked_at` / `check_failures`: a UzQR that ended here (expired, cancelled) while the
 *    operator may still take its money is asked again until the operator settles it (H2, H3), the minute job taking
 *    the most overdue first (M1: progress, no starvation); `check_failures` counts the asks in a row with no answer
 *    (backoff). Null `next_check_at`: nothing more to ask.
 *  - `operator_cancelled_at`: the operator acknowledged the cancel (or reported the invoice cancelled).
 *  - `alerted_at`: the owner was told the QR is still unsettled (once).
 *
 * Existing UzQR operator requests take their till's current key (the ciphertext is copied as it is: the same
 * application key encrypts both columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_qr_payments', function (Blueprint $table) {
            $table->text('uzqr_api_key')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('check_failures')->default(0);
            $table->timestamp('operator_cancelled_at')->nullable();
            $table->timestamp('alerted_at')->nullable();

            $table->index('next_check_at');
        });

        DB::statement(<<<'SQL'
            UPDATE pos_qr_payments SET uzqr_api_key = devices.uzqr_api_key
            FROM devices
            WHERE devices.id = pos_qr_payments.device_id
              AND devices.store_id = pos_qr_payments.store_id
              AND pos_qr_payments.driver = 'uzqr:uzqr_operator'
              AND pos_qr_payments.uzqr_api_key IS NULL
              AND devices.uzqr_api_key IS NOT NULL
            SQL);

        // UzQRs that ended here without a payment and were not reconciled yet are asked again from now on
        DB::table('pos_qr_payments')
            ->where('provider', 'uzqr')
            ->whereIn('status', ['expired', 'cancelled'])
            ->whereNotNull('gateway_reference')
            ->whereNull('payment_id')
            ->where(fn ($query) => $query->whereNull('cancel_reason')->orWhere('cancel_reason', '!=', 'paid_unmatched'))
            ->update(['next_check_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('pos_qr_payments', function (Blueprint $table) {
            $table->dropIndex(['next_check_at']);
            $table->dropColumn(['uzqr_api_key', 'next_check_at', 'last_checked_at', 'check_failures', 'operator_cancelled_at', 'alerted_at']);
        });
    }
};
