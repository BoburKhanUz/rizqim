<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may open the API docs (/docs/api, /docs/api.json): anyone in the local and testing environments or when
 * scramble.access.public (API_DOCS_PUBLIC) is on; otherwise only the `viewApiDocs` gate, i.e. a superadmin signed in
 * to the admin panel (ApiDocsServiceProvider). Replaces Scramble's RestrictedDocsAccess, which opens only `local`.
 */
class AuthorizeApiDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing']) || config('scramble.access.public') === true) {
            return $next($request);
        }

        abort_unless(Gate::allows('viewApiDocs'), 403);

        return $next($request);
    }
}
