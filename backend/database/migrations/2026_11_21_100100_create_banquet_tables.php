<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-51/P6-52 (wave 5 "Banket va dark kitchen", the shared banquet contract; App\Domain\Pos\Banquet):
 *
 * - `banquet_events`: one event (to'y, osh, tug'ilgan kun…) of a branch on a business date in a slot (morning, day,
 *   evening, or custom start/end); `starts_at`/`ends_at` are the UTC instants the slot covers (the overlap check reads
 *   them). It takes one or several merged halls (`banquet_event_halls`). The customer (PII), the guests (guaranteed,
 *   minimum, actual), the package snapshot frozen on the contract day, the totals (so'm), an optional quote currency
 *   and rate (display only), the hold's expiry, the contract number and an optimistic `version`.
 * - `banquet_event_lines`: what the event is billed for (App\Domain\Pos\Banquet\BanquetLineKind): the package, the
 *   hall rent, extras and the event-day order. amount = unit_price × quantity × (persons ?? 1); `frozen` once the
 *   prices were stamped on the contract day.
 * - `banquet_payments`: the payment schedule (P6-53): deposit, interims and the final row with due dates; each paid row
 *   carries its tender, shift and ADVANCE receipt.
 * - The catalogue (P6-52): `banquet_packages` (+ `banquet_package_items`: menu items per person, P6-54 reads them),
 *   `banquet_extras` (per event or per person), `banquet_hall_rates` (hall rent per slot) and the store's
 *   `banquet_settings` (slot times, hold hours, warnings, MXIK codes).
 *
 * Money is decimal(12,2) so'm (decimal strings in the API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banquet_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            // {morning: {start: "HH:MM", end: "HH:MM"}, day: {...}, evening: {...}} in the store's local time
            $table->json('slots')->nullable();
            $table->unsignedSmallInteger('hold_hours')->default(72); // a hold without a deposit is released after
            $table->json('guest_warning_thresholds')->nullable(); // [200, 250, 300]
            $table->string('window_start', 5)->default('06:00'); // time-window warning (never a refusal)
            $table->string('window_end', 5)->default('23:00');
            $table->string('advance_mxik', 32)->nullable(); // the deposit/interim ADVANCE receipt line
            $table->string('advance_package_code', 32)->nullable();
            $table->string('cancellation_mxik', 32)->nullable(); // "bekor qilish haqi" (a retained deposit)
            $table->string('cancellation_package_code', 32)->nullable();
            $table->string('quote_currency', 3)->nullable(); // the default quote currency (USD), display only
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('banquet_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('per_person_price', 12, 2);
            $table->unsignedInteger('minimum_guests')->default(0);
            $table->json('included')->nullable(); // list<string>: what is included, as free text lines
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('banquet_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banquet_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255); // snapshot (or a free item without a product)
            $table->decimal('quantity_per_person', 12, 3);
            $table->string('unit', 8)->default('portion'); // portion | piece | kg | l
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('banquet_extras', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('price', 12, 2);
            $table->string('pricing', 16)->default('per_event'); // App\Domain\Pos\Banquet\BanquetExtraPricing
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('banquet_hall_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 16); // App\Domain\Pos\Banquet\BanquetSlot (custom: the rent of a custom time)
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['hall_id', 'slot']);
        });

        Schema::create('banquet_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('event_date'); // the local (Asia/Tashkent) date of the event
            $table->string('slot', 16); // App\Domain\Pos\Banquet\BanquetSlot
            $table->timestamp('starts_at'); // UTC
            $table->timestamp('ends_at');
            $table->string('event_type', 16)->default('toy'); // App\Domain\Pos\Banquet\BanquetEventType
            $table->string('status', 16)->default('hold'); // App\Domain\Pos\Banquet\BanquetEventStatus
            $table->string('title', 160)->nullable();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20)->nullable(); // +998XXXXXXXXX (PII)
            $table->unsignedInteger('guests_guaranteed')->default(0);
            $table->unsignedInteger('guests_minimum')->default(0);
            $table->unsignedInteger('guests_actual')->nullable(); // set on the day (P6-54)
            $table->foreignId('banquet_package_id')->nullable()->constrained()->nullOnDelete();
            $table->json('package_snapshot')->nullable(); // {name, per_person_price, minimum_guests, included, items}
            $table->decimal('package_total', 12, 2)->default(0);
            $table->decimal('hall_total', 12, 2)->default(0);
            $table->decimal('extras_total', 12, 2)->default(0);
            $table->decimal('orders_total', 12, 2)->default(0); // the event-day order (P6-54)
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0); // kept by the payment schedule (P6-53)
            $table->string('quote_currency', 3)->nullable(); // USD: display only, every amount is so'm
            $table->decimal('quote_rate', 14, 4)->nullable(); // so'm per 1 unit of the quote currency, contract day
            $table->timestamp('hold_expires_at')->nullable();
            $table->string('contract_no', 32)->nullable();
            $table->timestamp('frozen_at')->nullable(); // prices stamped into the lines (contract day)
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // the event-day tab (P6-54)
            $table->string('note', 2000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('released_at')->nullable(); // a hold that expired (status cancelled)
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['branch_id', 'event_date']);
            $table->index(['branch_id', 'status']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['branch_id', 'starts_at']);
            $table->unique(['store_id', 'contract_no']);
        });

        Schema::create('banquet_event_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banquet_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['banquet_event_id', 'hall_id']);
            $table->index('hall_id');
        });

        Schema::create('banquet_event_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('banquet_event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // App\Domain\Pos\Banquet\BanquetLineKind
            $table->string('name', 255);
            $table->foreignId('banquet_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('banquet_extra_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete(); // kind order (P6-54)
            $table->decimal('quantity', 12, 3)->default(1);
            $table->unsignedInteger('persons')->nullable(); // per-person lines: the guests billed
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('frozen')->default(false);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['banquet_event_id', 'kind']);
        });

        Schema::create('banquet_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banquet_event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // App\Domain\Pos\Banquet\BanquetPaymentKind
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 16)->default('due'); // App\Domain\Pos\Banquet\BanquetPaymentStatus
            $table->string('tender', 16)->nullable(); // cash | card | qr | bank_transfer
            $table->string('card_type', 32)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('reference', 120)->nullable(); // bank transfer reference
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('fiscal_receipt_uuid')->nullable(); // the ADVANCE receipt
            $table->string('fiscal_status', 16)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('retained_amount', 12, 2)->default(0);
            $table->uuid('settle_receipt_uuid')->nullable(); // REFUND or SALE receipt of a refund/retention
            $table->string('settle_fiscal_status', 16)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamps();

            $table->index(['banquet_event_id', 'status']);
            $table->index(['store_id', 'due_date']);
            $table->index('shift_id');
            $table->unique(['banquet_event_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banquet_payments');
        Schema::dropIfExists('banquet_event_lines');
        Schema::dropIfExists('banquet_event_halls');
        Schema::dropIfExists('banquet_events');
        Schema::dropIfExists('banquet_hall_rates');
        Schema::dropIfExists('banquet_extras');
        Schema::dropIfExists('banquet_package_items');
        Schema::dropIfExists('banquet_packages');
        Schema::dropIfExists('banquet_settings');
    }
};
