<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-18: a till's shift (POS_SPEC 2.3, 10.2): opened on one terminal with the opening cash, closed with the counted
 * cash by banknote (Z report, `z_number` per branch) and its difference; and every movement of the cash drawer during
 * it (`cash_movements`: opening, cash sales, cash refunds, cash in/out with a category, wages paid to an employee,
 * bazaar purchases; deposits and debt repayments taken in cash). Amounts are positive; the type gives the direction.
 * One open shift per terminal (partial unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->string('status', 16)->default('open'); // open | closed
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->jsonb('counted_breakdown')->nullable(); // {"200000": 3, "50000": 4, ...}: banknote => count
            $table->decimal('difference', 12, 2)->nullable(); // counted - expected
            $table->unsignedInteger('z_number')->nullable();
            $table->boolean('force_closed')->default(false);
            $table->string('close_note', 500)->nullable(); // the manager's reason for a forced close
            $table->foreignId('forced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('summary')->nullable(); // the Z report as it was at closing
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
            $table->index(['branch_id', 'status']);
            $table->unique(['branch_id', 'z_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX pos_shifts_one_open_per_device ON pos_shifts (device_id) WHERE status = 'open'");
        }

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->string('type', 16); // App\Domain\Pos\Shifts\CashMovementType
            $table->string('category', 32)->nullable();
            $table->decimal('amount', 12, 2); // always positive; the type says in or out
            $table->string('note', 500)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete(); // wage / advance paid to
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('pos_shifts');
    }
};
