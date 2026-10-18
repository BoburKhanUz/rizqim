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
        Schema::create('branch_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('hidden_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_product_variants');
    }
};
