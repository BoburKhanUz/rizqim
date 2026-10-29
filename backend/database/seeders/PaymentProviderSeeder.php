<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Payme',
                'slug' => 'payme',
                'is_active' => true,
                // P4-32: the mode is its own column (Tizim → To'lov tizimlari), no longer a `test_mode` credential.
                // Review C#1: production for every provider; the team switches to test there (never on production
                // unless PAYMENTS_ALLOW_TEST_MODE=true)
                'mode' => 'production',
                'credentials' => [
                    'merchant_id' => '',
                    'secret_key' => '',
                ]
            ],
            [
                'name' => 'Click',
                'slug' => 'click',
                'is_active' => true,
                'mode' => 'production',
                'credentials' => [
                    'service_id' => '',
                    'merchant_id' => '',
                    'secret_key' => '',
                ]
            ],
            [
                'name' => 'Uzum Bank',
                'slug' => 'uzum',
                'is_active' => true,
                'mode' => 'production',
                'credentials' => [
                    'terminal_id' => '',
                    'secret_key' => '',
                ]
            ],
            [
                'name' => 'Paynet',
                'slug' => 'paynet',
                'is_active' => true,
                'mode' => 'production',
                'credentials' => [
                    'merchant_id' => '',
                    'secret_key' => '',
                ]
            ],
        ];

        // Adds the providers that are missing; one that exists keeps its credentials and switch (re-running the seeder
        // used to blank every merchant key the team entered, now in the control center: Tizim → To'lov tizimlari)
        foreach ($providers as $provider) {
            \App\Models\PaymentProvider::firstOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
