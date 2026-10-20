<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - pin_hash / pin_set_at: the staff member's terminal PIN (bcrypt hash only);
     * - staff_code: a short number unique within the store, typed on a terminal instead of picking a name;
     * - an index for finding a customer by SMS-verified phone (users.phone_verified_at, which existed unused until
     *   now): one customer identity across Telegram and the mobile app (AccountLinkingService).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->string('staff_code', 8)->nullable();

            $table->unique(['store_id', 'staff_code']);
            $table->index(['phone', 'phone_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'staff_code']);
            $table->dropIndex(['phone', 'phone_verified_at']);
            $table->dropColumn(['pin_hash', 'pin_set_at', 'staff_code']);
        });
    }
};
