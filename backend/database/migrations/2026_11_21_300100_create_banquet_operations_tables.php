<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-54 (wave 5, App\Domain\Pos\Banquet\Ops): the kitchen's production sheet, the event day and the final settlement.
 *
 * - `banquet_ops_settings`: the store's production reserve %, osh calculator ratios and kazans, portion weights, the
 *   MXIK codes of the final sale's lines (package, hall rent, extras), the contract text template and its per-year
 *   numbering (contract_no = "<year>-<seq>").
 * - `banquet_event_staff`: the staff assigned to an event (waiters' pay: event_rate counts a settled event for each).
 * - `banquet_settlements`: the final settlement of an event: the billed guests, the parts of the total, the advances
 *   offset on the ONE sale receipt (`applied_amount`, the AdvanceLedger of the event's order), an excess refunded.
 * - orders.banquet_amount / order_bills.banquet_amount: the event's package, hall rent and extras as a bill charge
 *   (OrderBill::CHARGES `banquet`; one receipt line per part, App\Domain\Pos\Banquet\Ops\BanquetFiscal), with the
 *   parts snapshotted on the order (`banquet_lines`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banquet_ops_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('reserve_percent', 5, 2)->default(0); // the production sheet's reserve on the guests
            $table->json('osh')->nullable(); // App\Domain\Pos\Banquet\Ops\OshCalculator::defaults()
            $table->json('portion_weights')->nullable(); // [{product_id, grams}] for kg on the production sheet
            $table->string('package_mxik', 32)->nullable();
            $table->string('package_package_code', 32)->nullable();
            $table->string('hall_mxik', 32)->nullable();
            $table->string('hall_package_code', 32)->nullable();
            $table->string('extra_mxik', 32)->nullable();
            $table->string('extra_package_code', 32)->nullable();
            $table->text('contract_template')->nullable();
            $table->unsignedSmallInteger('contract_year')->nullable();
            $table->unsignedInteger('contract_seq')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('banquet_event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banquet_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['banquet_event_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('banquet_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banquet_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('order_bills')->nullOnDelete();
            $table->uuid('bill_uuid')->nullable();
            $table->unsignedInteger('guests_billed');
            $table->decimal('package_total', 12, 2)->default(0);
            $table->decimal('hall_total', 12, 2)->default(0);
            $table->decimal('extras_total', 12, 2)->default(0);
            $table->decimal('orders_total', 12, 2)->default(0); // event-day lines with their charges
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('advances_total', 12, 2)->default(0); // paid on the schedule (incl. bank transfers)
            $table->decimal('applied_amount', 12, 2)->default(0); // offset on the sale receipt (prepayment)
            $table->json('applications')->nullable(); // [{bill_uuid, amount, at, reversed?}]
            $table->decimal('excess_amount', 12, 2)->default(0); // advances over the total, to give back
            $table->string('excess_status', 16)->default('none'); // none | pending | refunded
            $table->string('status', 16)->default('open'); // open | settled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('banquet_amount', 12, 2)->default(0);
            $table->json('banquet_lines')->nullable();
        });
        Schema::table('order_bills', function (Blueprint $table) {
            $table->decimal('banquet_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->dropColumn('banquet_amount');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['banquet_amount', 'banquet_lines']);
        });
        Schema::dropIfExists('banquet_settlements');
        Schema::dropIfExists('banquet_event_staff');
        Schema::dropIfExists('banquet_ops_settings');
    }
};
