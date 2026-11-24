<?php

use App\Models\Setting;
use App\Services\Admin\System\SystemSettings;
use App\Support\SettingSecrets;
use Illuminate\Support\Facades\Cache;

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        // Review 17 M1: a secret is stored encrypted; only that ciphertext is cached (never the plain value, nor a
        // value from .env), and it is decrypted on every read
        if (SystemSettings::isSecret((string) $key)) {
            $stored = Cache::rememberForever("setting_{$key}", fn () => Setting::where('key', $key)->value('value'));
            if ($stored !== null) {
                return SettingSecrets::decrypt((string) $stored);
            }

            return config("settings.{$key}") ?? $default;
        }

        return Cache::rememberForever("setting_{$key}", function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            if ($setting !== null && $setting->value !== null) {
                return $setting->value;
            }
            return config("settings.{$key}") ?? $default;
        });
    }
}
