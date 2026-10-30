<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P6-22 (C2): the split of a branch's tip pool for one business day (or one shift, `shift_id`), as computed by
 * App\Domain\Pos\Tips\TipPoolDistributor: the rule, roles and weights it used, the pool, and one line per person
 * (the basis: 1, minutes worked, sales in so'm or the role's weight; the amount in whole so'm). Recomputed on
 * request while `open`; `closed` freezes it (the tips of that period then take no more corrections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tip_distributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->string('rule', 16); // App\Domain\Pos\Tips\TipPoolRule
            $table->jsonb('roles')->nullable();
            $table->jsonb('role_weights')->nullable();
            $table->jsonb('participants')->nullable(); // the manager's own list: [{staff_id, minutes|null}]
            $table->decimal('pool_amount', 12, 2)->default(0);
            $table->unsignedInteger('tips_count')->default(0);
            $table->string('status', 16)->default('open'); // App\Domain\Pos\Tips\TipDistributionStatus
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX pos_tip_distributions_scope ON pos_tip_distributions (branch_id, business_date, COALESCE(shift_id, 0))');
        }

        Schema::create('pos_tip_distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('pos_tip_distributions')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32)->nullable();
            $table->decimal('basis', 18, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tip_distribution_lines');
        Schema::dropIfExists('pos_tip_distributions');
    }
};
