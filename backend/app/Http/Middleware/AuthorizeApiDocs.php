<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may open the API docs (/docs/api, /docs/api.json): anyone in the local and testing environments or when
 * scramble.access.public (API_DOCS_PUBLIC) is on; nobody otherwise. P4-33: a superadmin signed in to the old Filament
 * panel used to read them on production too; that session is gone with the panels, and the same document is in the
 * repository (packages/api-spec/openapi.json). Replaces Scramble's RestrictedDocsAccess, which opens only `local`.
 */
class AuthorizeApiDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing']) || config('scramble.access.public') === true) {
            return $next($request);
        }

        abort(403);
    }
}
