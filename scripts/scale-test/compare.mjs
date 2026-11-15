// node compare.mjs results/a.json results/b.json ...  -> p50/p95 per endpoint side by side (markdown)
import fs from 'fs';
const runs = process.argv.slice(2).map((f) => [f.replace(/.*\//, '').replace('.json', ''), JSON.parse(fs.readFileSync(f))]);
const labels = [...new Set(runs.flatMap(([, r]) => Object.keys(r.endpoints)))].sort();
console.log(`| endpoint | ${runs.map(([n]) => `${n} p50 / p95`).join(' | ')} |`);
console.log(`|---|${runs.map(() => '---').join('|')}|`);
for (const l of labels) console.log(`| ${l} | ${runs.map(([, r]) => (r.endpoints[l] ? `${Math.round(r.endpoints[l].p50)} / ${Math.round(r.endpoints[l].p95)}` : '-')).join(' | ')} |`);
console.log(`| _cpu php/pg s, req, stmts/req_ | ${runs.map(([, r]) => `${r.cpu_s.php.toFixed(0)}/${r.cpu_s.postgres.toFixed(0)}, ${r.requests}, ${r.statements_per_request ?? '-'}`).join(' | ')} |`);
