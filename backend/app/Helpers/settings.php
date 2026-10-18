<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Cache::rememberForever("setting_{$key}", function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            if ($setting !== null && $setting->value !== null) {
                return $setting->value;
            }
            return config("settings.{$key}") ?? $default;
        });
    }
}
