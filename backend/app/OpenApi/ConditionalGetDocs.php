<?php

namespace App\OpenApi;

use App\Http\Middleware\ConditionalGet;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Wave M2: every route behind ConditionalGet documents the optional `If-None-Match` request header and the `304 Not
 * Modified` answer (with its `ETag`); the 200 answer's body schema is untouched (docs/API.md 7.1).
 */
class ConditionalGetDocs implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $conditional = false;
        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, ConditionalGet::class)) {
                $conditional = true;
                break;
            }
        }
        if (!$conditional) {
            return;
        }

        $header = new Parameter('If-None-Match', 'header');
        $header->required = false;
        $header->description = 'Optional: the ETag of the last 200 answer. While nothing it depends on has changed the answer is 304 with no body: keep the last body and the time it was received.';
        $header->setSchema(Schema::fromType(new StringType));
        $operation->addParameters([$header]);

        $etag = new Header('The same ETag: the last body is still current.', schema: Schema::fromType(new StringType));
        $operation->addResponse((new Response(304))
            ->setDescription('Not Modified: nothing the body depends on changed since the ETag sent in If-None-Match.')
            ->addHeader('ETag', $etag));
    }
}
