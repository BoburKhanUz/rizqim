<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $secretToken = config('services.telegram.webhook_secret');
        
        // If a secret is configured, enforce it
        if (!empty($secretToken)) {
            $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            
            if ($providedToken !== $secretToken) {
                \Illuminate\Support\Facades\Log::warning('Unauthorized Telegram Webhook Access Attempt', [
                    'ip' => $request->ip(),
                    'provided_token' => $providedToken
                ]);
                abort(403, 'Unauthorized');
            }
        }

        return $next($request);
    }
}
