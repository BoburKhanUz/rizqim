<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Keyingi partiya HH:MM" (P6-15): when a branch plans to cook a product (or one variant of it) next. The till,
     * the waiter phone and the Mini App show it beside "tugadi"; opening the batch (BatchService::open) marks the
     * earliest matching plan started. A plan is a promise to the guest, never a stock: it moves no portions.
     */
    public function up(): void
    {
        Schema::create('menu_batch_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->timestamp('planned_at');
            $table->date('business_date'); // the branch's business day (Asia/Tashkent, day start of the branch)
            $table->decimal('planned_quantity', 12, 3)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('status', 12)->default('planned'); // planned | started | canceled
            $table->foreignId('menu_batch_id')->nullable()->constrained('menu_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'planned_at']);
            $table->index(['branch_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_batch_plans');
    }
};
