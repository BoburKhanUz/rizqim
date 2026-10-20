<?php

namespace App\OpenApi;

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\GeneratorResult;
use Dedoc\Scramble\Scramble;

/**
 * The OpenAPI document of the backend API: what Scramble generates from the code, plus a few normalizations Scramble
 * cannot do from the code itself (see normalize()). The /docs/api pages, `php artisan api-spec:export` and its CI
 * check all go through here, so they serve the same document.
 */
class ApiSpec
{
    /** packages/api-spec/openapi.json, relative to the backend directory. */
    public const EXPORT_PATH = '../packages/api-spec/openapi.json';

    public const ERROR_SCHEMA = 'ErrorResponse';

    private const METHOD_ORDER = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(
        private readonly Generator $generator,
        private readonly CacheableGenerator $cacheableGenerator,
    ) {}

    public static function exportPath(): string
    {
        return base_path(self::EXPORT_PATH);
    }

    /**
     * Scramble's raw result. `$cached` uses the `scramble:cache` copy when there is one (the docs pages); the export
     * always generates afresh.
     */
    public function result(bool $cached = false): GeneratorResult
    {
        $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        return $cached ? $this->cacheableGenerator->generate($config) : $this->generator->generate($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(?GeneratorResult $result = null): array
    {
        $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        return self::normalize(($result ?? $this->result())->spec($config->openApiVersion()));
    }

    /**
     * Pretty JSON with a trailing newline, as committed to packages/api-spec/openapi.json.
     *
     * @param  array<string, mixed>  $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * - one relative server, `/api`, so the document does not depend on APP_URL and "try it" calls the same host;
     * - error responses in our `{success: false, message, data}` envelope point to one ErrorResponse schema instead of
     *   repeating each literal message (clients branch on the status, not the text);
     * - success envelopes keep `message` a plain string, not the literal Scramble read from the code;
     * - /broadcasting/auth (a framework route Scramble cannot read) gets its request and response;
     * - paths, methods and components are sorted, so a change in the code shows up as a small, stable diff.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public static function normalize(array $spec): array
    {
        $spec['servers'] = [['url' => '/api', 'description' => 'Rizqim backend']];

        $usesErrorSchema = false;

        foreach ($spec['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (!in_array($method, self::METHOD_ORDER, true) || !is_array($operation)) {
                    continue;
                }

                foreach ($operation['responses'] ?? [] as $status => $response) {
                    $schema = $response['content']['application/json']['schema'] ?? null;
                    if (!is_array($schema)) {
                        continue;
                    }

                    if ((int) $status >= 400 && self::isEnvelope($schema)) {
                        $schema = ['$ref' => '#/components/schemas/' . self::ERROR_SCHEMA];
                        $usesErrorSchema = true;
                    } elseif ((int) $status < 400) {
                        $schema = self::withPlainMessage($schema);
                    }

                    $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] = $schema;
                }
            }

            $spec['paths'][$path] = self::sortMethods($spec['paths'][$path]);
        }

        if (isset($spec['paths']['/broadcasting/auth']['post'])) {
            $spec['paths']['/broadcasting/auth']['post'] = self::broadcastingAuth($spec['paths']['/broadcasting/auth']['post']);
        }

        if ($usesErrorSchema) {
            $spec['components']['schemas'][self::ERROR_SCHEMA] = self::errorSchema();
        }

        if (isset($spec['paths'])) {
            ksort($spec['paths']);
        }

        foreach (['schemas', 'responses', 'securitySchemes'] as $section) {
            if (isset($spec['components'][$section]) && is_array($spec['components'][$section])) {
                ksort($spec['components'][$section]);
            }
        }

        return $spec;
    }

    /**
     * An object (or a union of objects) with `success` and `message`, i.e. what ApiResponse::error() returns.
     *
     * @param  array<string, mixed>  $schema
     */
    private static function isEnvelope(array $schema): bool
    {
        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            return $schema['anyOf'] !== [] && collect($schema['anyOf'])->every(fn ($s) => is_array($s) && self::isEnvelope($s));
        }

        return ($schema['type'] ?? null) === 'object'
            && isset($schema['properties']['success'], $schema['properties']['message']);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function withPlainMessage(array $schema): array
    {
        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            $schema['anyOf'] = array_map(fn ($s) => is_array($s) ? self::withPlainMessage($s) : $s, $schema['anyOf']);

            return $schema;
        }

        if (($schema['type'] ?? null) === 'object' && isset($schema['properties']['success'], $schema['properties']['message'])) {
            unset($schema['properties']['message']['const'], $schema['properties']['message']['enum']);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $operations
     * @return array<string, mixed>
     */
    private static function sortMethods(array $operations): array
    {
        uksort($operations, function ($a, $b) {
            $ia = array_search($a, self::METHOD_ORDER, true);
            $ib = array_search($b, self::METHOD_ORDER, true);

            // Path-level keys (parameters, servers...) first, then the operations in a fixed method order
            return [$ia === false ? -1 : $ia, $a] <=> [$ib === false ? -1 : $ib, $b];
        });

        return $operations;
    }

    /**
     * @return array<string, mixed>
     */
    private static function errorSchema(): array
    {
        return [
            'type' => 'object',
            'description' => "Xato javobi: `success: false` va foydalanuvchiga ko'rsatiladigan `message`.",
            'properties' => [
                'success' => ['type' => 'boolean', 'const' => false],
                'message' => ['type' => 'string'],
                'data' => ['description' => "Odatda bo'sh massiv."],
                'errors' => [
                    'type' => 'object',
                    'description' => "Maydon bo'yicha xatolar (faqat ba'zi 422 javoblarida).",
                    'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'required' => ['success', 'message'],
            'title' => self::ERROR_SCHEMA,
        ];
    }

    /**
     * POST /api/broadcasting/auth is Laravel's own BroadcastController (Pusher protocol, used by laravel-echo): the
     * Reverb private-channel signature for the Sanctum user. Channels and who may join them: routes/channels.php.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private static function broadcastingAuth(array $operation): array
    {
        $body = [
            'type' => 'object',
            'properties' => [
                'socket_id' => ['type' => 'string', 'description' => 'Reverb ulanishining socket_id si.'],
                'channel_name' => ['type' => 'string', 'description' => 'Masalan, `private-orders.store.1`.'],
            ],
            'required' => ['socket_id', 'channel_name'],
        ];

        $operation['summary'] = 'Private kanalga obuna imzosi';
        $operation['description'] = "laravel-echo (Pusher protokoli) chaqiradi: Reverb private kanaliga ulanish uchun imzo beradi. "
            . "Kim qaysi kanalga ulana olishi routes/channels.php da.";
        $operation['requestBody'] = [
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => ['schema' => $body],
                'application/json' => ['schema' => $body],
            ],
        ];
        $operation['responses'] = [
            '200' => [
                'description' => 'Kanal imzosi',
                'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => [
                        'auth' => ['type' => 'string'],
                        'channel_data' => ['type' => 'string', 'description' => 'Faqat presence kanallarida.'],
                    ],
                    'required' => ['auth'],
                ]]],
            ],
            '401' => ['$ref' => '#/components/responses/AuthenticationException'],
            '403' => ['description' => "Bu kanalga ruxsat yo'q"],
        ];

        return $operation;
    }
}
