<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Str;

/**
 * M4 stage 1: the cross-tenant check of one manifest entry (CrossTenantRouteTest) and the proof that it can fail
 * (LeakDetectorTest): A's own call, then B's ids, each observed by TenancyWorld.
 */
trait TenancySuite
{
    use TenancyWorld;

    /** @var array<string, string> B before the pass */
    protected array $bBefore = [];

    /** @var array<string, array<string, int>> per prefix and actor: routes whose isolation the pass proved */
    public static array $covered = [];

    /** @var list<string> actors whose isolation the last routeFailures() proved */
    protected array $coveredActors = [];

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    protected function routeFailures(string $key, LaravelRoute $route, array $entry): array
    {
        [$method, $uri] = explode(' ', $key, 2);
        $middleware = $route->gatherMiddleware();
        $actors = in_array('auth:device', $middleware, true) ? ['device'] : CrossTenantRouteTest::SANCTUM_ACTORS;
        $ok = is_array($entry['ok']) ? $entry['ok'] : array_fill_keys($actors, $entry['ok']);
        if (array_diff($actors, array_keys($ok)) !== [] || array_diff(array_keys($ok), $actors) !== []) {
            return ["{$key}: `ok` must name exactly the actors " . implode(', ', $actors)];
        }

        $paramsOf = $entry['params'] ?? fn (array $s) => [];
        $bodyOf = $entry['body'] ?? fn (array $s) => [];
        $a = $this->world['A'];
        $b = $this->world['B'];
        $params = ['A' => $paramsOf($a), 'B' => $paramsOf($b)];
        $bodies = ['A' => $bodyOf($a), 'B' => $bodyOf($b)];
        preg_match_all('/\{(\w+)\??\}/', $uri, $names);
        if (array_diff($names[1], array_keys($params['A'])) !== []) {
            return ["{$key}: params must give " . implode(', ', $names[1])];
        }

        // Variants with B's ids: all of them, then (when the route takes more than one) each one alone
        $variants = ['B ids' => [$params['B'], $bodies['B'], false]];
        $differing = [];
        foreach ($params['A'] as $name => $value) {
            if ($params['B'][$name] != $value) {
                $differing[] = ['param', $name];
            }
        }
        foreach ($bodies['A'] as $name => $value) {
            if (($bodies['B'][$name] ?? null) != $value) {
                $differing[] = ['body', $name];
            }
        }
        $softKeys = [...CrossTenantRouteTest::NOT_A_RECORD, ...($entry['soft'] ?? [])];
        // B's ids together are a store's record unless every differing key is one the route may ignore
        $variants['B ids'][2] = $differing !== [] && array_diff(array_column($differing, 1), $softKeys) === [];
        if (count($differing) > 1) {
            foreach ($differing as [$where, $name]) {
                $p = $params['A'];
                $q = $bodies['A'];
                if ($where === 'param') {
                    $p[$name] = $params['B'][$name];
                } else {
                    $q[$name] = $bodies['B'][$name];
                }
                $variants["B {$where} {$name}"] = [$p, $q, in_array($name, $softKeys, true)];
            }
        }
        // Every id the route's own request rules take (the API spec, as generated from the validation rules) that the
        // entry does not send with B's value is sent once on its own: an optional id the manifest left out is still
        // tried. A route may ignore it (soft: answered 2xx), never use it
        [$extra, $unclassified] = $this->optionalIds($method, $uri, $entry, $bodies);
        foreach ($unclassified as $path) {
            return ["{$key}: the request rules take `{$path}`: map it in TenancySuite::idKeys(), send it in the entry's body, or list it in the entry's `not_ref` (or TenancySuite::NOT_REF) with the reason"];
        }
        if ($differing === []) {
            // Nothing of a store to name (the caller's own till, shift, clock): A's call must still leave B alone
            $variants = [];
        }

        foreach ($extra as $path => $value) {
            $q = $bodies['A'];
            data_set($q, str_replace('*', '0', $path), $value);
            $variants["B extra {$path}"] = [$params['A'], $q, true];
        }

        $failures = [];
        $this->coveredActors = [];
        // The other people and terminals of A: the manifest names no answer for them (whatever A's own call gives
        // is theirs), they try B's ids all together, and a terminal's token must not pass a staff route at all
        $extras = $actors === ['device']
            ? array_values(array_diff(['till', 'kds', 'display'], [$entry['device'] ?? 'till']))
            : [...self::EXTRA_STAFF, 'terminal'];
        foreach ([...$actors, ...$extras] as $actor) {
            $extra = !in_array($actor, $actors, true);
            $token = $this->tokenOf($actor, $entry);
            $positive = $this->isolatedCall($method, self::fill($uri, $params['A']), $token, $bodies['A']);
            $expected = $extra ? [$positive['status']] : (array) $ok[$actor];
            $label = "{$key} as {$actor}";
            // M4 stage 2: a route with the `tenant` middleware (TenantMiddlewareCoverageTest: every tenant route but
            // NOT_BOUND) runs bound to A's store and to no other, from the middleware to the end of the controller and
            // again while a streamed body is written; any other route binds nothing, and a staff route refuses a
            // terminal's token before the middleware
            $tenant = $actor !== 'terminal' && in_array('tenant', $middleware, true) ? (int) $this->world['A']['store']->id : null;
            foreach (self::tenantFailures($positive, $tenant) as $problem) {
                $failures[] = "{$label}: {$problem}";
            }
            if ($actor === 'terminal' && $positive['status'] < 400) {
                $failures[] = "{$label}: a terminal's own token passes a staff route ({$positive['status']})";
            }
            $mapped = in_array($positive['status'], $expected, true);
            if (!$mapped) {
                $failures[] = "{$label} with A's ids: {$positive['status']}, manifest expects " . implode('|', $expected) . ': ' . Str::limit($positive['content'], 300);
            }
            if ($leak = $this->bLeakIn($positive['content'], $positive['request'])) {
                $failures[] = "{$label} with A's ids LEAKS B: {$leak}";
            }
            if ($positive['after'] != $this->bBefore) {
                $failures[] = "{$label} with A's ids CHANGES B in " . implode(', ', $this->changedTables($positive['after']));
            }
            foreach ($positive['side'] as $effect) {
                $failures[] = "{$label} with A's ids {$effect}";
            }
            $selfRefused = array_intersect($expected, [401, 403, 404]) === $expected || in_array($actor, $entry['self_refused'] ?? [], true)
                || ($extra && $positive['status'] >= 300);
            $covered = true;

            foreach ($extra ? array_slice($variants, 0, 1, true) : $variants as $variant => [$p, $q, $soft]) {
                $negative = $this->isolatedCall($method, self::fill($uri, $p), $token, $q);
                $where = "{$label} [{$variant}]";
                $answered = $negative['status'] >= 200 && $negative['status'] < 300;
                $refused = in_array($negative['status'], CrossTenantRouteTest::REFUSALS, true)
                    // A list filtered by B's id may answer with A's own (or no) rows: nothing of B is the check
                    || ($answered && (($entry['filter'] ?? false) || $soft))
                    // A batch (offline sync) answers 200 and refuses per operation
                    || ($answered && isset($entry['refused']) && ($entry['refused'])($negative['json']));
                if (!$refused) {
                    $failures[] = "{$where}: status {$negative['status']} (not a refusal): " . Str::limit($negative['content'], 300);
                    $covered = false;
                }
                // A log of exactly what the caller sent (the offline queue's rejected operation) names B's ids
                // because the caller did: allowed for the tables the entry lists with its reason, nothing else
                if (array_diff($this->changedTables($negative['after']), array_keys($entry['logs_request'] ?? [])) !== []) {
                    $failures[] = "{$where}: CHANGES B in " . implode(', ', $this->changedTables($negative['after'])) . " (status {$negative['status']})";
                    $covered = false;
                }
                if ($leak = $this->bLeakIn($negative['content'], $negative['request'])) {
                    $failures[] = "{$where}: LEAKS B: {$leak}";
                    $covered = false;
                }
                foreach ($negative['side'] as $effect) {
                    $failures[] = "{$where}: {$effect}";
                    $covered = false;
                }
                if ($mapped && !$selfRefused && !$answered && !$soft && self::signature($negative) === self::signature($positive)) {
                    $failures[] = "{$where}: same answer as with A's ids ({$negative['status']}): the mapping proves nothing: " . Str::limit($negative['content'], 200);
                    $covered = false;
                }
            }
            if ($covered && $mapped && !$selfRefused) {
                // Proved only when A's own call went through (2xx): a 409/422 about the record's state shows the
                // record was found, but the route's work never ran
                if ($positive['status'] < 300) {
                    $this->coveredActors[] = $actor;
                }
                $prefix = preg_match('#^api/v1/(\w+)#', $uri, $m) ? $m[1] : $uri;
                $as = $actor . ($variants === [] ? ' (no ids)' : '');
                self::$covered[$prefix][$as] = (self::$covered[$prefix][$as] ?? 0) + 1;
            }
        }

        return $failures;
    }

    /**
     * M4 stage 2: a call's record of the bound store (isolatedCall: `tenant`, `tenant_body`) against the store it must
     * run for (null: none). Never another store, whoever binds it. A route that must be bound is bound by the
     * middleware and is not unbound while a runAs is open (in the middle of the controller); its streamed body binds the
     * store again and runs no query unbound. A queued job is unbound by QueuedJobsRunUnbound and bound again after it:
     * that stretch is the job's, not the route's.
     *
     * @param  array{tenant: list<array{0: string, 1: int|null, 2: string|null}>, tenant_body: list<array{0: string, 1: int|null, 2: string|null}>|null}  $call
     * @return list<string>
     */
    private static function tenantFailures(array $call, ?int $expected): array
    {
        $failures = [];
        $for = $expected === null ? 'no store is expected' : "store {$expected} is expected";
        foreach (['tenant' => 'the request', 'tenant_body' => 'the streamed body'] as $part => $what) {
            if ($call[$part] === null) {
                continue;
            }
            $jobs = 0;
            $open = 0;
            $bound = false;
            foreach ($call[$part] as [$change, $store, $by]) {
                if ($store !== null && $store !== $expected) {
                    $failures[] = "{$what} is bound to store {$store} ({$change}), {$for}";
                }
                if ($by !== null) {
                    $jobs = max(0, $jobs + ($by === 'started' ? 1 : -1));
                } elseif ($jobs === 0 && $expected !== null) {
                    $open += ['runAs' => 1, 'restore' => -1][$change] ?? 0;
                    $bound = $bound || $store === $expected;
                    if ($store === null && $change === 'query') {
                        $failures[] = "{$what} runs a query unbound, {$for}";
                    } elseif ($store === null && $open > 0) {
                        $failures[] = "{$what} is unbound in the middle ({$change}), {$for}";
                    }
                }
            }
            if ($expected !== null && !$bound) {
                $failures[] = "{$what} never runs bound, {$for}";
            }
        }

        return array_values(array_unique($failures));
    }

    /**
     * Keys of the request rules that name no store's record, with the reason.
     */
    public const NOT_REF = [
        'category_id' => 'platform categories, shared by every store',
        'category_ids' => 'platform categories, shared by every store',
        'region_id' => 'platform reference data',
        'district_id' => 'platform reference data',
        'external_id' => 'the caller\'s own reference text',
        'transaction_id' => 'the bank terminal\'s transaction number (text)',
        'merchant_id' => 'a payment provider\'s merchant number (text)',
        'service_id' => 'a payment provider\'s service number (text)',
        'fiscal_kassa_id' => 'the fiscal provider\'s kassa number (text)',
        'op_id' => 'the till\'s own offline operation id',
    ];

    /**
     * B's value for an id key of the request rules (the last segment of its path), by the rule's type.
     *
     * @return array<string, callable(array<string, mixed>, ?string): mixed>
     */
    private static function idKeys(): array
    {
        $device = fn (array $s) => $s['till']['device']->uuid;
        $id = fn (string $key) => fn (array $s) => $s[$key]->id;

        return [
            'branch_id' => $id('branch'),
            'account_uuid' => fn (array $s) => $s['account']->uuid,
            'banquet_package_id' => $id('banquet_package'),
            'brand_id' => $id('brand'),
            'courier_id' => $id('courier'),
            'device_id' => $device,
            'device_uuid' => $device,
            'hub_device_id' => $device,
            'printing_device_id' => $device,
            'till_uuid' => $device,
            'dining_table_id' => $id('free_table'),
            'table_id' => $id('free_table'),
            'employee_id' => $id('cook'),
            'extra_id' => $id('banquet_extra'),
            'extra_table_ids' => fn (array $s) => [$s['table2']->id],
            'hall_id' => $id('hall'),
            'hall_ids' => fn (array $s) => [$s['banquet_hall']->id],
            'item_ids' => fn (array $s) => [$s['item']->id],
            'order_item_id' => $id('item'),
            'line_uuid' => fn (array $s) => $s['banquet_line']->uuid,
            'modifier_group_id' => $id('modifier_group'),
            'modifier_id' => $id('modifier'),
            'option_id' => fn (array $s) => $s['combo_option']->uuid,
            'payment_uuid' => fn (array $s) => $s['payment']->uuid,
            'shift_uuid' => fn (array $s) => $s['shift']->uuid,
            'source_uuid' => fn (array $s) => $s['order2']->uuid,
            'product_id' => $id('product'),
            'product_ids' => fn (array $s) => [$s['product']->id],
            'product_variant_id' => $id('variant'),
            'product_variant_uuid' => fn (array $s) => $s['variant']->uuid,
            'variant_id' => fn (array $s, ?string $type) => $type === 'string' ? $s['variant']->uuid : $s['variant']->id,
            'staff_id' => $id('waiter'),
            'tip_staff_id' => $id('waiter'),
            'waiter_id' => $id('waiter'),
            'user_id' => $id('waiter'),
            'user_ids' => fn (array $s) => [$s['waiter']->id],
            'station_id' => $id('station'),
            'subject_id' => $id('paid_order'),
            'store_id' => fn (array $s) => (string) $s['store']->id,
        ];
    }

    /** @var array<string, list<array{0: string, 1: string|null}>>|null method + spec path => [key path, type] */
    private static ?array $specKeys = null;

    /**
     * The id keys of the route's request rules the entry does not already send with B's value: B's value for each one
     * mapped, and the keys neither mapped nor listed as not a reference.
     *
     * @param  array<string, mixed>  $entry
     * @param  array{A: array<string, mixed>, B: array<string, mixed>}  $bodies
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function optionalIds(string $method, string $uri, array $entry, array $bodies): array
    {
        $spec = self::specKeys();
        $pattern = preg_replace('/\\\{\w+\\\}/', '\{[^}]+\}', preg_quote(substr($uri, 3), '#'));
        $keys = [];
        foreach ($spec as $at => $list) {
            [$specMethod, $path] = explode(' ', $at, 2);
            if ($specMethod === $method && preg_match('#^' . $pattern . '$#', $path)) {
                $keys = $list;
            }
        }
        $map = self::idKeys();
        $extra = [];
        $unclassified = [];
        foreach ($keys as [$path, $type]) {
            $name = substr($path, (int) strrpos('.' . $path, '.'));
            $at = str_replace('*', '0', $path);
            if (isset(self::NOT_REF[$name]) || isset($entry['not_ref'][$path]) || isset($entry['not_ref'][$name])) {
                continue;
            }
            if (data_get($bodies['A'], $at) !== null && data_get($bodies['A'], $at) != data_get($bodies['B'], $at)) {
                continue; // sent with each side's own value already
            }
            if (!isset($map[$name])) {
                $unclassified[] = $path;

                continue;
            }
            $extra[$path] = ($map[$name])($this->world['B'], $type);
        }

        return [$extra, $unclassified];
    }

    /**
     * @return array<string, list<array{0: string, 1: string|null}>>
     */
    private static function specKeys(): array
    {
        if (self::$specKeys !== null) {
            return self::$specKeys;
        }
        $spec = json_decode((string) file_get_contents(base_path('../packages/api-spec/openapi.json')), true);
        $schemas = $spec['components']['schemas'] ?? [];
        $resolve = function (array $schema) use ($schemas): array {
            for ($i = 0; $i < 10 && isset($schema['$ref']); $i++) {
                $schema = $schemas[substr($schema['$ref'], strrpos($schema['$ref'], '/') + 1)] ?? [];
            }

            return $schema;
        };
        $type = function (array $schema) use (&$type, $resolve): ?string {
            $t = $schema['type'] ?? null;
            if (is_array($t)) {
                $t = array_values(array_diff($t, ['null']))[0] ?? null;
            }
            if ($t === null && isset($schema['anyOf'][0])) {
                $t = $type($resolve($schema['anyOf'][0]));
            }

            return $t;
        };
        $walk = function (array $schema, string $prefix, int $depth) use (&$walk, $resolve, $type): array {
            $out = [];
            foreach (($resolve($schema)['properties'] ?? []) as $name => $property) {
                $property = $resolve($property);
                $t = $type($property);
                $out[] = [$prefix . $name, $t];
                if ($depth < 3 && $t === 'array' && isset($property['items'])) {
                    $out = [...$out, ...$walk($property['items'], $prefix . $name . '.*.', $depth + 1)];
                } elseif ($depth < 3 && $t === 'object') {
                    $out = [...$out, ...$walk($property, $prefix . $name . '.', $depth + 1)];
                }
            }

            return $out;
        };
        $keys = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $fields = [];
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    if (($parameter['in'] ?? null) === 'query') {
                        $fields[] = [$parameter['name'], $type($parameter['schema'] ?? [])];
                    }
                }
                foreach ($operation['requestBody']['content'] ?? [] as $content) {
                    $fields = [...$fields, ...$walk($content['schema'] ?? [], '', 0)];
                    break;
                }
                $keys[strtoupper($method) . ' ' . $path] = array_values(array_filter($fields, fn ($field) => preg_match('/(_id|_ids|_uuid)$/', $field[0]) === 1));
            }
        }

        return self::$specKeys = $keys;
    }

    /**
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function changedTables(array $after): array
    {
        return array_keys(array_diff_assoc($after, $this->bBefore) + array_diff_key($this->bBefore, $after));
    }

    /** A's other staff, each with their own sign-in (the waiter's and courier's apps, the KDS cook, the hostess). */
    public const EXTRA_STAFF = ['waiter', 'cook', 'courier', 'hostess'];

    private function tokenOf(string $actor, array $entry): string
    {
        $side = $this->world['A'];

        return match ($actor) {
            'device' => $side[($entry['device'] ?? 'till') . '_token'],
            'terminal' => $side['till_token'],
            default => $side[$actor . '_token'],
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function fill(string $uri, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => rawurlencode((string) $params[$m[1]]), $uri);
    }

    /**
     * @param  array{status: int, json: mixed}  $call
     */
    private static function signature(array $call): string
    {
        $json = is_array($call['json']) ? $call['json'] : [];
        $errors = is_array($json['errors'] ?? null) ? array_keys($json['errors']) : [];
        sort($errors);

        return $call['status'] . '|' . json_encode([$json['message'] ?? null, $json['code'] ?? null, is_array($json['data'] ?? null) ? ($json['data']['reason'] ?? null) : null, $errors]);
    }

}
