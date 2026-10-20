<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramSecretToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secretToken = (string) config('services.telegram.webhook_secret');

        // Fail closed: without a configured secret anyone could post forged updates
        if ($secretToken === '') {
            Log::error('Telegram webhook rejected: TELEGRAM_WEBHOOK_SECRET is not configured.', [
                'ip' => $request->ip(),
            ]);
            abort(403, 'Unauthorized');
        }

        $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (!is_string($providedToken) || !hash_equals($secretToken, $providedToken)) {
            Log::warning('Unauthorized Telegram Webhook Access Attempt', [
                'ip' => $request->ip(),
            ]);
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
