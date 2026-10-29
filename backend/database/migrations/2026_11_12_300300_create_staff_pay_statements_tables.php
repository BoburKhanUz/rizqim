<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-23: a branch's pay statement for one period (business dates `period_from`..`period_to`): a line per staff
     * member with what the rules gave (`components`, a snapshot with the work and sales it was computed from:
     * `metrics`), the wage money already given from the till in the period (`advances`), a manual `adjustment` with
     * its reason, and what is to pay. draft (recomputed on demand) → closed (frozen) → paid (every line paid). Paying a
     * line from the till writes a `wage` cash movement (category payroll) on an open shift of the branch.
     */
    public function up(): void
    {
        Schema::create('staff_pay_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('period', 16); // App\Domain\Pos\StaffPay\PayPeriod
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 16)->default('draft'); // App\Domain\Pos\StaffPay\StatementStatus
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('payable_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->jsonb('warnings')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'period_from']);
            $table->index(['store_id', 'period_from']);
        });

        Schema::create('staff_pay_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('statement_id')->constrained('staff_pay_statements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 255)->nullable();
            $table->string('role', 16)->nullable();
            $table->jsonb('metrics');
            $table->jsonb('components');
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('advances', 14, 2)->default(0);
            $table->decimal('adjustment', 14, 2)->default(0);
            $table->string('adjustment_reason', 500)->nullable();
            $table->decimal('payable', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('paid_method', 16)->nullable(); // cash_drawer | other
            $table->string('paid_note', 500)->nullable();
            $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['statement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_pay_statement_lines');
        Schema::dropIfExists('staff_pay_statements');
    }
};
