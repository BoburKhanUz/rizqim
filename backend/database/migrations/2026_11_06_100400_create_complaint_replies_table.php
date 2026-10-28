<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31: what the RIZQIM support team wrote to the customer about a complaint (through the bot, queued like every
     * Telegram message): the text as sent, who sent it, the tayyor javob it started from. Kept as the complaint's
     * conversation; the store's own answer stays in complaints.reply.
     */
    public function up(): void
    {
        Schema::create('complaint_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->foreignId('canned_reply_id')->nullable()->constrained('support_canned_replies')->nullOnDelete();
            $table->string('channel', 16)->default('telegram');
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_replies');
    }
};
