<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 Tizim → To'lov tizimlari: whether a platform payment provider runs with test (sandbox) or production merchant
     * keys, and (P4-32) its on/off switch, both now read by the order flow (App\Services\Payment\PaymentProviderSwitch).
     *
     * Review C#1: until now neither did anything. The old seeder wrote Payme's credentials with `test_mode` 'true' and
     * the old panel's `is_active` toggle was never read, so what a row says is no decision anyone made. Taking them over
     * would send live Payme checkouts to the sandbox (or switch a provider off for every store). So every row starts in
     * `production` and on; a row whose credentials said test_mode is only logged. The team reviews Tizim → To'lov
     * tizimlari after migrating (docs/DEPLOY.md) and `php artisan app:check-production` warns on a provider in test or
     * off in production. The credentials themselves are not touched (`test_mode` there is hidden and ignored).
     */
    public function up(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->string('mode', 16)->default('production')->after('is_active');
        });

        DB::table('payment_providers')->update(['mode' => 'production', 'is_active' => true]);

        $saidTest = [];
        foreach (DB::table('payment_providers')->whereNotNull('credentials')->orderBy('id')->get(['slug', 'credentials']) as $row) {
            try {
                $credentials = json_decode(Crypt::decryptString((string) $row->credentials), true);
            } catch (\Throwable) {
                // Written with another APP_KEY: nothing to read
                continue;
            }
            $flag = is_array($credentials) ? ($credentials['test_mode'] ?? null) : null;
            if (filter_var($flag, FILTER_VALIDATE_BOOLEAN)) {
                $saidTest[] = (string) $row->slug;
            }
        }

        if ($saidTest !== []) {
            Log::warning("payment_providers: the old credentials said test_mode for these providers; they start in production (review Tizim → To'lov tizimlari)", ['providers' => $saidTest]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
