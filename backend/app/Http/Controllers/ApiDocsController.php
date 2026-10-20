<?php

namespace App\Http\Controllers;

use App\OpenApi\ApiSpec;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * GET /docs/api (Stoplight Elements UI) and GET /docs/api.json. Access: App\Http\Middleware\AuthorizeApiDocs.
 */
class ApiDocsController extends Controller
{
    public function ui(ApiSpec $spec): View
    {
        $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);
        $result = $spec->result(cached: true);

        return view($config->renderer()->view, [
            'spec' => $spec->document($result),
            'config' => $config,
            'result' => $result,
        ]);
    }

    public function document(ApiSpec $spec): JsonResponse
    {
        return response()->json(
            $spec->document($spec->result(cached: true)),
            options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
