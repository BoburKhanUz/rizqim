<?php

use App\Http\Middleware\AuthorizeApiDocs;

/*
 * OpenAPI documentation of the backend API (dedoc/scramble), generated from the code: routes, validation rules,
 * return types and the #[...] attributes on the controllers. App\Providers\ApiDocsServiceProvider adds the parts
 * Scramble cannot infer and serves the docs; `php artisan api-spec:export` writes packages/api-spec/openapi.json,
 * which CI keeps in sync with the code.
 */
return [
    /*
     * Everything under /api: the /api/v1 endpoints and /api/broadcasting/auth (Reverb private channels). The server
     * is /api (set in ApiDocsServiceProvider, so the spec does not depend on APP_URL) and paths keep their /v1 prefix.
     */
    'api_path' => 'api',

    'api_domain' => null,

    /*
     * `php artisan scramble:export` writes here too; `php artisan api-spec:export` is the one CI checks.
     */
    'export_path' => base_path('../packages/api-spec/openapi.json'),

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'openapi_version' => \Dedoc\Scramble\OpenApiVersion::V3_1,

    'info' => [
        /*
         * A fixed value, not an env var: the exported spec must be the same on every machine.
         */
        'version' => '1.0.0',

        'description' => <<<'MD'
Rizqim backend API (`/api/v1`): Telegram Mini App, ega/menejer/kuryer ekranlari va to'lov webhooklari.

**Kirish:** `Authorization: Bearer <token>` (Laravel Sanctum). Token `POST /v1/auth/telegram` orqali olinadi.

**Javob formati:** `{ "success": bool, "message": string, "data": ... }`. Xatolarda `success: false` va `message`;
validatsiya xatolari (422) `errors` maydonida keladi.

Spetsifikatsiya koddan yaratiladi: `cd backend && php artisan api-spec:export` (`packages/api-spec/openapi.json`).
MD,
    ],

    'ui' => [
        'title' => 'Rizqim API',
    ],

    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * Ignored: ApiDocsServiceProvider sets the single relative server `/api`.
     */
    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    /*
     * Middleware of /docs/api and /docs/api.json. AuthorizeApiDocs opens them in the local and testing environments,
     * when `access.public` is on, and otherwise only to a signed-in superadmin (the Filament session).
     */
    'middleware' => [
        'web',
        AuthorizeApiDocs::class,
    ],

    /*
     * API_DOCS_PUBLIC=true opens the docs to everyone (e.g. on staging). Off by default: in production the docs
     * describe internal endpoints and must not be public.
     */
    'access' => [
        'public' => (bool) env('API_DOCS_PUBLIC', false),
    ],

    'extensions' => [
        \App\OpenApi\EloquentModelExtension::class,
    ],

    /*
     * Routes behind auth:sanctum get the bearer scheme; all other routes (Telegram login, webhooks, health) are
     * documented as public.
     */
    'security_strategy' => \App\OpenApi\SanctumSecurityStrategy::class,
];
