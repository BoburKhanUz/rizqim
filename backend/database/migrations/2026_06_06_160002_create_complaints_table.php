<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['WRONG_ITEM', 'ORDER_NOT_RECEIVED', 'QUALITY_ISSUE', 'COURIER_ISSUE', 'PAYMENT_ISSUE', 'OTHER']);
            $table->text('description');
            $table->enum('status', ['pending', 'owner_replied', 'in_review', 'closed'])->default('pending');
            $table->text('reply')->nullable();
            $table->text('resolution_comment')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
