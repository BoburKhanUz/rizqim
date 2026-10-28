<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21: the v2 tariff catalog (docs/BILLING.md). A v2 plan (version 2) has a tier (start | asosiy | pro), a monthly
     * and a yearly base fee, the POS and KDS terminals it includes, the price of each extra one, and its features
     * (App\Enums\PlanFeature values on top of the base ones). The P1-23 plans (commission, pos, mixed) stay as version 1
     * rows, readable for history and for the stores still on them until `billing:migrate-plans`.
     */
    public function up(): void
    {
        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('version')->default(1)->after('code');
            $table->string('tier', 16)->nullable()->after('version');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('tier');
            $table->decimal('yearly_fee', 12, 2)->nullable()->after('monthly_fee_per_terminal');
            $table->unsignedInteger('included_pos')->default(0)->after('included_terminals');
            $table->unsignedInteger('included_kds')->default(0)->after('included_pos');
            $table->decimal('extra_pos_fee', 12, 2)->nullable()->after('included_kds');
            $table->decimal('extra_kds_fee', 12, 2)->nullable()->after('extra_pos_fee');
            $table->jsonb('features')->nullable()->after('extra_kds_fee');
        });

        $now = now();
        foreach ((array) config('billing.catalog', []) as $code => $plan) {
            DB::table('tariff_plans')->insertOrIgnore([
                'name' => $plan['name'],
                'code' => $code,
                'version' => 2,
                'tier' => $code,
                'sort_order' => $plan['sort_order'],
                'is_active' => true,
                'commission_percent' => null,
                'monthly_base_fee' => $plan['monthly_fee'],
                'monthly_fee_per_terminal' => null,
                'yearly_fee' => $plan['yearly_fee'],
                'included_terminals' => $plan['included_pos'] + $plan['included_kds'],
                'included_pos' => $plan['included_pos'],
                'included_kds' => $plan['included_kds'],
                'extra_pos_fee' => $plan['extra_pos_fee'],
                'extra_kds_fee' => $plan['extra_kds_fee'],
                'features' => json_encode(array_values($plan['features'])),
                'trial_days' => $plan['trial_days'],
                'description' => $plan['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tariff_plans')->where('version', 2)->whereNotExists(
            fn ($query) => $query->select(DB::raw(1))->from('store_subscriptions')->whereColumn('store_subscriptions.tariff_plan_id', 'tariff_plans.id')
        )->delete();

        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->dropColumn(['version', 'tier', 'sort_order', 'yearly_fee', 'included_pos', 'included_kds', 'extra_pos_fee', 'extra_kds_fee', 'features']);
        });
    }
};
