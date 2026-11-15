<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M1: the recipients of an ommaviy e'lon, listed once when it starts and handed to the Telegram queue in waves
     * at a set rate (StoreBroadcastService). One row per chat (unique), marked when its message was queued, so a
     * resumed or repeated wave never messages an owner twice and a stopped broadcast continues where it was.
     */
    public function up(): void
    {
        Schema::create('store_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_broadcast_id')->constrained('store_broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('telegram_id', 64);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['store_broadcast_id', 'telegram_id']);
            // The next wave: the broadcast's recipients not queued yet, in order
            $table->index(['store_broadcast_id', 'queued_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_broadcast_recipients');
    }
};
