<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P2-30: the API speaks Uzbek (docs/API.md §4): Laravel's validation and other built-in messages of an /api request
 * come from lang/uz, whatever APP_LOCALE says. `app.api_locale` (API_LOCALE) changes it.
 */
class SetApiLocale
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('app.api_locale', 'uz'));

        return $next($request);
    }
}
