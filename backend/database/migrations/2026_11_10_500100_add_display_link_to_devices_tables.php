<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-14: a customer display (devices.type = display) may follow one till: it then shows that till's cart only. Without
     * the link it shows the latest cart of its branch (one till is the usual case). Set when the pairing code is made.
     */
    public function up(): void
    {
        foreach (['devices', 'device_pairing_codes'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('display_for_device_id')->nullable()->constrained('devices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['devices', 'device_pairing_codes'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('display_for_device_id');
            });
        }
    }
};
