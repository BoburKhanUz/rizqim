<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'sms_driver', 'value' => 'log'],
            ['key' => 'eskiz_email', 'value' => ''],
            ['key' => 'eskiz_password', 'value' => ''],
            ['key' => 'eskiz_from', 'value' => '4546'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
