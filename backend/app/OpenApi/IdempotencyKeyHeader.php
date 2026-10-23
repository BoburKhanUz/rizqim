<?php

namespace App\OpenApi;

use App\Http\Middleware\EnsureIdempotency;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * P3-26: every route behind EnsureIdempotency documents the `Idempotency-Key` header, so the generated TypeScript and
 * Dart clients expose it. Required on the POS routes (an EnsureIdempotency scope starting with `pos.`: the controllers
 * refuse such a change without a key) unless the middleware says `optional` (EnsureIdempotency:pos.x,optional),
 * optional elsewhere (honoured when sent). A header the controller documented
 * itself (HeaderParameter) is replaced, so each operation has it once and with the same text.
 */
class IdempotencyKeyHeader implements OperationTransformer
{
    public const HEADER = 'Idempotency-Key';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $scope = $this->scope($routeInfo);
        if ($scope === false) {
            return;
        }
        [$scope, $mode] = array_pad(explode(',', (string) $scope, 2), 2, null);
        $required = str_starts_with($scope, 'pos.') && $mode !== 'optional';

        $operation->parameters = array_values(array_filter(
            $operation->parameters,
            fn ($parameter) => !($parameter instanceof Parameter && $parameter->in === 'header' && strcasecmp($parameter->name, self::HEADER) === 0),
        ));

        $header = new Parameter(self::HEADER, 'header');
        $header->required = $required;
        $header->description = $required
            ? 'Required: a new UUID per action, the same one when retrying it (428 or 400 without).'
            : 'Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.';
        $header->setSchema(Schema::fromType(new StringType));
        $operation->addParameters([$header]);
    }

    /**
     * The route's EnsureIdempotency scope (null: the middleware without one), or false when it has none.
     */
    private function scope(RouteInfo $routeInfo): string|false|null
    {
        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (!is_string($middleware) || !str_starts_with($middleware, EnsureIdempotency::class)) {
                continue;
            }
            $rest = substr($middleware, strlen(EnsureIdempotency::class));
            if ($rest === '') {
                return null;
            }
            if ($rest[0] === ':') {
                return substr($rest, 1);
            }
        }

        return false;
    }
}
