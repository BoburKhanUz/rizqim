<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-34 (B8, module `packaging`): a branch's packaging rules ("Qadoq qoidalari"). Each rule is one container sold as
     * its own line with its own price and MXIK: `basis` order — `quantity` per order (one bag per takeaway order), or
     * item — `quantity` per portion of the dishes of one category or one product (a soup bowl per soup). `order_types`
     * lists where it applies (App\Domain\Pos\Takeaway\PackagingOrderType: POS takeaway, Telegram/Mini App pickup and
     * delivery; never the hall). `mxik` / `package_code` null: the store's fiscal "packaging" charge code.
     */
    public function up(): void
    {
        Schema::create('branch_packaging_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('basis', 8); // App\Domain\Pos\Takeaway\PackagingBasis
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->string('mxik', 17)->nullable();
            $table->string('package_code', 20)->nullable();
            $table->string('unit', 32)->default('dona');
            $table->jsonb('order_types');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_packaging_rules');
    }
};
