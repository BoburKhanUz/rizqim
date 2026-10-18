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
                'credentials' => [
                    'merchant_id' => '',
                    'secret_key' => '',
                    'test_mode' => 'true',
                ]
            ],
            [
                'name' => 'Click',
                'slug' => 'click',
                'is_active' => true,
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
                'credentials' => [
                    'terminal_id' => '',
                    'secret_key' => '',
                ]
            ],
            [
                'name' => 'Paynet',
                'slug' => 'paynet',
                'is_active' => true,
                'credentials' => [
                    'merchant_id' => '',
                    'secret_key' => '',
                ]
            ],
        ];

        // Adds the providers that are missing; one that exists keeps its credentials and switch (re-running the seeder
        // used to blank every merchant key entered in the superadmin panel)
        foreach ($providers as $provider) {
            \App\Models\PaymentProvider::firstOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
