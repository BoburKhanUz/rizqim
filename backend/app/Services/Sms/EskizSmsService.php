<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EskizSmsService implements SmsServiceInterface
{
    public function send(string $phone, string $message): bool
    {
        $driver = setting('sms_driver', 'log');

        if ($driver === 'log') {
            Log::info("SMS Mocked to {$phone}: {$message}");
            return true;
        }

        if ($driver === 'eskiz') {
            $email = setting('eskiz_email');
            $password = setting('eskiz_password');
            $from = setting('eskiz_from', '4546');

            if (empty($email) || empty($password)) {
                Log::error("Eskiz SMS service is enabled but email or password is not configured.");
                return false;
            }

            // Obtain and cache token
            $token = Cache::remember('eskiz_token', now()->addDays(20), function () use ($email, $password) {
                try {
                    $response = Http::post('https://notify.eskiz.uz/api/auth/login', [
                        'email' => $email,
                        'password' => $password,
                    ]);

                    if ($response->successful()) {
                        return $response->json('data.token');
                    }

                    Log::error('Eskiz Login Failed: ' . $response->body());
                } catch (\Exception $e) {
                    Log::error('Eskiz Login Exception: ' . $e->getMessage());
                }
                return null;
            });

            if (empty($token)) {
                return false;
            }

            // Format phone number to match Eskiz expected format (e.g. 998901234567 without +)
            $cleanPhone = ltrim($phone, '+');

            try {
                $response = Http::withToken($token)->post('https://notify.eskiz.uz/api/message/sms/send', [
                    'mobile_phone' => $cleanPhone,
                    'message' => $message,
                    'from' => $from,
                ]);

                if ($response->successful()) {
                    Log::info("Eskiz SMS sent successfully to {$phone}.");
                    return true;
                }

                // If token expired/invalid, clear cache and retry once
                if ($response->status() === 401) {
                    Cache::forget('eskiz_token');
                    return $this->send($phone, $message);
                }

                Log::error("Eskiz SMS Send Failed to {$phone}: " . $response->body());
            } catch (\Exception $e) {
                Log::error("Eskiz SMS Send Exception to {$phone}: " . $e->getMessage());
            }
        }

        return false;
    }
}
