<?php

namespace App\OpenApi;

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

/**
 * Documents the Sanctum bearer token: routes behind `auth:sanctum` require it, every other route (Telegram login,
 * payment and Telegram webhooks, health) is marked public (`security: []`). A class rather than a config array, so
 * config/scramble.php holds no objects and `config:cache` keeps working.
 */
class SanctumSecurityStrategy extends MiddlewareAuthSecurityStrategy
{
    public function __construct()
    {
        parent::__construct(
            middleware: ['auth:sanctum'],
            scheme: SecurityScheme::http('bearer')
                ->as('sanctum')
                ->setDescription('Laravel Sanctum shaxsiy kirish tokeni: `Authorization: Bearer <token>`.'),
        );
    }
}
