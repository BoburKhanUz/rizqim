<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-43 (D3): gift certificates and vouchers (App\Domain\Pos\Vouchers). A `vouchers` row is one certificate: its code
 * (human 4-4-4, unique in the store; the printed QR carries the code), the face value and what is left of it
 * (`balance`, moved only by VoucherLedgerService/VoucherService under the row's lock), the validity in business days
 * (`valid_from`, `valid_until`, the store's dates), the status (App\Domain\Pos\Vouchers\VoucherStatus; `expired` is
 * also worked out from the date) and the sale: tender, shift, terminal, the ADVANCE receipt, the buyer (PII, masked
 * in lists). `voucher_entries` is its history, each with the balance after it (App\Domain\Pos\Vouchers\
 * VoucherEntryType): the sale, each redemption on a bill, each refund of a voucher-paid bill back to it, a block or
 * unblock; reports read its `business_date`. Money is decimal(12,2) so'm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // where it was sold
            $table->string('kind', 16)->default('gift'); // App\Domain\Pos\Vouchers\VoucherKind
            $table->string('code', 14); // XXXX-XXXX-XXXX
            $table->decimal('face_value', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->string('currency', 3)->default('UZS');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 16)->default('active'); // App\Domain\Pos\Vouchers\VoucherStatus
            // The sale (a non-menu sale at the till: an ADVANCE receipt)
            $table->string('sale_tender', 16); // cash | card | qr
            $table->string('sale_card_type', 16)->nullable();
            $table->string('sale_transaction_id', 64)->nullable();
            $table->string('sale_reference', 100)->nullable();
            $table->foreignId('sold_shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('sold_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->uuid('fiscal_receipt_uuid')->nullable();
            $table->string('fiscal_status', 16)->nullable();
            $table->string('buyer_name', 120)->nullable();
            $table->string('buyer_phone', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('block_reason', 300)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->unique(['store_id', 'idempotency_key']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'sold_at']);
            $table->index('sold_shift_id');
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // App\Domain\Pos\Vouchers\VoucherEntryType
            $table->date('business_date'); // the branch's business day (else the store's local date)
            // What the entry moved (positive; a redemption lowers the balance, a sale and a refund raise it), 0 for a block
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2);
            $table->uuid('bill_uuid')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('refund_uuid')->nullable();
            $table->string('tender', 16)->nullable(); // the sale's
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index(['voucher_id', 'type']);
            $table->index(['store_id', 'type', 'business_date']);
            $table->index(['shift_id', 'type']);
            $table->index('bill_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('vouchers');
    }
};
