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

        foreach ($providers as $provider) {
            \App\Models\PaymentProvider::updateOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
