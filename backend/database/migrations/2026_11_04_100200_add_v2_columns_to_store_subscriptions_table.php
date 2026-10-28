<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21 (docs/BILLING.md):
     * - billing_period monthly | yearly, and the period chosen for when the current one ends (next_billing_period);
     *   term_ends_at: the end of the yearly term paid in advance (null for a monthly subscription, and before a yearly
     *   one's first term starts, e.g. during its trial).
     * - prorate_from_plan_id: the plan an immediate upgrade came from; the row's first month is billed at the old plan's
     *   price for the days before the upgrade (a credit line).
     * - price_lock_plan_id / price_locked_until: a store moved from a P1-23 plan pays at most what that plan charged for
     *   its periods ending by then (billing:migrate-plans: the first month's price does not go up).
     * - locked_at: when the dunning routine locked the subscription (null when not locked).
     */
    public function up(): void
    {
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->string('billing_period', 16)->default('monthly')->after('tariff_plan_id');
            $table->string('next_billing_period', 16)->nullable()->after('next_tariff_plan_id');
            $table->timestamp('term_ends_at')->nullable()->after('current_period_end');
            $table->foreignId('prorate_from_plan_id')->nullable()->after('term_ends_at')->constrained('tariff_plans')->nullOnDelete();
            $table->foreignId('price_lock_plan_id')->nullable()->after('prorate_from_plan_id')->constrained('tariff_plans')->nullOnDelete();
            $table->timestamp('price_locked_until')->nullable()->after('price_lock_plan_id');
            $table->timestamp('locked_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prorate_from_plan_id');
            $table->dropConstrainedForeignId('price_lock_plan_id');
            $table->dropColumn(['billing_period', 'next_billing_period', 'term_ends_at', 'price_locked_until', 'locked_at']);
        });
    }
};
