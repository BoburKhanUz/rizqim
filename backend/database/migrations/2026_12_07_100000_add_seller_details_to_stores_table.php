<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave P (receipts): the seller's legal address (VM 943 1-ilova 7: the supplier's legal address on a delivered sale;
 * consumer law art. 5 and 28-1: where the seller is) and how a customer reaches it with a complaint (a phone or a
 * Telegram handle). The owner fills both in the partner panel; the Mini App shows them as the store's `seller`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('legal_address')->nullable();
            $table->string('complaint_contact', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['legal_address', 'complaint_contact']);
        });
    }
};
