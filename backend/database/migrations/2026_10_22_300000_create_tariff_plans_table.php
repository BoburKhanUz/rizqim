<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1-23: what a partner pays Rizqim (docs/BILLING.md). A plan takes a commission on online orders, a monthly
     * subscription (base fee + per POS/KDS terminal over the included ones), or both. commission_percent NULL keeps
     * the store's own commission_rate; the fees NULL mean none.
     */
    public function up(): void
    {
        Schema::create('tariff_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('monthly_base_fee', 12, 2)->nullable();
            $table->decimal('monthly_fee_per_terminal', 12, 2)->nullable();
            $table->unsignedInteger('included_terminals')->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Starting plans; the prices are placeholders the owner sets in the superadmin panel (docs/BILLING.md)
        $now = now();
        DB::table('tariff_plans')->insertOrIgnore([
            [
                'name' => 'Faqat komissiya',
                'code' => 'commission',
                'is_active' => true,
                'commission_percent' => null,
                'monthly_base_fee' => null,
                'monthly_fee_per_terminal' => null,
                'included_terminals' => 0,
                'trial_days' => 0,
                'description' => "Hozirgi tartib: har bir onlayn buyurtmadan do'kon sozlamasidagi komissiya foizi olinadi. Oylik to'lov yo'q.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'POS obuna',
                'code' => 'pos',
                'is_active' => true,
                'commission_percent' => '0.00',
                'monthly_base_fee' => null,
                'monthly_fee_per_terminal' => '150000.00',
                'included_terminals' => 0,
                'trial_days' => 14,
                'description' => "Har bir faol terminal (kassa yoki oshxona ekrani) uchun oylik to'lov, onlayn buyurtmalardan komissiya yo'q. Narx vaqtinchalik.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aralash',
                'code' => 'mixed',
                'is_active' => true,
                'commission_percent' => '5.00',
                'monthly_base_fee' => '100000.00',
                'monthly_fee_per_terminal' => '100000.00',
                'included_terminals' => 1,
                'trial_days' => 14,
                'description' => "Kamaytirilgan komissiya va oylik obuna: asosiy to'lovga 1 ta terminal kiradi, qolganlari alohida. Narxlar vaqtinchalik.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_plans');
    }
};
