<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave P: each POS till's own UzQR API key (the restaurant makes one dynamic QR and one API key per till in uzqr.uz;
 * "Each API key works only with the QR to which it is linked", UzQR Personal Account manual v1.1). Encrypted at rest
 * (the `encrypted` cast, as devices.sync_key), never returned by any API: only whether it is set, its last four
 * characters and when it was set (App\Domain\Pos\QrPayments\UzQr\UzQrTillKeys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->text('uzqr_api_key')->nullable();
            $table->string('uzqr_api_key_last4', 4)->nullable();
            $table->timestamp('uzqr_api_key_set_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['uzqr_api_key', 'uzqr_api_key_last4', 'uzqr_api_key_set_at']);
        });
    }
};
