<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-53 (wave 5, the payment schedule of a banquet event; App\Domain\Pos\Banquet\Payments):
 *
 * - `banquet_payment_settings`: the store's schedule template (deposit %, interims % and days before, the rest on the
 *   day), the cancellation rule table (days before the event → retained % of what was paid) and the reminder days.
 * - `banquet_payment_entries`: every movement of money against a schedule row — a payment taken at the till (its
 *   shift, tender and ADVANCE receipt), a bank transfer recorded by the manager (no shift, no receipt), a refund on a
 *   cancellation (REFUND receipt against the advance; cash from the drawer of the open shift; a bank transfer one is
 *   only marked "to be returned by transfer") and a retained part (SALE receipt of the cancellation service line paid
 *   by the advance as prepayment). The X/Z report and the "Tadbirlar" report read money from here.
 * - `banquet_cancellations`: what a cancellation computed and decided (rule band, retained and refunded parts, the
 *   manager's override with its reason).
 * - `banquet_payment_reminders`: one Telegram reminder per schedule row and day (the daily command is idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banquet_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            // list<{kind: deposit|interim|final, percent: number|null (null: the rest), days_before: int|null (null: at booking)}>
            $table->json('schedule_template')->nullable();
            // list<{min_days: int, retained_percent: number}>: the band with the largest min_days ≤ days before applies
            $table->json('cancellation_rules')->nullable();
            $table->json('reminder_days')->nullable(); // list<int>: days before a due date the owner's chat hears
            $table->boolean('overdue_reminders')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('banquet_payment_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banquet_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banquet_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // App\Domain\Pos\Banquet\Payments\BanquetEntryType
            $table->decimal('amount', 12, 2);
            $table->string('tender', 16); // cash | card | qr | bank_transfer
            $table->string('card_type', 32)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('reference', 120)->nullable();
            $table->date('business_date');
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->boolean('transfer_pending')->default(false); // a bank transfer to give back by transfer
            $table->uuid('fiscal_receipt_uuid')->nullable();
            $table->string('fiscal_status', 16)->nullable();
            $table->uuid('original_receipt_uuid')->nullable(); // a refund's advance receipt
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamps();

            $table->index(['banquet_event_id', 'type']);
            $table->index(['store_id', 'business_date']);
            $table->index('shift_id');
            $table->unique(['banquet_event_id', 'idempotency_key']);
        });

        Schema::create('banquet_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banquet_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('days_before');
            $table->decimal('rule_percent', 5, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('computed_retained', 12, 2)->default(0);
            $table->decimal('retained_amount', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('override_reason', 500)->nullable();
            $table->string('reason', 500)->nullable();
            $table->date('business_date');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'business_date']);
        });

        Schema::create('banquet_payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banquet_payment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // before | overdue
            $table->date('sent_on');
            $table->timestamps();

            $table->unique(['banquet_payment_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banquet_payment_reminders');
        Schema::dropIfExists('banquet_cancellations');
        Schema::dropIfExists('banquet_payment_entries');
        Schema::dropIfExists('banquet_payment_settings');
    }
};
