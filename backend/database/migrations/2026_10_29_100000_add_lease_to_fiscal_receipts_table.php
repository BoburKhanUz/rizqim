<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review P3-35: the attempt that is registering a receipt holds a lease (`lease_token` until `leased_until`): a manual
 * retry never claims a receipt another worker is sending, and only the attempt holding the lease may write its answer
 * (an issued receipt is never overwritten by a late `failed`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->uuid('lease_token')->nullable();
            $table->timestamp('leased_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->dropColumn(['lease_token', 'leased_until']);
        });
    }
};
