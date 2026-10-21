<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-17: the booking book (bron daftari, research report R14–R16): a room or table of a branch for a time slot,
     * the guests, the event, the contact and where the booking came from; the tables it occupies (a banquet may merge
     * several rooms: booking_tables lists every one of them, the main table included); pre-ordered dishes with the time
     * they must be ready (booking_items); deposits taken against it (booking_payments, each with its ADVANCE receipt).
     */
    public function up(): void
    {
        // dining_tables belongs to P3-12 (its migration, 2026_10_25_2xxxxx, runs before this one)
        $tables = Schema::hasTable('dining_tables');

        Schema::create('bookings', function (Blueprint $table) use ($tables) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('dining_table_id')->nullable(); // the main room or table; null: not assigned yet
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('guests_planned')->default(1);
            $table->string('event_type', 24)->default('other'); // App\Domain\Pos\Booking\BookingEventType
            $table->string('contact_name', 120);
            $table->string('contact_phone', 20)->nullable(); // +998XXXXXXXXX
            $table->string('source', 16)->default('phone'); // App\Domain\Pos\Booking\BookingSource
            $table->string('status', 16)->default('requested'); // App\Domain\Pos\Booking\BookingStatus
            $table->decimal('min_spend', 12, 2)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // opened on arrival
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['store_id', 'contact_phone']);
            $table->index(['branch_id', 'status']);

            if ($tables) {
                $table->foreign('dining_table_id')->references('id')->on('dining_tables')->nullOnDelete();
            }
        });

        Schema::create('booking_tables', function (Blueprint $table) use ($tables) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('dining_table_id');
            $table->timestamps();

            $table->unique(['booking_id', 'dining_table_id']);
            $table->index('dining_table_id');

            if ($tables) {
                $table->foreign('dining_table_id')->references('id')->on('dining_tables')->cascadeOnDelete();
            }
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 255); // snapshot for the booking card; the order line takes the menu then
            $table->string('variant_name', 255)->nullable();
            $table->string('unit', 8)->default('piece');
            $table->decimal('quantity', 12, 3);
            $table->json('modifiers')->nullable(); // [{id, uuid, group_id, group_name, name, price_delta, quantity, total}]
            $table->decimal('unit_price', 12, 2)->default(0); // estimate at booking time
            $table->string('note', 500)->nullable();
            $table->timestamp('due_at')->nullable(); // when it must be ready (osh: the kitchen starts hours before)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // linked on arrival
            $table->decimal('amount', 12, 2);
            $table->string('tender', 16); // cash | card | qr
            $table->string('card_type', 32)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('status', 16)->default('taken'); // taken | applied | refunded | retained
            $table->uuid('fiscal_receipt_uuid')->nullable(); // the ADVANCE receipt
            $table->string('fiscal_status', 16)->nullable();
            $table->decimal('applied_amount', 12, 2)->default(0);
            $table->uuid('applied_bill_uuid')->nullable(); // the last bill it was offset against
            $table->json('applications')->nullable(); // [{bill_uuid, amount, at}]
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('retained_amount', 12, 2)->default(0);
            $table->string('settle_reason', 500)->nullable(); // why it was refunded or retained
            $table->uuid('settle_receipt_uuid')->nullable(); // REFUND or SALE receipt of the refund/retention, if any
            $table->string('settle_fiscal_status', 16)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->unique(['booking_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('booking_tables');
        Schema::dropIfExists('bookings');
    }
};
