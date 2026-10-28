<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 Tizim → To'lov tizimlari: whether a platform payment provider runs with test (sandbox) or production merchant
     * keys. Until now the only hint was a `test_mode` entry inside the encrypted credentials (seeded for Payme); a
     * provider whose credentials say test_mode=true starts as `test`, every other one as `production`. The credentials
     * themselves are not touched.
     */
    public function up(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->string('mode', 16)->default('production')->after('is_active');
        });

        foreach (DB::table('payment_providers')->whereNotNull('credentials')->get(['id', 'credentials']) as $row) {
            try {
                $credentials = json_decode(\Illuminate\Support\Facades\Crypt::decryptString((string) $row->credentials), true);
            } catch (\Throwable) {
                // Written with another APP_KEY: nothing to read, keep the default
                continue;
            }
            $flag = is_array($credentials) ? ($credentials['test_mode'] ?? null) : null;
            if (filter_var($flag, FILTER_VALIDATE_BOOLEAN)) {
                DB::table('payment_providers')->where('id', $row->id)->update(['mode' => 'test']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
