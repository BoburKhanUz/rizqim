<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-22 (C2): one row per tip taken with a payment (`kind` tip) and per manager correction of one (`kind`
 * correction: a signed amount with its reason, `corrects_id` the tip it corrects). Tips are not revenue: they are
 * never a payment, never on the fiscal receipt, and a refund never touches them (App\Domain\Pos\Tips). The amount
 * of a tip is the sum of its row and its corrections; `staff_id` is whom a personal tip goes to (null: a pool tip,
 * or a personal tip with no waiter yet), `mode` the branch's mode when it was taken. `business_date` and
 * `shift_id` are the payment's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('order_bills')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->date('business_date');
            $table->string('kind', 16)->default('tip'); // App\Domain\Pos\Tips\TipKind
            $table->string('method', 8); // App\Domain\Pos\Tips\TipMethod
            $table->string('source', 16)->nullable(); // App\Domain\Pos\Tips\TipSource (tips only)
            $table->string('mode', 16); // App\Domain\Pos\Tips\TipMode
            $table->decimal('amount', 12, 2);
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrects_id')->nullable()->constrained('pos_tips')->cascadeOnDelete();
            $table->string('reason', 300)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
            $table->index(['staff_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tips');
    }
};
