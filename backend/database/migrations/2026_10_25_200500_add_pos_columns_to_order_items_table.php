<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: a POS order line's round ("bosqich", R2: who added it, which round), kitchen firing (`fire_mode` now/hold,
 * `sent_at`, the station it went to, `due_at` for a pre-order), its guest seat and note, and the void with its reason
 * ("O'CHIRILDI"). kitchen_status gains `sent`, `served` and `void` (App\Enums\KitchenItemStatus). Online lines keep
 * every new column null. `station_id` has no foreign key: kitchen_stations (P3-13) is created after this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_no')->nullable();
            $table->string('fire_mode', 8)->nullable(); // App\Enums\FireMode
            $table->unsignedBigInteger('station_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->unsignedSmallInteger('seat_no')->nullable();
            $table->string('note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropIndex(['station_id']);
            $table->dropColumn(['round_no', 'fire_mode', 'station_id', 'sent_at', 'due_at', 'voided_at', 'void_reason', 'seat_no', 'note']);
        });
    }
};
