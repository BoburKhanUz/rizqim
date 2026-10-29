<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-16 (D5 b): a POS terminal that is an Android smart kassa (Sunmi P2/P3 and the like): its receipts go through
     * the fiscal provider's virtual kassa bound to that device (stores.fiscal_settings.smart_kassa_driver), and
     * `fiscal_kassa_id` is the id the provider knows that kassa by. False / null for every existing device.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('smart_kassa')->default(false);
            $table->string('fiscal_kassa_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['smart_kassa', 'fiscal_kassa_id']);
        });
    }
};
