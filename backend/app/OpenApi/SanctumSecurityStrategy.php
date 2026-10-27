<?php

namespace App\OpenApi;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\SecurityDocumentationContext;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents the Sanctum bearer token: routes behind `auth:sanctum` require it, every other route (Telegram login,
 * payment and Telegram webhooks, health) is marked public (`security: []`). A class rather than a config array, so
 * config/scramble.php holds no objects and `config:cache` keeps working.
 *
 * P4-11: routes behind `auth:admin` (the admin API) require the separate `admin` bearer token instead.
 */
class SanctumSecurityStrategy extends MiddlewareAuthSecurityStrategy
{
    public const ADMIN_SCHEME = 'admin';

    public function __construct()
    {
        parent::__construct(
            middleware: ['auth:sanctum'],
            scheme: SecurityScheme::http('bearer')
                ->as('sanctum')
                ->setDescription('Laravel Sanctum shaxsiy kirish tokeni: `Authorization: Bearer <token>`.'),
        );
    }

    public function configure(SecurityDocumentationContext $context): GeneratorConfig
    {
        return parent::configure($context)
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->components->addSecurityScheme(self::ADMIN_SCHEME, SecurityScheme::http('bearer')
                    ->as(self::ADMIN_SCHEME)
                    ->setDescription("Boshqaruv markazi sessiyasi (email + parol + 2FA, `POST /admin/auth/2fa/verify`): `Authorization: Bearer <token>`. Faqat `/admin/*` uchun."));
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers->append(function (Operation $operation, RouteInfo $routeInfo): void {
                    if (in_array('auth:admin', $routeInfo->route->gatherMiddleware(), true)) {
                        $operation->security = [new SecurityRequirement([self::ADMIN_SCHEME => []])];
                    }
                });
            });
    }
}
