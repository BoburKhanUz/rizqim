<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-22 (C2, owner decision 29.10.2026): how a branch shares its tips (choy puli; App\Domain\Pos\Tips\TipSettings):
 *  - `mode`: personal (to the order's waiter, or the one picked at payment) or pool (a shared pot);
 *  - `pool_rule`: equal, hours, sales or role_weights; `pool_period`: day (business day) or shift;
 *  - `roles`: the staff roles that take part in the pool; `role_weights`: {role: weight} for role_weights.
 * One row per branch, written on the first change; a branch without one reads as the defaults (personal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tip_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 16)->default('personal'); // App\Domain\Pos\Tips\TipMode
            $table->string('pool_rule', 16)->default('equal'); // App\Domain\Pos\Tips\TipPoolRule
            $table->string('pool_period', 16)->default('day'); // App\Domain\Pos\Tips\TipPoolPeriod
            $table->jsonb('roles')->nullable(); // null: waiters only
            $table->jsonb('role_weights')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tip_settings');
    }
};
