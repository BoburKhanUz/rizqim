<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave P (receipts): when the bot told the order's customer about this receipt (App\Jobs\SendCustomerReceiptNotice).
 * Set once, by an atomic claim before the message is queued, so a receipt is never announced twice. fiscal_receipts
 * is partitioned: the column is added on the parent and reaches every partition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->timestamp('customer_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->dropColumn('customer_notified_at');
        });
    }
};
