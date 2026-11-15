// node summary.mjs results/L1.json ... -> one line per run (markdown) + cloud-first vs hub-first normalisation
import fs from 'fs';
const rows = process.argv.slice(2).map((f) => [f.replace(/.*\//, '').replace('.json', ''), JSON.parse(fs.readFileSync(f))]);
const ep = (r, k, f = 'p95') => (r.endpoints[k] ? Math.round(r.endpoints[k][f]) : '-');
console.log('| run | wall s | req | rps | by group | php CPU s | pg CPU s | stmts/req | xacts | max conn | lock-wait samples | err | POST /pos/orders p95 | payments p95 | floor p95 | kds p95 |');
console.log('|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|');
for (const [n, r] of rows) {
  const err = Object.values(r.errors).reduce((a, b) => a + b, 0);
  console.log(`| ${n} | ${r.wall_s} | ${r.requests} | ${r.rps} | ${Object.entries(r.by_group).map(([g, c]) => g + ':' + c).join(' ')} | ${r.cpu_s.php.toFixed(0)} | ${r.cpu_s.postgres.toFixed(0)} | ${r.statements_per_request ?? '-'} | ${r.db.xacts} | ${r.activity?.max_conn} | ${r.activity?.lock_wait_samples} | ${err} | ${ep(r, 'POST /pos/orders')} | ${ep(r, 'POST /pos/bills/:id/payments')} | ${ep(r, 'GET /pos/floor')} | ${ep(r, 'GET /staff/kitchen/tickets')} |`);
}
for (const [n, r] of rows) {
  const orders = r.endpoints['POST /pos/orders/:id/close']?.n || (r.sync_outcomes?.applied ? r.sync_outcomes.applied / 4 : 0);
  if (!orders) continue;
  const stores = 33, min = r.wall_s / 60;
  console.log(`${n}: orders=${orders} req/order=${(r.requests / orders).toFixed(1)} req/store/min=${(r.requests / stores / min).toFixed(1)} stmts/order=${((r.pgss_calls_total || 0) / orders).toFixed(0)} xacts/order=${(r.db.xacts / orders).toFixed(0)} cpu_ms/order php=${((r.cpu_s.php * 1000) / orders).toFixed(0)} pg=${((r.cpu_s.postgres * 1000) / orders).toFixed(0)} tup_ins/order=${(r.db.tup_ins / orders).toFixed(1)} tup_upd/order=${(r.db.tup_upd / orders).toFixed(1)} blks_hit/order=${(r.db.blks_hit / orders).toFixed(0)}`);
}
