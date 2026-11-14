// RIZQIM scale test load driver (Node 22, no dependencies).
//   node load.mjs --tokens tokens.json --cfg cfg.json --duration 300 --out results/run.json [--label name]
// cfg keys (each optional; a scenario runs when its key is present):
//   lunch   {stores, gap_s:[a,b], think_s:[a,b], hall_pct}      POS lunch flow per till: open -> round -> send -> bill -> cash -> close
//   poll    {stores, till_s, kds_s, display_s, web_s}            polling fleet as the apps do with Reverb off (15/15/5/15 s)
//   reports {owners, every_s, days}                              owner reports over `days`
//   market  {customers, think_s:[a,b], order_pct, start_s, end_s, ramp_s}  Mini App browsing (+ spike window)
//   sync    {tills, ops, at_s, spread_s}                         offline burst: each till uploads `ops` queued ops (<=50 per call)
//   hub     {stores, batch_s, orders_per_min}                    hub-first: one POST /pos/sync per store every batch_s, no polling
// Every POST carries a fresh Idempotency-Key. Stats: per-label latency percentiles, status counts, 10 s timeline of the
// till (POS) endpoints, CPU seconds of php and postgres processes, pg_stat_database deltas, pg_stat_activity samples.
import fs from 'fs';
import { exec } from 'child_process';

const args = Object.fromEntries(process.argv.slice(2).reduce((a, v, i, arr) => (v.startsWith('--') ? a.concat([[v.slice(2), arr[i + 1]]]) : a), []));
const T = JSON.parse(fs.readFileSync(args.tokens));
const cfg = JSON.parse(fs.existsSync(args.cfg || '') ? fs.readFileSync(args.cfg) : (args.cfg || '{}'));
const DURATION = +(args.duration || 120) * 1000;
const BASE = `http://127.0.0.1:${process.env.APP_PORT || 18500}/api/v1`;
const DB = process.env.DB_DATABASE || 'scale_big';
const t0 = Date.now();
const now = () => Date.now() - t0;
let S0 = 1e15; // scenario start (after setup)
const rel = () => now() - S0;
const running = () => rel() < DURATION;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rnd = (a, b) => a + Math.random() * (b - a);
const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
const sh = (cmd) => new Promise((res) => exec(cmd, { maxBuffer: 1 << 24 }, (e, out) => res(e ? '' : out.trim())));
const psql = (sql) => sh(`sudo -n -u postgres psql -X -At -d ${DB} -c "${sql.replace(/"/g, '\\"')}"`);

// ---------------------------------------------------------------- stats
const samples = []; // [t_ms, label, ms, status, group]
const errors = {};
async function call(group, label, method, path, token, body) {
  const s = performance.now();
  let status = 0, json = null;
  try {
    const res = await fetch(BASE + path, {
      method, signal: AbortSignal.timeout(60000),
      headers: { Authorization: 'Bearer ' + token, Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': crypto.randomUUID(), 'X-Rizqim-App': 'scale-test' },
      body: body ? JSON.stringify(body) : undefined,
    });
    status = res.status;
    const text = await res.text();
    try { json = JSON.parse(text); } catch { json = null; }
    if (status >= 400) {
      const k = `${label} ${status} ${(json?.data?.reason || json?.code || json?.message || text).toString().slice(0, 90)}`;
      errors[k] = (errors[k] || 0) + 1;
    }
  } catch (e) {
    status = -1; errors[`${label} EXC ${e.name}`] = (errors[`${label} EXC ${e.name}`] || 0) + 1;
  }
  samples.push([now(), label, performance.now() - s, status, group]);
  return { status, data: json?.data, json };
}
function pct(sorted, p) { return sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] : null; }
function summarize(list, seconds) {
  const by = {};
  for (const [, label, ms, status] of list) {
    const b = (by[label] ||= { lat: [], st: {} });
    b.lat.push(ms); b.st[status] = (b.st[status] || 0) + 1;
  }
  const out = {};
  for (const [label, b] of Object.entries(by).sort()) {
    b.lat.sort((x, y) => x - y);
    const n = b.lat.length, bad = Object.entries(b.st).filter(([s]) => +s >= 500 || +s < 0 || +s === 429).reduce((a, [, c]) => a + c, 0);
    out[label] = { n, rps: +(n / seconds).toFixed(2), p50: +pct(b.lat, 50).toFixed(1), p95: +pct(b.lat, 95).toFixed(1), p99: +pct(b.lat, 99).toFixed(1), max: +b.lat[n - 1].toFixed(1), err_pct: +((100 * bad) / n).toFixed(2), status: b.st };
  }
  return out;
}
async function cpuSnap() {
  const out = { php: 0, postgres: 0 };
  for (const pid of fs.readdirSync('/proc').filter((p) => /^\d+$/.test(p))) {
    try {
      const st = fs.readFileSync(`/proc/${pid}/stat`, 'utf8');
      const comm = st.slice(st.indexOf('(') + 1, st.lastIndexOf(')'));
      const f = st.slice(st.lastIndexOf(')') + 2).split(' ');
      const ticks = +f[11] + +f[12] + +f[13] + +f[14]; // utime stime cutime cstime
      if (comm.startsWith('php')) out.php += ticks; else if (comm.startsWith('postgres')) out.postgres += ticks;
    } catch {}
  }
  return out;
}
const dbStatSql = `select xact_commit+xact_rollback, tup_inserted, tup_updated, tup_deleted, tup_returned, tup_fetched, blks_hit, blks_read from pg_stat_database where datname='${DB}'`;
const activity = [];
async function sampleActivity() {
  while (running()) {
    const r = await psql(`select count(*), count(*) filter (where state='active'), count(*) filter (where wait_event_type='Lock'), count(*) filter (where state='idle in transaction') from pg_stat_activity where datname='${DB}'`);
    if (r) activity.push([now(), ...r.split('|').map(Number)]);
    await sleep(5000);
  }
}

// ---------------------------------------------------------------- tenants
const posStores = T.stores.filter((s) => s.kind !== 'market' && s.tills?.length);
const mktStores = T.stores.filter((s) => s.kind !== 'pos');
const take = (n, arr) => arr.slice(0, Math.min(n ?? arr.length, arr.length));
const items = (s, n) => Array.from({ length: n }, () => { const i = Math.floor(Math.random() * s.variants.length); return { variant_id: s.variants[i], quantity: Math.random() < 0.2 ? 2 : 1, _p: +s.prices[i] }; });
const clean = (its) => its.map(({ _p, ...x }) => x);
const total = (its) => its.reduce((a, x) => a + x._p * x.quantity, 0).toFixed(2);
const tillState = new Map(); // device_uuid -> open order uuid (for the order-page poll)

async function ensureShift(till) {
  const r = await call('setup', 'POST /pos/shifts', 'POST', '/pos/shifts', till.staff_token, { opening_cash: 100000 });
  if (r.status === 201) till.shift_uuid = r.data.uuid;
  else { const c = await call('setup', 'GET /pos/shifts/current', 'GET', '/pos/shifts/current', till.staff_token); till.shift_uuid = c.data?.shift?.uuid; }
}

// a) POS lunch peak: one till = one cashier taking orders one after another
async function lunchTill(s, till, ti, c) {
  const think = () => sleep(rnd(...(c.think_s || [3, 8])) * 1000);
  await sleep(rnd(0, (c.gap_s || [30, 60])[1]) * 1000);
  let k = 0;
  while (running()) {
    k++;
    const hall = Math.random() < (c.hall_pct ?? 0.6) && s.table_ids?.length;
    const body = hall
      ? { channel: 'pos_hall', table_id: s.table_ids[(ti * 3 + (k % 3)) % s.table_ids.length], guests_count: 2, items: clean(items(s, 2 + Math.floor(Math.random() * 3))) }
      : { channel: 'pos_takeaway', items: clean(items(s, 2 + Math.floor(Math.random() * 3))) };
    let r = await call('pos', 'POST /pos/orders', 'POST', '/pos/orders', till.staff_token, body);
    if (r.status !== 201) { await sleep(5000); continue; }
    const uuid = r.data.uuid; let v = r.data.version; tillState.set(till.device_uuid, uuid);
    await think();
    r = await call('pos', 'POST /pos/orders/:id/rounds', 'POST', `/pos/orders/${uuid}/rounds`, till.staff_token, { version: v, items: clean(items(s, 1 + Math.floor(Math.random() * 2))) });
    v = r.data?.version ?? v; await think();
    r = await call('pos', 'POST /pos/orders/:id/send', 'POST', `/pos/orders/${uuid}/send`, till.staff_token, { version: v });
    v = r.data?.version ?? v; await think();
    r = await call('pos', 'POST /pos/orders/:id/bills', 'POST', `/pos/orders/${uuid}/bills`, till.staff_token, { version: v, mode: 'single' });
    const bill = r.data?.bills?.[0];
    if (bill) {
      await think();
      r = await call('pos', 'POST /pos/bills/:id/payments', 'POST', `/pos/bills/${bill.uuid}/payments`, till.staff_token, { tenders: [{ tender: 'cash', amount: bill.total, tendered_amount: bill.total }] });
      v = r.data?.order?.version ?? v;
      await call('pos', 'POST /pos/orders/:id/close', 'POST', `/pos/orders/${uuid}/close`, till.staff_token, { version: v });
    }
    tillState.delete(till.device_uuid);
    await sleep(rnd(...(c.gap_s || [30, 60])) * 1000);
  }
}

// b) polling fleet (Reverb off): till screen, KDS board, customer display, owner's LiveOrders tab
async function every(sec, fn) { await sleep(rnd(0, sec) * 1000); while (running()) { const s = Date.now(); await fn(); await sleep(Math.max(0, sec * 1000 - (Date.now() - s))); } }
function pollStore(s, c) {
  const jobs = [];
  for (const till of s.tills) jobs.push(every(c.till_s ?? 15, () => {
    const open = tillState.get(till.device_uuid);
    return open ? call('poll', 'GET /pos/orders/:id', 'GET', `/pos/orders/${open}`, till.staff_token) : call('poll', 'GET /pos/floor', 'GET', '/pos/floor', till.staff_token);
  }));
  if (s.kds) jobs.push(every(c.kds_s ?? 15, () => call('poll', 'GET /staff/kitchen/tickets', 'GET', '/staff/kitchen/tickets', s.kds)));
  if (s.display) jobs.push(every(c.display_s ?? 5, () => call('poll', 'GET /pos/display/state', 'GET', '/pos/display/state', s.display)));
  if (s.owner && c.web_s) jobs.push(every(c.web_s, async () => {
    await call('poll', 'GET /staff/orders?status=new', 'GET', '/staff/orders?status=new', s.owner);
    await call('poll', 'GET /staff/orders', 'GET', '/staff/orders', s.owner);
  }));
  return jobs;
}

// c) owner reports
function ymd(d) { return new Date(d).toISOString().slice(0, 10); }
async function owner(s, c) {
  const to = ymd(Date.now()), from = ymd(Date.now() - (c.days || 30) * 86400000);
  await every(c.every_s || 30, async () => {
    await call('report', 'GET /owner/analytics?period=month', 'GET', '/owner/analytics?period=month', s.owner);
    await call('report', 'GET /owner/reports/sales', 'GET', `/owner/reports/sales?from=${from}&to=${to}&group_by=day`, s.owner);
    await call('report', 'GET /owner/reports/top-products', 'GET', `/owner/reports/top-products?from=${from}&to=${to}`, s.owner);
  });
}

// d) Mini App customers (browsing; order_pct of loops place a pickup order)
async function customer(token, i, c) {
  const start = (c.start_s || 0) * 1000 + (c.ramp_s ? (i / c.customers) * c.ramp_s * 1000 : rnd(0, 5000));
  const end = (c.end_s ? c.end_s * 1000 : DURATION);
  while (rel() < start) await sleep(200);
  const think = () => sleep(rnd(...(c.think_s || [3, 8])) * 1000);
  while (running() && rel() < end) {
    const s = pick(mktStores), lat = (+s.lat + rnd(-0.02, 0.02)).toFixed(5), lng = (+s.lng + rnd(-0.02, 0.02)).toFixed(5);
    await call('mkt', 'GET /stores?geo', 'GET', `/stores?lat=${lat}&lng=${lng}`, token); await think();
    if (!running() || rel() >= end) break;
    await call('mkt', 'GET /catalog?geo', 'GET', `/catalog?lat=${lat}&lng=${lng}`, token); await think();
    if (!running() || rel() >= end) break;
    await call('mkt', 'GET /catalog?store_id', 'GET', `/catalog?store_id=${s.store_id}`, token); await think();
    if (Math.random() * 100 < (c.order_pct ?? 2)) {
      await call('mkt', 'POST /orders', 'POST', '/orders', token, { store_id: s.store_id, type: 'pickup', branch_id: s.branch_id, payment_method: 'cash', items: clean(items(s, 2)) });
    }
    await call('mkt', 'GET /orders', 'GET', '/orders', token);
    await call('mkt', 'GET /notifications', 'GET', '/notifications', token); await think();
  }
}

// e/f) offline operations as the till's outbox builds them (pos_sync Operation): order.open(+items, send) -> bill.create -> bill.pay -> order.close
let seqBase = Math.floor(Date.now() / 1000) * 1000;
function buildOps(s, till, nOrders) {
  const ops = [];
  for (let k = 0; k < nOrders; k++) {
    const order = crypto.randomUUID(), bill = crypto.randomUUID();
    const raw = items(s, 2 + Math.floor(Math.random() * 3)); const amount = total(raw);
    const its = clean(raw).map((x) => ({ ...x, line_id: crypto.randomUUID() }));
    const at = new Date(Date.now() - rnd(60, 1800) * 1000).toISOString();
    const base = { staff_id: till.staff_id, occurred_at: at };
    ops.push({ ...base, op_id: crypto.randomUUID(), seq: ++seqBase, type: 'order.open', payload: { order, channel: 'pos_takeaway', items: its, send: true, menu_taken_at: new Date(Date.now() - 3600e3).toISOString() } });
    ops.push({ ...base, op_id: crypto.randomUUID(), seq: ++seqBase, type: 'bill.create', payload: { order, bill, expected_total: amount } });
    ops.push({ ...base, op_id: crypto.randomUUID(), seq: ++seqBase, type: 'bill.pay', payload: { order, bill, tenders: [{ tender: 'cash', amount }], shift_uuid: till.shift_uuid } });
    ops.push({ ...base, op_id: crypto.randomUUID(), seq: ++seqBase, type: 'order.close', payload: { order } });
  }
  return ops;
}
async function postOps(group, till, ops) {
  const res = [];
  for (let i = 0; i < ops.length; i += 50) {
    const r = await call(group, 'POST /pos/sync', 'POST', '/pos/sync', till.device_token, { sent_at: new Date().toISOString(), operations: ops.slice(i, i + 50) });
    res.push(r);
    for (const x of r.data?.results || []) syncOutcome[x.status + (x.reason ? ':' + x.reason : '')] = (syncOutcome[x.status + (x.reason ? ':' + x.reason : '')] || 0) + 1;
  }
  return res;
}
const syncOutcome = {};
async function syncBurst(s, till, c) {
  await sleep(((c.at_s || 10) + rnd(0, c.spread_s || 60)) * 1000);
  const t = performance.now();
  await postOps('sync', till, buildOps(s, till, Math.ceil((c.ops || 30) / 4)));
  await call('sync', 'GET /pos/floor (after sync)', 'GET', '/pos/floor', till.staff_token);
  await call('sync', 'GET /pos/orders (after sync)', 'GET', '/pos/orders', till.staff_token);
  drain.push(performance.now() - t);
}
const drain = [];
async function hubStore(s, c) {
  const hub = s.tills[0];
  let carry = 0;
  await sleep(rnd(0, c.batch_s || 30) * 1000);
  while (running()) {
    carry += ((c.orders_per_min || 2) * (c.batch_s || 30)) / 60;
    const n = Math.floor(carry); carry -= n;
    if (n > 0) await postOps('hub', hub, buildOps(s, hub, n));
    await sleep((c.batch_s || 30) * 1000);
  }
}


// probe: ONE client, sequential, the same requests on the same tenants at every profile (S -> M -> L latency growth)
async function probe(c) {
  const s = posStores.filter((x) => x.grp === 1).sort((a, b) => b.pos_per_day - a.pos_per_day)[0];
  const m = mktStores.filter((x) => x.grp === 1).sort((a, b) => b.mkt_per_day - a.mkt_per_day)[0];
  const till = s.tills[0], cust = T.customers[0];
  const to = ymd(Date.now()), from = ymd(Date.now() - 30 * 86400000);
  for (let i = 0; i < (c.reps || 15); i++) {
    const o = await call('probe', 'POST /pos/orders', 'POST', '/pos/orders', till.staff_token, { channel: 'pos_takeaway', items: clean(items(s, 3)) });
    const uuid = o.data?.uuid; let v = o.data?.version;
    const list = [
      ['GET /pos/floor', '/pos/floor', till.staff_token], ['GET /pos/orders/:id', `/pos/orders/${uuid}`, till.staff_token],
      ['GET /pos/orders', '/pos/orders', till.staff_token], ['GET /staff/kitchen/tickets', '/staff/kitchen/tickets', s.kds],
      ['GET /pos/display/state', '/pos/display/state', s.display], ['GET /staff/orders', '/staff/orders', s.owner],
      ['GET /staff/orders?status=new', '/staff/orders?status=new', s.owner], ['GET /notifications (owner)', '/notifications', s.owner],
      ['GET /owner/analytics?period=month', '/owner/analytics?period=month', s.owner],
      ['GET /owner/reports/sales', `/owner/reports/sales?from=${from}&to=${to}&group_by=day`, s.owner],
      ['GET /owner/reports/top-products', `/owner/reports/top-products?from=${from}&to=${to}`, s.owner],
      ['GET /stores?geo', `/stores?lat=${m.lat}&lng=${m.lng}`, cust], ['GET /catalog?geo', `/catalog?lat=${m.lat}&lng=${m.lng}`, cust],
      ['GET /catalog?store_id', `/catalog?store_id=${m.store_id}`, cust], ['GET /orders', '/orders', cust], ['GET /notifications', '/notifications', cust],
      ['GET /pos/sync/status', '/pos/sync/status', till.device_token],
    ];
    for (const [label, path, tok] of list) await call('probe', label, 'GET', path, tok);
    let r = await call('probe', 'POST /pos/orders/:id/send', 'POST', `/pos/orders/${uuid}/send`, till.staff_token, { version: v }); v = r.data?.version ?? v;
    r = await call('probe', 'POST /pos/orders/:id/bills', 'POST', `/pos/orders/${uuid}/bills`, till.staff_token, { version: v, mode: 'single' });
    const bill = r.data?.bills?.[0];
    if (bill) {
      r = await call('probe', 'POST /pos/bills/:id/payments', 'POST', `/pos/bills/${bill.uuid}/payments`, till.staff_token, { tenders: [{ tender: 'cash', amount: bill.total, tendered_amount: bill.total }] });
      await call('probe', 'POST /pos/orders/:id/close', 'POST', `/pos/orders/${uuid}/close`, till.staff_token, { version: r.data?.order?.version ?? v });
    }
  }
}

// ---------------------------------------------------------------- main
const cpu0 = await cpuSnap(); const db0 = (await psql(dbStatSql)).split('|').map(Number);
const lunchStores = take(cfg.lunch?.stores, posStores), pollStores = take(cfg.poll?.stores, posStores);
const needShift = new Set([...(cfg.lunch ? lunchStores : []), ...(cfg.sync || cfg.hub ? posStores : []), ...(cfg.probe ? [posStores.filter((x) => x.grp === 1).sort((a, b) => b.pos_per_day - a.pos_per_day)[0]] : [])]);
if (args.pgss) await psql('select pg_stat_statements_reset()');
// open a shift on every till that takes money (real API path), 6 at a time; not counted in the scenario stats window
const setup = [...needShift].flatMap((s) => s.tills);
for (let i = 0; i < setup.length; i += 6) await Promise.all(setup.slice(i, i + 6).map(ensureShift));
const setupMs = now();
samples.length = 0; for (const k of Object.keys(errors)) if (k.startsWith('POST /pos/shifts') || k.startsWith('GET /pos/shifts')) delete errors[k];
const shift0 = now(); S0 = shift0;
const jobs = [sampleActivity()];
if (cfg.lunch) lunchStores.forEach((s) => s.tills.forEach((t, i) => jobs.push(lunchTill(s, t, i, cfg.lunch))));
if (cfg.poll) pollStores.forEach((s) => jobs.push(...pollStore(s, cfg.poll)));
if (cfg.reports) take(cfg.reports.owners, posStores.concat(mktStores)).forEach((s) => jobs.push(owner(s, cfg.reports)));
if (cfg.market) take(cfg.market.customers, T.customers).forEach((tok, i) => jobs.push(customer(tok, i, cfg.market)));
if (cfg.sync) take(cfg.sync.tills, posStores.flatMap((s) => s.tills.map((t) => [s, t]))).forEach(([s, t]) => jobs.push(syncBurst(s, t, cfg.sync)));
if (cfg.hub) take(cfg.hub.stores, posStores).forEach((s) => jobs.push(hubStore(s, cfg.hub)));
if (cfg.probe) jobs.push(probe(cfg.probe).then(() => { S0 = -1e15; }));
await Promise.all(jobs);
const wall = (now() - shift0) / 1000;
const cpu1 = await cpuSnap(); const db1 = (await psql(dbStatSql)).split('|').map(Number);
const hz = 100;
const scen = samples.filter((x) => x[0] >= shift0);
const res = {
  label: args.label || '', cfg, wall_s: +wall.toFixed(1), setup_s: +(setupMs / 1000).toFixed(1),
  requests: scen.length, rps: +(scen.length / wall).toFixed(2),
  endpoints: summarize(scen, wall),
  by_group: Object.fromEntries(['pos', 'poll', 'report', 'mkt', 'sync', 'hub'].map((g) => [g, scen.filter((x) => x[4] === g).length]).filter(([, n]) => n)),
  errors, sync_outcomes: syncOutcome,
  sync_drain_ms: drain.length ? { n: drain.length, p50: pct(drain.sort((a, b) => a - b), 50), p95: pct(drain, 95), max: drain[drain.length - 1] } : undefined,
  cpu_s: { php: (cpu1.php - cpu0.php) / hz, postgres: (cpu1.postgres - cpu0.postgres) / hz },
  db: Object.fromEntries(['xacts', 'tup_ins', 'tup_upd', 'tup_del', 'tup_returned', 'tup_fetched', 'blks_hit', 'blks_read'].map((k, i) => [k, db1[i] - db0[i]])),
  activity: activity.length ? { samples: activity.length, max_conn: Math.max(...activity.map((a) => a[1])), max_active: Math.max(...activity.map((a) => a[2])), lock_wait_samples: activity.filter((a) => a[3] > 0).length, max_idle_in_tx: Math.max(...activity.map((a) => a[4])) } : undefined,
};
// spike phases (market window) for the POS endpoints
if (cfg.market?.start_s !== undefined) {
  const a = shift0 + cfg.market.start_s * 1000, b = shift0 + (cfg.market.end_s || DURATION / 1000) * 1000;
  const ph = { before: scen.filter((x) => x[0] < a), during: scen.filter((x) => x[0] >= a && x[0] < b), after: scen.filter((x) => x[0] >= b) };
  res.phases = {};
  for (const [k, list] of Object.entries(ph)) {
    const secs = Math.max(1, ((k === 'before' ? a - shift0 : k === 'during' ? b - a : shift0 + wall * 1000 - b)) / 1000);
    const pos = list.filter((x) => x[4] === 'pos' || x[4] === 'poll');
    const lat = pos.map((x) => x[2]).sort((p, q) => p - q);
    res.phases[k] = { secs: +secs.toFixed(0), total_rps: +(list.length / secs).toFixed(1), till_n: lat.length, till_p50: pct(lat, 50), till_p95: pct(lat, 95), till_p99: pct(lat, 99), endpoints: summarize(pos, secs) };
  }
}
if (args.pgss) {
  const q = (order) => `select round(total_exec_time::numeric,1) total_ms, calls, round(mean_exec_time::numeric,3) mean_ms, round(100*shared_blks_hit::numeric/nullif(shared_blks_hit+shared_blks_read,0),1) hit_pct, rows, left(regexp_replace(query, '\\s+', ' ', 'g'), 400) q from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%' order by ${order} desc limit 25`;
  fs.writeFileSync(args.out.replace(/\.json$/, '.pgss.txt'), `== by total time\n${await psql(q('total_exec_time'))}\n\n== by mean time (calls>=5)\n${await psql(q('mean_exec_time').replace('order by', 'and calls>=5 order by'))}\n\n== totals\n${await psql(`select sum(calls), round(sum(total_exec_time)::numeric) from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}')`)}\n`);
  res.pgss_calls_total = +(await psql(`select sum(calls) from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%'`));
  res.statements_per_request = +(res.pgss_calls_total / Math.max(1, res.requests)).toFixed(1);
}
fs.writeFileSync(args.out, JSON.stringify(res, null, 1));
const brief = Object.entries(res.endpoints).map(([k, v]) => `${k.padEnd(36)} n=${String(v.n).padStart(5)} rps=${String(v.rps).padStart(6)} p50=${v.p50} p95=${v.p95} p99=${v.p99} err%=${v.err_pct}`).join('\n');
console.log(`${res.label} wall=${res.wall_s}s req=${res.requests} rps=${res.rps} cpu php=${res.cpu_s.php}s pg=${res.cpu_s.postgres}s xacts=${res.db.xacts} conn_max=${res.activity?.max_conn} lockwaits=${res.activity?.lock_wait_samples}\n${brief}\nerrors: ${JSON.stringify(errors).slice(0, 1500)}\nsync: ${JSON.stringify(syncOutcome)}`);
process.exit(0);
