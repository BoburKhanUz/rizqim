<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-23 (C3): the staff pay settings of a branch (the pay period: day, week, half-month or month of business days)
     * and its wage rules (App\Domain\Pos\StaffPay). A rule belongs to one staff member (`user_id`) or to a role (`role`),
     * `subject` = "user:<id>" or "role:<role>" keeps one version per subject and start date; a person's own rule wins
     * over their role's. `components` is the list of parts that add up (shift rate, hourly rate, % of own sales, % of
     * the service charge, guarantee with %, event rate, tips): App\Domain\Pos\StaffPay\PayComponents. A version is in
     * force from `effective_from` (a business date) until the next version of the same subject.
     */
    public function up(): void
    {
        Schema::create('staff_pay_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('period', 16)->default('month'); // App\Domain\Pos\StaffPay\PayPeriod
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_pay_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 16)->nullable(); // App\Enums\UserRole, when the rule is a role's
            $table->string('subject', 40); // user:<id> | role:<role>
            $table->date('effective_from');
            $table->jsonb('components');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'subject', 'effective_from']);
            $table->index(['branch_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_pay_rules');
        Schema::dropIfExists('staff_pay_settings');
    }
};
