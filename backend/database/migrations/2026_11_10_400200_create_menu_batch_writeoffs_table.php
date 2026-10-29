<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Write-off of unsold portions of a batch with a reason (P6-15): it lowers the batch's quantity_left, is kept as its
     * own record (who, why, when, on which shift) and counts in the yield report and on the Z report. Quantities are in
     * the batch's unit (portions, kg), like quantity_left. menu_batches.written_off_quantity is their running sum.
     */
    public function up(): void
    {
        Schema::create('menu_batch_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_batch_id')->constrained('menu_batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 8)->default('piece');
            $table->string('reason', 24); // App\Domain\Pos\Menu\WriteOffReason
            $table->string('note', 255)->nullable();
            $table->date('business_date');
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
            $table->index(['branch_id', 'created_at']);
            $table->index('menu_batch_id');
        });

        Schema::table('menu_batches', function (Blueprint $table) {
            $table->decimal('written_off_quantity', 12, 3)->default(0)->after('oversold_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('menu_batches', function (Blueprint $table) {
            $table->dropColumn('written_off_quantity');
        });
        Schema::dropIfExists('menu_batch_writeoffs');
    }
};
