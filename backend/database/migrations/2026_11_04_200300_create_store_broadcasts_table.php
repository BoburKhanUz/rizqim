<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-22: ommaviy e'lon — a Telegram message from the team to the owners of all or selected stores (technical works,
     * news). One row per broadcast; the messages go through the queued Telegram path (SendStoreBroadcastJob).
     */
    public function up(): void
    {
        Schema::create('store_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 16); // all | selected
            $table->jsonb('store_ids')->nullable(); // audience = selected
            $table->text('text');
            $table->string('reason', 500);
            $table->unsignedInteger('stores')->default(0); // stores in the audience
            $table->unsignedInteger('recipients')->default(0); // owners with Telegram
            $table->unsignedInteger('queued')->default(0); // messages handed to the Telegram queue
            $table->string('status', 16)->default('queued'); // queued | sending | sent | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_broadcasts');
    }
};
