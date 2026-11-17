// node m2summary.mjs results/<run>.json ...  -> markdown tables of the M2 report (docs/scale/RESULTS_M2.md):
// per run the lunch costs (per POS order, per request, per store per minute), the 304 share of every polled endpoint,
// Redis / real-time figures and, for runs with a market spike, the till latency and market served/shed per phase.
import fs from 'fs';
const runs = process.argv.slice(2).map((f) => [f.replace(/.*\//, '').replace('.json', ''), JSON.parse(fs.readFileSync(f)), f]);
const POS_STORES = 33;
const sum = (o) => Object.values(o || {}).reduce((a, b) => a + b, 0);
const fpmLine = (f, re) => { const p = f.replace(/\.json$/, '.fpm.txt'); if (!fs.existsSync(p)) return null; const m = fs.readFileSync(p, 'utf8').match(re); return m ? m[1] : null; };
console.log('| run | req | req/s | POS-side req/store/min | orders | php / pg / pgb / redis / reverb+queue+hb CPU s | (php+pg) CPU per order s | php ms/req | pg+pgb ms/req | stmts/req | stmts/order | Redis cmd/s | kB/POS store/min | err (5xx/429/timeout) |');
console.log('|---|---|---|---|---|---|---|---|---|---|---|---|---|---|');
for (const [n, r, f] of runs) {
  const c = r.cpu_s, orders = r.endpoints['POST /pos/orders/:id/close']?.n || 0, min = r.wall_s / 60;
  const posReq = (r.by_group.pos || 0) + (r.by_group.poll || 0);
  const bad = Object.values(r.endpoints).reduce((a, e) => a + Object.entries(e.status).filter(([s]) => +s >= 500 || +s < 0 || +s === 429).reduce((x, [, k]) => x + k, 0), 0);
  const rtc = (c.reverb || 0) + (c.queue || 0) + (c.scheduler || 0);
  const kb = fpmLine(f, /= ([\d.]+) kB per POS store per minute/);
  const stm = r.pgss_calls_total - (r.pgss_by_role?.scale_rt?.calls || 0);
  console.log(`| ${n} | ${r.requests} | ${r.rps} | ${posReq ? (posReq / POS_STORES / min).toFixed(1) : '-'} | ${orders} | ${c.php.toFixed(0)} / ${c.postgres.toFixed(0)} / ${c.pgbouncer.toFixed(0)} / ${(c.redis || 0).toFixed(1)} / ${rtc.toFixed(1)} | ${orders ? ((c.php + c.postgres) / orders).toFixed(2) : '-'} | ${((c.php * 1000) / r.requests).toFixed(1)} | ${(((c.postgres + c.pgbouncer) * 1000) / r.requests).toFixed(1)} | ${r.statements_per_request} | ${orders ? (stm / orders).toFixed(0) : '-'} | ${r.redis?.cmd_per_s ?? '-'} | ${kb ?? '-'} | ${bad} |`);
}
// 304 share per polled endpoint
const polled = [...new Set(runs.flatMap(([, r]) => Object.entries(r.endpoints).filter(([k, e]) => k.startsWith('GET')).map(([k]) => k)))].sort();
console.log(`\n| 304 share (n) | ${runs.map(([n]) => n).join(' | ')} |`);
console.log(`|---|${runs.map(() => '---').join('|')}|`);
for (const k of polled) console.log(`| ${k} | ${runs.map(([, r]) => { const e = r.endpoints[k]; return e ? `${e.pct304} % (${e.n})` : '-'; }).join(' | ')} |`);
console.log(`| all GETs | ${runs.map(([, r]) => { let n = 0, m = 0; for (const [k, e] of Object.entries(r.endpoints)) if (k.startsWith('GET')) { n += e.n; m += e.status[304] || 0; } return n ? `${((100 * m) / n).toFixed(1)} % (${n})` : '-'; }).join(' | ')} |`);
// real-time
const rts = runs.filter(([, r]) => r.realtime);
if (rts.length) {
  console.log('\n| run | screens | sockets subscribed | events / heartbeats received | reads: poll / event / heartbeat | events skipped (rev == N) | full (no If-None-Match) polls |');
  console.log('|---|---|---|---|---|---|---|');
  for (const [n, r] of rts) { const s = r.realtime.setup, x = r.realtime.run; console.log(`| ${n} | ${r.realtime.screens} | ${s.subscribed}/${s.sockets} | ${x.events} / ${x.heartbeats} | ${x.poll_reads} / ${x.event_reads} / ${x.heartbeat_reads} | ${x.skipped_same} | ${x.full_reads} |`); }
}
// m2b (rules 'paced'): reads per screen and cause, and what happened to the events
const pz = runs.filter(([, r]) => r.realtime?.reads_by_kind);
if (pz.length) {
  console.log('\n| run (till model) | events asked / skipped (rev = R / already asked) / ignored | heartbeats asked | due reads dropped (covered) | return reads | reads by screen: cause n |');
  console.log('|---|---|---|---|---|---|');
  for (const [n, r] of pz) { const x = r.realtime.run; const k = Object.entries(r.realtime.reads_by_kind).map(([kind, o]) => `${kind}: ${Object.entries(o).map(([c, v]) => `${c} ${v}`).join(', ')}`).join('; '); console.log(`| ${n} (${r.realtime.till}) | ${x.asked} / ${x.skipped_same} / ${x.skipped_asked} / ${x.ignored} | ${x.heartbeat_asks} | ${x.dropped} | ${x.return_reads || 0} | ${k} |`); }
}
// phases
const ph = runs.filter(([, r]) => r.phases);
if (ph.length) {
  console.log('\n| run | till+poll p95 before / during / after (ms) | POST /pos/orders p95 (all) | market 2xx+304 during | shed 503 / 429 during | shed % | market ok p50 / p95 during (ms) |');
  console.log('|---|---|---|---|---|---|---|');
  for (const [n, r] of ph) {
    const d = r.phases.during, st = d.mkt_status || {}, ok = Object.entries(st).filter(([s]) => +s >= 200 && +s < 400).reduce((a, [, c]) => a + c, 0), all = sum(st);
    console.log(`| ${n} | ${Math.round(r.phases.before.till_p95)} / ${Math.round(d.till_p95)} / ${Math.round(r.phases.after.till_p95)} | ${Math.round(r.endpoints['POST /pos/orders']?.p95 || 0)} | ${ok} | ${st[503] || 0} / ${st[429] || 0} | ${all ? ((100 * ((st[503] || 0) + (st[429] || 0))) / all).toFixed(1) : '-'} | ${Math.round(d.mkt_ok_p50)} / ${Math.round(d.mkt_ok_p95)} |`);
  }
}
// market-only
const mk = runs.filter(([, r]) => r.by_group.mkt && !r.phases);
if (mk.length) {
  console.log('\n| run | market req | req/s | 2xx+304 | 503 / 429 | shed % | php / pg+pgb CPU s | php ms/req | stmts/req | p50 / p95 of each GET (ms) |');
  console.log('|---|---|---|---|---|---|---|---|---|---|');
  for (const [n, r] of mk) {
    let ok = 0, s503 = 0, s429 = 0, all = 0;
    for (const e of Object.values(r.endpoints)) for (const [s, c] of Object.entries(e.status)) { all += c; if (+s >= 200 && +s < 400) ok += c; if (+s === 503) s503 += c; if (+s === 429) s429 += c; }
    const gets = Object.entries(r.endpoints).filter(([k]) => k.startsWith('GET')).map(([k, e]) => `${k.replace('GET ', '')} ${Math.round(e.p50)}/${Math.round(e.p95)}`).join('; ');
    console.log(`| ${n} | ${r.requests} | ${r.rps} | ${ok} | ${s503} / ${s429} | ${((100 * (s503 + s429)) / all).toFixed(1)} | ${r.cpu_s.php.toFixed(0)} / ${(r.cpu_s.postgres + r.cpu_s.pgbouncer).toFixed(0)} | ${((r.cpu_s.php * 1000) / r.requests).toFixed(1)} | ${r.statements_per_request} | ${gets} |`);
  }
}
