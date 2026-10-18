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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('short_code')->unique()->nullable();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('status')->default('new'); // new, accepted, preparing, delivery, delivered, canceled
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('order_type')->default('delivery'); // delivery, pickup, dine-in
            
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('currency')->default('UZS');
            
            $table->text('notes')->nullable();
            $table->jsonb('shipping_address')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
