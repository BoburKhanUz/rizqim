<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave P (receipts): the seller as it was when the order was placed (legal name, STIR, legal address, phone,
 * complaint contact; App\Domain\Receipts\SellerDetails), so the customer's history keeps who sold it. Null on orders
 * from before (they show the store's current details) and on POS orders. A nullable column: metadata only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->jsonb('seller_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('seller_snapshot');
        });
    }
};
