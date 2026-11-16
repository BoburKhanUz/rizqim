<?php
// php report.php prof.jsonl [--sql] [--providers]  -- summary of the profiler's per-request lines (front.php):
// per route: requests, CPU ms (median, p25-p75), wall ms median, statements median, then the median of each phase and,
// with --providers, of each service provider's register+boot; with --sql, every statement shape and its count per request.
$file = $argv[1] ?? 'prof.jsonl';
$withSql = in_array('--sql', $argv, true);
$withProv = in_array('--providers', $argv, true);
$rows = array_map(fn ($l) => json_decode($l, true), array_filter(file($file, FILE_IGNORE_NEW_LINES)));
$med = function (array $a, float $p = 0.5) { if (!$a) return 0; sort($a); return $a[(int) floor($p * (count($a) - 1))]; };
$by = [];
foreach ($rows as $r) {
    $key = strtok($r['uri'], ' ').' '.($r['route'] ?? preg_replace('/[0-9a-f-]{36}/', ':id', $r['uri']));
    $by[$key][] = $r;
}
ksort($by);
printf("%-46s %5s %8s %15s %8s %6s %7s %6s\n", 'route', 'n', 'cpu_ms', 'cpu p25-p75', 'wall_ms', 'stmts', 'sql_ms', 'files');
$allPh = []; $allProv = [];
foreach ($by as $k => $list) {
    $cpu = array_column($list, 'cpu'); $wall = array_column($list, 'wall');
    $st = array_map(fn ($r) => isset($r['sql']) ? count($r['sql']) : 0, $list);
    $sqlms = array_map(fn ($r) => array_sum(array_column($r['sql'] ?? [], 1)), $list);
    printf("%-46s %5d %8.2f %7.2f-%-7.2f %8.2f %6d %7.2f %6d\n", substr($k, 0, 46), count($list), $med($cpu), $med($cpu, .25), $med($cpu, .75), $med($wall), $med($st), $med($sqlms), $med(array_column($list, 'files')));
    foreach ($list as $r) {
        foreach ($r['ph'] ?? [] as [$n, $w, $c]) { $allPh[$k][$n]['w'][] = $w; $allPh[$k][$n]['c'][] = $c; }
        $allPh[$k]['(autoload, inside phases)']['w'][] = $r['autoload_ms'] ?? 0; $allPh[$k]['(autoload, inside phases)']['c'][] = $r['autoload_n'] ?? 0;
        foreach ($r['prov'] ?? [] as $p => $t) { $allProv[$p][] = ($t['reg'] ?? 0) + ($t['boot'] ?? 0); }
    }
}
if ($allPh) {
    echo "\nphases (median wall ms / cpu ms; autoload row = ms / classes loaded)\n";
    foreach ($allPh as $k => $phs) {
        echo "  $k\n";
        foreach ($phs as $n => $v) printf("    %-28s %7.2f %7.2f\n", $n, $med($v['w']), $med($v['c']));
    }
}
if ($withProv && $allProv) {
    echo "\nservice providers, register+boot wall ms (median over all requests, top 40)\n";
    $m = array_map(fn ($v) => $med($v), $allProv); arsort($m);
    $tot = 0; foreach ($m as $v) $tot += $v;
    printf("  %-70s %7.3f\n", 'TOTAL ('.count($m).' providers)', $tot);
    foreach (array_slice($m, 0, 40, true) as $p => $v) printf("  %-70s %7.3f\n", $p, $v);
}
if ($withSql) {
    echo "\nstatements per request by shape (phase, count/request, mean ms)\n";
    foreach ($by as $k => $list) {
        $shapes = [];
        foreach ($list as $r) foreach ($r['sql'] ?? [] as [$ph, $ms, $q]) {
            $q = preg_replace(['/\$\d+|\?/', "/'[^']*'/", '/\b\d+\b/'], ['?', "'?'", 'N'], $q);
            $s = &$shapes["$ph|$q"]; $s['n'] = ($s['n'] ?? 0) + 1; $s['ms'] = ($s['ms'] ?? 0) + $ms; unset($s);
        }
        echo "  $k\n";
        uasort($shapes, fn ($a, $b) => $b['n'] <=> $a['n']);
        foreach ($shapes as $q => $s) { [$ph, $qq] = explode('|', $q, 2); printf("    %-10s %5.2f %6.3f  %s\n", $ph, $s['n'] / count($list), $s['ms'] / $s['n'], $qq); }
    }
}
