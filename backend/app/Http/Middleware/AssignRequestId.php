<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id, returns it in the X-Request-Id response header and adds it to the log context, so one
 * support ticket ("X-Request-Id: ...") finds every log line of that request. An id sent by a proxy or client is kept
 * when it looks safe (the same id then ties the Mini App nginx log to the backend log); anything else is replaced.
 * The Laravel Context also travels with queued jobs, so a job's log lines carry the id of the request that queued it.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(self::HEADER);
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        Context::add('request_id', $id);

        if (function_exists('Sentry\configureScope')) {
            \Sentry\configureScope(static function (\Sentry\State\Scope $scope) use ($id): void {
                $scope->setTag('request_id', $id);
            });
        }

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
