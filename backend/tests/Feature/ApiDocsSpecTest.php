<?php

namespace Tests\Feature;

use App\OpenApi\ApiSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The OpenAPI document (App\OpenApi\ApiSpec, dedoc/scramble) generates from the code and describes the API the apps
 * use: key paths and parameters, the Sanctum bearer scheme, public routes, the shared error schema.
 */
class ApiDocsSpecTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doc = app(ApiSpec::class)->document();
    }

    public function test_document_has_the_api_info_server_and_bearer_scheme(): void
    {
        $this->assertStringStartsWith('3.1.', $this->doc['openapi']);
        $this->assertSame('Rizqim API', $this->doc['info']['title']);
        $this->assertSame([['url' => '/api', 'description' => 'Rizqim backend']], $this->doc['servers']);

        $this->assertSame('http', $this->doc['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $this->doc['components']['securitySchemes']['sanctum']['scheme']);
        $this->assertSame([['sanctum' => []]], $this->doc['security']);
    }

    public function test_only_api_routes_are_documented(): void
    {
        foreach (array_keys($this->doc['paths']) as $path) {
            $this->assertTrue(
                str_starts_with($path, '/v1/') || $path === '/broadcasting/auth',
                "Unexpected path in the API spec: {$path}",
            );
        }

        $this->assertArrayHasKey('post', $this->doc['paths']['/broadcasting/auth']);
        $this->assertArrayNotHasKey('get', $this->doc['paths']['/broadcasting/auth']);
    }

    public function test_orders_list_documents_scope_and_cursor_pagination(): void
    {
        $operation = $this->doc['paths']['/v1/orders']['get'];
        $parameters = collect($operation['parameters'])->keyBy('name');

        $this->assertSame(['active', 'history'], $parameters['scope']['schema']['enum']);
        $this->assertSame('query', $parameters['scope']['in']);
        $this->assertSame('integer', $parameters['per_page']['schema']['type']);
        $this->assertSame('string', $parameters['cursor']['schema']['type']);

        $variants = $operation['responses']['200']['content']['application/json']['schema']['anyOf'];
        $metas = collect($variants)->map(fn (array $v) => array_keys($v['properties']['meta']['properties'] ?? []))->all();
        $this->assertContains(['next_cursor', 'per_page'], $metas);
        $this->assertContains(['truncated'], $metas);
        foreach ($variants as $variant) {
            $this->assertSame('#/components/schemas/Order', $variant['properties']['data']['items']['$ref']);
        }
    }

    public function test_cancel_returns_the_order_and_errors_use_the_shared_schema(): void
    {
        $operation = $this->doc['paths']['/v1/orders/{uuid}/cancel']['post'];

        $this->assertSame('uuid', $operation['parameters'][0]['name']);
        $this->assertSame('#/components/schemas/Order', $operation['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref']);
        $this->assertArrayNotHasKey('const', $operation['responses']['200']['content']['application/json']['schema']['properties']['message']);
        $this->assertSame('#/components/schemas/ErrorResponse', $operation['responses']['404']['content']['application/json']['schema']['$ref']);
        $this->assertSame(['success', 'message'], $this->doc['components']['schemas']['ErrorResponse']['required']);
        $this->assertArrayHasKey('note', $operation['requestBody']['content']['application/json']['schema']['properties']);
    }

    public function test_model_schemas_list_relations_and_booleans(): void
    {
        $order = $this->doc['components']['schemas']['Order'];

        // Relations are optional (present when the endpoint loads them), snake_cased like Model::toArray()
        $this->assertSame('#/components/schemas/OrderItem', $order['properties']['items']['items']['$ref']);
        $this->assertArrayHasKey('status_histories', $order['properties']);
        $this->assertNotContains('items', $order['required']);
        $this->assertContains('uuid', $order['required']);

        // Store::toArray() drops its internal (financial) columns
        $store = $this->doc['components']['schemas']['Store'];
        $this->assertArrayHasKey('name', $store['properties']);
        $this->assertArrayNotHasKey('debt_balance', $store['properties']);
        $this->assertArrayNotHasKey('telegram_bot_token', $store['properties']);

        // A PostgreSQL boolean column without a cast is a boolean, not a string
        $this->assertSame('boolean', $this->doc['components']['schemas']['Category']['properties']['is_active']['type']);
    }

    public function test_public_routes_need_no_token(): void
    {
        $this->assertSame([], $this->doc['paths']['/v1/payments/click/webhook']['post']['security']);
        $this->assertSame([], $this->doc['paths']['/v1/payments/payme/webhook']['post']['security']);
        $this->assertSame([], $this->doc['paths']['/v1/telegram/webhook']['post']['security']);
        $this->assertSame([], $this->doc['paths']['/v1/health']['get']['security']);
        $this->assertSame(['Webhooks'], $this->doc['paths']['/v1/payments/click/webhook']['post']['tags']);

        // Authenticated routes inherit the document-level bearer requirement
        $this->assertArrayNotHasKey('security', $this->doc['paths']['/v1/orders']['get']);
    }

    public function test_paths_are_sorted_for_stable_diffs(): void
    {
        $paths = array_keys($this->doc['paths']);
        $sorted = $paths;
        sort($sorted);

        $this->assertSame($sorted, $paths);
    }

    public function test_docs_json_route_serves_the_same_document(): void
    {
        $response = $this->getJson('/docs/api.json')->assertOk();

        // Compared as decoded objects: an empty schema `{}` must not turn into `[]`
        $this->assertEquals(json_decode(ApiSpec::encode($this->doc)), json_decode((string) $response->getContent()));

        $this->get('/docs/api')->assertOk()->assertSee('Rizqim API');
    }

    public function test_export_command_writes_the_file_and_check_detects_drift(): void
    {
        $path = sys_get_temp_dir() . '/rizqim-openapi-' . uniqid() . '.json';

        try {
            $this->artisan('api-spec:export', ['--path' => $path])->assertSuccessful();
            $this->assertSame(ApiSpec::encode($this->doc), File::get($path));
            $this->assertStringEndsWith("}\n", File::get($path));

            $this->artisan('api-spec:export', ['--path' => $path, '--check' => true])->assertSuccessful();

            File::put($path, str_replace('"Rizqim API"', '"Stale API"', File::get($path)));
            $this->artisan('api-spec:export', ['--path' => $path, '--check' => true])
                ->expectsOutputToContain('php artisan api-spec:export')
                ->assertFailed();
        } finally {
            File::delete($path);
        }
    }

    /**
     * P2-30: the shapes the wave 2 screens had to correct by hand (packages/ts/staff/src/api/specGaps.ts) are right in
     * the document now, and no payout response documents a card number.
     */
    public function test_the_shapes_the_staff_screens_corrected_by_hand_are_documented(): void
    {
        $paths = $this->doc['paths'];
        $data = fn (string $path, string $method = 'get', string $code = '200') => $paths[$path][$method]['responses'][$code]['content']['application/json']['schema']['properties']['data'];

        $employee = $data('/v1/owner/employees')['items'];
        $this->assertSame('#/components/schemas/UserRole', $employee['properties']['role']['$ref']);
        $this->assertSame(['object', 'null'], $employee['properties']['branch']['type']);
        $this->assertArrayHasKey('telegram_invite_token', $employee['properties']);

        $this->assertSame(['id', 'name'], array_keys($data('/v1/superadmin/locations')['properties']['regions']['items']['properties']));
        $this->assertSame('number', $data('/v1/superadmin/dashboard')['properties']['total_store_debt']['type']);
        $this->assertSame(['string', 'null'], $data('/v1/superadmin/stores')['items']['properties']['owner']['properties']['invite_link']['type']);

        $this->assertSame('object', $data('/v1/owner/settings')['properties']['payment_settings']['type']);
        $this->assertSame('object', $paths['/v1/owner/settings']['post']['requestBody']['content']['application/json']['schema']['properties']['payment_settings']['type']);
        $this->assertContains('toggle_active', array_column($paths['/v1/owner/products/{id}']['post']['parameters'], 'name'));
        $this->assertArrayHasKey('requestBody', $paths['/v1/owner/modifier-groups']['post']);
        $this->assertArrayHasKey('requestBody', $paths['/v1/owner/modifier-groups/{id}']['put']);

        foreach (['/v1/owner/finance/ledger', '/v1/owner/finance/wallet', '/v1/owner/payouts', '/v1/owner/payments', '/v1/owner/invoices'] as $path) {
            $this->assertContains('page', array_column($paths[$path]['get']['parameters'], 'name'), $path);
        }

        $payout = $data('/v1/owner/payouts', 'post');
        $this->assertSame(['pending', 'completed', 'rejected'], $payout['properties']['status']['enum']);
        $this->assertArrayHasKey('card', $payout['properties']);
        $this->assertStringNotContainsString('#/components/schemas/PayoutRequest', (string) json_encode($paths), 'a payout model (with its card number) is never a response');
    }
}
