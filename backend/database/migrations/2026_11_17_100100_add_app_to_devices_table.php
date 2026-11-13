<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 stage B: the POS app a terminal runs (App\Enums\DeviceApp: cashier, waiter, kds, customer_display), sent by the
 * app when it pairs; null for devices paired before. The branch's default LAN hub is a cashier till, never a waiter's
 * phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('app', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('app');
        });
    }
};
