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
        if ($differing === []) {
            // Nothing of a store to name (the caller's own till, shift, clock): A's call must still leave B alone
            $variants = [];
        }

        $failures = [];
        foreach ($actors as $actor) {
            $token = $this->tokenOf($actor, $entry);
            $expected = (array) $ok[$actor];
            $positive = $this->isolatedCall($method, self::fill($uri, $params['A']), $token, $bodies['A']);
            $label = "{$key} as {$actor}";
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
            $selfRefused = array_intersect($expected, [401, 403, 404]) === $expected || in_array($actor, $entry['self_refused'] ?? [], true);
            $covered = true;

            foreach ($variants as $variant => [$p, $q, $soft]) {
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
                $prefix = preg_match('#^api/v1/(\w+)#', $uri, $m) ? $m[1] : $uri;
                $as = $actor . ($variants === [] ? ' (no ids)' : '');
                self::$covered[$prefix][$as] = (self::$covered[$prefix][$as] ?? 0) + 1;
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function changedTables(array $after): array
    {
        return array_keys(array_diff_assoc($after, $this->bBefore) + array_diff_key($this->bBefore, $after));
    }

    private function tokenOf(string $actor, array $entry): string
    {
        $side = $this->world['A'];

        return match ($actor) {
            'owner' => $side['owner_token'],
            'manager' => $side['manager_token'],
            'cashier' => $side['cashier_token'],
            'device' => $side[($entry['device'] ?? 'till') . '_token'],
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
