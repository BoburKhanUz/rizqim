<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review P6 hardware/LAN (C2): the branch keeps every "print this bill as a pre-check" request it announced
 * (App\Domain\Pos\Billing\BillPrintInbox), so a till that missed the `bill.print_requested` broadcast (offline for a
 * moment, its screen locked, restarted) fetches the outstanding ones with its own token and prints each once
 * (`printed_at`: the till that took it). Rows older than two days are pruned as new ones come.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_bill_print_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->uuid('bill_uuid');
            $table->boolean('reprint')->default(false);
            $table->unsignedBigInteger('printer_id')->nullable();
            $table->uuid('target_device')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->unsignedBigInteger('printed_device_id')->nullable();

            $table->index(['branch_id', 'printed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_bill_print_requests');
    }
};
