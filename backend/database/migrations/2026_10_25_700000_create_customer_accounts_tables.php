<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-17: customer accounts for credit sales (nasiya, docs/research report R18). One account per regular customer or
     * group (phone), store-wide or tied to a branch; its debt is the sum of its entries (charge +, repayment −,
     * adjustment ±), kept in `balance` under a row lock by App\Domain\Pos\Accounts\CustomerAccountService.
     */
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable(); // +998XXXXXXXXX
            $table->string('group_name', 120)->nullable(); // "jo'ra boshi", gap guruhi
            // null: no limit; 0: no credit at all
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->default(0); // current debt (positive = the customer owes)
            $table->boolean('is_blocked')->default(false);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'phone']); // one account per phone in a store (NULLs never collide)
            $table->index(['store_id', 'balance']);
        });

        Schema::create('customer_account_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // charge | repayment | adjustment
            // Signed change of the debt: charge > 0, repayment < 0, adjustment either way
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->uuid('bill_uuid')->nullable(); // the bill closed on credit (P3-18 order_bills.uuid)
            $table->string('tender', 16)->nullable(); // repayment: cash | card | qr
            $table->string('card_type', 32)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->uuid('fiscal_receipt_uuid')->nullable(); // repayment: the CREDIT receipt
            $table->string('fiscal_status', 16)->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'id']);
            $table->unique(['customer_account_id', 'type', 'bill_uuid']); // one charge per bill
            $table->unique(['customer_account_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_entries');
        Schema::dropIfExists('customer_accounts');
    }
};
