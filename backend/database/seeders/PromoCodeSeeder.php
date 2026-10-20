<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Default codes that /promo/validate used to auto-create on an empty table.
     */
    public function run(): void
    {
        $codes = [
            'TEZFOOD' => 10,
            'RIZQIM_81' => 10,
            'YANGI2026' => 15,
        ];

        foreach ($codes as $code => $percent) {
            PromoCode::firstOrCreate(['code' => $code], [
                'type' => 'percentage',
                'value' => $percent,
                'min_order_amount' => 0,
                'is_active' => true,
                'per_user_limit' => 1,
            ]);
        }
    }
}
