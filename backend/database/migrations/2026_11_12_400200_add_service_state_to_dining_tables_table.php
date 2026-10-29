<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-26 (C5): the hostess' manual table state on the floor map. `service_state` is `cleaning` ("tozalanmoqda":
     * guests left, the table is not ready yet) or null (nothing marked). It only shows while the table has no open
     * order and no order was opened there after `service_state_at`; it never blocks seating or an order.
     */
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('service_state', 16)->nullable()->after('is_active');
            $table->timestamp('service_state_at')->nullable()->after('service_state');
            $table->foreignId('service_state_by')->nullable()->after('service_state_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_state_by');
            $table->dropColumn(['service_state', 'service_state_at']);
        });
    }
};
