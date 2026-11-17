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
const samples = []; // [t_ms, label, ms, status, group, body_bytes, unconditional(0/1/2: 2 = conditional sent)]
const errors = {};
const retryAfter = {}; // "status Retry-After" -> count (503/429 answers, e.g. the fpm market limits)
// cc (M2 conditional client, optional): {etags: Map(path -> {etag, data, json, rev, scope}), uncond: bool}. A GET with a
// kept ETag sends If-None-Match (unless cc.uncond, the apps' periodic full read); a 304 returns the kept body, as the
// Flutter interceptor (packages/dart/rizqim_api sync/conditional_get.dart) and the browser HTTP cache do.
async function call(group, label, method, path, token, body, xff, cc) {
  const s = performance.now();
  let status = 0, json = null, bytes = 0, sentCond = 0, kept = null, rev = null, scope = null;
  try {
    const headers = { Authorization: 'Bearer ' + token, Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': crypto.randomUUID(), 'X-Rizqim-App': 'scale-test', ...(xff ? { 'X-Forwarded-For': xff } : {}) };
    if (cc && method === 'GET') {
      kept = cc.etags.get(path);
      if (kept && !cc.uncond) { headers['If-None-Match'] = kept.etag; sentCond = 2; } else sentCond = 1;
    }
    const res = await fetch(path.startsWith('http') ? path : BASE + path, {
      method, signal: AbortSignal.timeout(60000), headers,
      body: body ? JSON.stringify(body) : undefined,
    });
    status = res.status;
    if (status === 429 || status === 503) { const k = `${group} ${status} Retry-After=${res.headers.get('retry-after')}`; retryAfter[k] = (retryAfter[k] || 0) + 1; }
    const text = await res.text();
    bytes = Buffer.byteLength(text);
    if (status === 304 && kept) { json = kept.json; rev = kept.rev; scope = kept.scope; }
    else {
      try { json = JSON.parse(text); } catch { json = null; }
      rev = res.headers.get('x-revision'); scope = res.headers.get('x-revision-scope');
      if (cc && method === 'GET' && status === 200) {
        const etag = res.headers.get('etag');
        if (etag) cc.etags.set(path, { etag, json, rev, scope }); else cc.etags.delete(path);
      }
    }
    if (status >= 400) {
      const k = `${label} ${status} ${(json?.data?.reason || json?.code || json?.message || text).toString().slice(0, 90)}`;
      errors[k] = (errors[k] || 0) + 1;
    }
  } catch (e) {
    status = -1; errors[`${label} EXC ${e.name}`] = (errors[`${label} EXC ${e.name}`] || 0) + 1;
  }
  samples.push([now(), label, performance.now() - s, status, group, bytes, sentCond]);
  return { status, data: json?.data, json, rev, scope };
}
function pct(sorted, p) { return sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] : null; }
function summarize(list, seconds) {
  const by = {};
  for (const [, label, ms, status, , bytes, cond] of list) {
    const b = (by[label] ||= { lat: [], st: {}, bytes: 0, cond: 0, uncond: 0, lat304: [], lat200: [] });
    b.lat.push(ms); b.st[status] = (b.st[status] || 0) + 1; b.bytes += bytes || 0;
    if (cond === 2) b.cond++; else if (cond === 1) b.uncond++;
    if (status === 304) b.lat304.push(ms); else if (status === 200) b.lat200.push(ms);
  }
  const out = {};
  for (const [label, b] of Object.entries(by).sort()) {
    b.lat.sort((x, y) => x - y); b.lat304.sort((x, y) => x - y); b.lat200.sort((x, y) => x - y);
    const n = b.lat.length, bad = Object.entries(b.st).filter(([s]) => +s >= 500 || +s < 0 || +s === 429).reduce((a, [, c]) => a + c, 0);
    out[label] = { n, rps: +(n / seconds).toFixed(2), p50: +pct(b.lat, 50).toFixed(1), p95: +pct(b.lat, 95).toFixed(1), p99: +pct(b.lat, 99).toFixed(1), max: +b.lat[n - 1].toFixed(1), err_pct: +((100 * bad) / n).toFixed(2), status: b.st,
      pct304: +((100 * (b.st[304] || 0)) / n).toFixed(1), sent_inm: b.cond, sent_full: b.uncond, body_bytes: b.bytes,
      p50_200: b.lat200.length ? +pct(b.lat200, 50).toFixed(1) : null, p50_304: b.lat304.length ? +pct(b.lat304, 50).toFixed(1) : null };
  }
  return out;
}
async function cpuSnap() {
  // php = the web processes (php-fpm / artisan serve) as before; the M2 real-time processes (php too) are split out:
  // reverb (reverb:start), queue (queue:work: broadcasts), scheduler (schedule:work and its schedule:run children)
  const out = { php: 0, postgres: 0, pgbouncer: 0, nginx: 0, redis: 0, reverb: 0, queue: 0, scheduler: 0 };
  for (const pid of fs.readdirSync('/proc').filter((p) => /^\d+$/.test(p))) {
    try {
      const st = fs.readFileSync(`/proc/${pid}/stat`, 'utf8');
      const comm = st.slice(st.indexOf('(') + 1, st.lastIndexOf(')'));
      const f = st.slice(st.lastIndexOf(')') + 2).split(' ');
      const ticks = +f[11] + +f[12] + +f[13] + +f[14]; // utime stime cutime cstime
      if (comm.startsWith('php')) {
        const cmd = comm.startsWith('php-fpm') ? '' : fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8');
        if (cmd.includes('reverb:start')) out.reverb += ticks; else if (cmd.includes('queue:work')) out.queue += ticks;
        else if (cmd.includes('schedule:')) out.scheduler += ticks; else out.php += ticks;
      } else if (comm.startsWith('postgres')) out.postgres += ticks; else if (comm === 'pgbouncer') out.pgbouncer += ticks; else if (comm === 'nginx') out.nginx += ticks; else if (comm.startsWith('redis-server')) out.redis += ticks;
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
    const uuid = r.data.uuid; let v = r.data.version; tillState.set(till.device_uuid, uuid); simEvent(s, uuid);
    await think();
    r = await call('pos', 'POST /pos/orders/:id/rounds', 'POST', `/pos/orders/${uuid}/rounds`, till.staff_token, { version: v, items: clean(items(s, 1 + Math.floor(Math.random() * 2))) });
    v = r.data?.version ?? v; simEvent(s, uuid); await think();
    r = await call('pos', 'POST /pos/orders/:id/send', 'POST', `/pos/orders/${uuid}/send`, till.staff_token, { version: v });
    v = r.data?.version ?? v; simEvent(s, uuid); await think();
    r = await call('pos', 'POST /pos/orders/:id/bills', 'POST', `/pos/orders/${uuid}/bills`, till.staff_token, { version: v, mode: 'single' });
    const bill = r.data?.bills?.[0]; simEvent(s, uuid);
    if (bill) {
      await think();
      r = await call('pos', 'POST /pos/bills/:id/payments', 'POST', `/pos/bills/${bill.uuid}/payments`, till.staff_token, { tenders: [{ tender: 'cash', amount: bill.total, tendered_amount: bill.total }] });
      v = r.data?.order?.version ?? v; simEvent(s, uuid);
      await call('pos', 'POST /pos/orders/:id/close', 'POST', `/pos/orders/${uuid}/close`, till.staff_token, { version: v });
    }
    tillState.delete(till.device_uuid); simEvent(s, uuid);
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

// b2) M2 polling fleet (cfg.client.cond): each screen is a Dev, modelled on pos_data PosRefreshScheduler /
// KitchenBoardController / DisplayController and the partner web's react-query + browser cache:
//  - a periodic poll every `sec`; conditional (If-None-Match) except that when the poll time since the last full read
//    reaches cfg.client.unconditional_s (110: with 60 s polls every 2nd, with 15 s every 8th) the poll goes without it.
//    Browser screens (owner board) have no such full read (fetch always revalidates): unconditional_s = 0.
//  - an event on its channel (cfg.client.refresh: 'ws' = real Reverb sockets, 'sim' = one event per POS order write
//    of the branch, 50-250 ms after it): `rev` N+1 or unknown -> one debounced (300 ms) conditional read; rev == N -> none.
//  - one read at a time; asked again while reading -> one more read after it.
const devs = []; const devsByBranch = new Map(); const devsByStore = new Map();
const rt = { sockets: 0, subscribed: 0, auth_fail: 0, ws_errors: 0, ws_closed: 0, events: 0, heartbeats: 0, skipped_same: 0, event_reads: 0, heartbeat_reads: 0, poll_reads: 0, full_reads: 0 };
class Dev {
  constructor(group, kind, sec, read, { uncond_s, filter, scoped } = {}) {
    Object.assign(this, { group, kind, sec, read, uncond_s: uncond_s ?? 110, filter: filter || (() => true), scoped: scoped !== false });
    this.etags = new Map(); this.since = 0; this.nextFull = false; this.busy = false; this.pending = null; this.N = null; this.deb = null;
  }
  async trigger(reason) {
    if (!running()) return;
    if (this.busy) { this.pending = reason; return; }
    this.busy = true;
    try {
      let next = reason;
      while (next && running()) {
        const full = this.nextFull && next === 'poll';
        if (full) { this.nextFull = false; rt.full_reads++; }
        rt[next + '_reads'] = (rt[next + '_reads'] || 0) + 1;
        const r = await this.read({ etags: this.etags, uncond: full });
        this.N = this.scoped && r && r.status >= 200 && r.status < 400 && r.rev ? BigInt(r.rev) : null;
        next = this.pending; this.pending = null;
      }
    } finally { this.busy = false; }
  }
  start() {
    return (async () => {
      await sleep(rnd(0, this.sec) * 1000);
      while (running()) {
        const s = Date.now();
        this.since += this.sec;
        if (this.uncond_s > 0 && this.since >= this.uncond_s) { this.since = 0; this.nextFull = true; }
        await this.trigger('poll');
        await sleep(Math.max(0, this.sec * 1000 - (Date.now() - s)));
      }
    })();
  }
  event(data, chKey, name) {
    if (!this.filter(data, name)) return;
    const rev = data?.rev?.[chKey] !== undefined ? BigInt(data.rev[chKey]) : null;
    if (this.scoped && rev !== null && this.N !== null) {
      if (rev === this.N) { rt.skipped_same++; return; }
      if (rev === this.N + 1n) this.N = rev; else this.N = null;
    } else this.N = null;
    clearTimeout(this.deb); this.deb = setTimeout(() => this.trigger('event'), cfg.client?.debounce_ms ?? 300);
  }
  heartbeat(data, chKey) {
    const rev = data?.rev?.[chKey] !== undefined ? BigInt(data.rev[chKey]) : null;
    if (!this.scoped || rev === null || this.N === null || rev === this.N) return;
    this.N = null; this.trigger('heartbeat');
  }
}
// Which events re-read which screen (pos/apps: cashier floor_page.dart / order_page.dart, kds kitchen_board_controller.dart
// = every event, customer_display display_controller.dart = cart + queue events, partner web = order events)
const FLOOR_EVENTS = /^(order\.(created|status_changed|assigned|items_changed|bill_changed)|booking\.changed)$/;
const eventNames = {};
function pollStoreM2(s, c) {
  const reg = (d, key, map) => { devs.push(d); if (!map.has(key)) map.set(key, []); map.get(key).push(d); return d; };
  const uncond = cfg.client.unconditional_s ?? 110;
  for (const till of s.tills) {
    const d = new Dev('poll', 'till', c.till_s ?? 60, (cc) => {
      const open = tillState.get(till.device_uuid);
      return open ? call('poll', 'GET /pos/orders/:id', 'GET', `/pos/orders/${open}`, till.staff_token, undefined, undefined, cc) : call('poll', 'GET /pos/floor', 'GET', '/pos/floor', till.staff_token, undefined, undefined, cc);
    }, { uncond_s: uncond, filter: (data, name) => { const open = tillState.get(till.device_uuid); return open ? data?.order?.uuid === open : FLOOR_EVENTS.test(name); } });
    d.token = till.staff_token; d.channel = `private-pos-branch.${s.branch_id}`;
    reg(d, s.branch_id, devsByBranch);
  }
  if (s.kds) { const d = new Dev('poll', 'kds', c.kds_s ?? 15, (cc) => call('poll', 'GET /staff/kitchen/tickets', 'GET', '/staff/kitchen/tickets', s.kds, undefined, undefined, cc), { uncond_s: uncond }); d.token = s.kds; d.channel = `private-pos-branch.${s.branch_id}`; reg(d, s.branch_id, devsByBranch); }
  if (s.display) { const d = new Dev('poll', 'display', c.display_s ?? 30, (cc) => call('poll', 'GET /pos/display/state', 'GET', '/pos/display/state', s.display, undefined, undefined, cc), { uncond_s: uncond, filter: (data, name) => /^(display\.cart|queue\.)/.test(name) }); d.token = s.display; d.channel = `private-pos-branch.${s.branch_id}`; reg(d, s.branch_id, devsByBranch); }
  if (s.owner && c.web_s) {
    const d = new Dev('poll', 'owner', c.web_s, async (cc) => {
      await call('poll', 'GET /staff/orders?status=new', 'GET', '/staff/orders?status=new', s.owner, undefined, undefined, cc);
      return call('poll', 'GET /staff/orders', 'GET', '/staff/orders', s.owner, undefined, undefined, cc);
    }, { uncond_s: 0, scoped: false, filter: (data, name) => /^order\./.test(name) });
    d.token = s.owner; d.channel = `private-store.${s.store_id}`; reg(d, s.store_id, devsByStore);
  }
}
// 'sim' refresh: one event per POS write, to every screen of the branch and the store's owner board
function simEvent(s, uuid) {
  if (cfg.client?.refresh !== 'sim') return;
  for (const d of [...(devsByBranch.get(s.branch_id) || []), ...(devsByStore.get(s.store_id) || [])]) setTimeout(() => { rt.events++; d.event({ order: { uuid } }, null, 'order.status_changed'); }, rnd(50, 250));
}
// 'ws' refresh: a real Pusher-protocol socket per screen (Node 22 WebSocket), subscribed with POST /api/broadcasting/auth
const RV = { port: process.env.REVERB_PORT || 18580, key: process.env.REVERB_APP_KEY || '' };
function openSocket(d) {
  return new Promise((resolve) => {
    let done = false; const finish = (ok) => { if (!done) { done = true; resolve(ok); } };
    const ws = new WebSocket(`ws://127.0.0.1:${RV.port}/app/${RV.key}?protocol=7&client=js&version=8.4.0&flash=false`);
    const chKey = d.channel.replace(/^private-/, '');
    rt.sockets++;
    ws.onerror = () => { rt.ws_errors++; finish(false); };
    ws.onclose = () => { rt.ws_closed++; finish(false); };
    ws.onmessage = async (m) => {
      let msg; try { msg = JSON.parse(m.data); } catch { return; }
      if (msg.event === 'pusher:connection_established') {
        const sid = JSON.parse(msg.data).socket_id;
        const r = await call('rt', 'POST /broadcasting/auth', 'POST', `http://127.0.0.1:${process.env.APP_PORT || 18500}/api/broadcasting/auth`, d.token, { socket_id: sid, channel_name: d.channel });
        if (r.json?.auth) ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { auth: r.json.auth, channel: d.channel } })); else { rt.auth_fail++; finish(false); }
      } else if (msg.event === 'pusher:ping') ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
      else if (msg.event === 'pusher_internal:subscription_succeeded') { rt.subscribed++; finish(true); }
      else if (msg.channel === d.channel) {
        let data = msg.data; try { data = typeof data === 'string' ? JSON.parse(data) : data; if (typeof data === 'string') data = JSON.parse(data); } catch {}
        if (String(msg.event).includes('heartbeat')) { rt.heartbeats++; d.heartbeat(data, chKey); } else { rt.events++; eventNames[msg.event] = (eventNames[msg.event] || 0) + 1; d.event(data, chKey, String(msg.event)); }
      }
    };
    d.ws = ws; setTimeout(() => finish(false), 15000);
  });
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
  // Each customer its own client address (X-Forwarded-For from 127.0.0.1, a trusted proxy for nginx real_ip and Laravel), as
  // real customers arrive from many addresses; without it all 250 share 127.0.0.1 and nginx's per-address market limit
  // (NGINX_MARKET_CONN_PER_IP / IP_RATE) would shed what in production is spread over hundreds of addresses. XFF=0 disables.
  const xff = process.env.XFF === '0' ? undefined : `100.64.${(i >> 8) & 255}.${(i & 255) + 1}`;
  const get = (label, path) => call('mkt', label, 'GET', path, token, undefined, xff);
  while (running() && rel() < end) {
    const s = pick(mktStores), lat = (+s.lat + rnd(-0.02, 0.02)).toFixed(5), lng = (+s.lng + rnd(-0.02, 0.02)).toFixed(5);
    await get('GET /stores?geo', `/stores?lat=${lat}&lng=${lng}`); await think();
    if (!running() || rel() >= end) break;
    await get('GET /catalog?geo', `/catalog?lat=${lat}&lng=${lng}`); await think();
    if (!running() || rel() >= end) break;
    await get('GET /catalog?store_id', `/catalog?store_id=${s.store_id}`); await think();
    if (Math.random() * 100 < (c.order_pct ?? 2)) {
      await call('mkt', 'POST /orders', 'POST', '/orders', token, { store_id: s.store_id, type: 'pickup', branch_id: s.branch_id, payment_method: 'cash', items: clean(items(s, 2)) }, xff);
    }
    await get('GET /orders', '/orders');
    await get('GET /notifications', '/notifications'); await think();
  }
}

// d2) M2 Mini App customer (cfg.market.mode = 'm2'), modelled on customer/miniapp/src (hooks/useMarketplace.ts,
// useMenuAvailability.ts, useOrders.ts, useNotifications.ts, lib/polling.ts, pages/StoreDetail.tsx):
//  - one fixed address per customer (near a random market store), as a real user's saved location; the old mode moves it
//    every loop. Every GET goes through the customer's own ETag cache (the browser HTTP cache: always If-None-Match).
//  - home: GET /catalog?{loc}&view=cards&limit=50 and GET /stores?{loc}&page=1&per_page=10, each only when older than
//    its react-query staleTime (30 s / 60 s); scrolling: page 2 for scroll_pct (30 %), page 3 for a third of them.
//  - a store from the page: GET /catalog?store_id={uuid} (every open, as StoreDetail's useEffect) and
//    GET /catalog/availability?store_id={id} on open when older than 20 s, then every avail_s (20 s; 60 s when live)
//    while the customer stays on the store (dwell = 2-3 think times).
//  - background polls for the whole session: GET /notifications every notif_s (15 s), GET /orders?scope=active every
//    orders_s (60 s live, 10 s otherwise).
async function customerM2(token, i, c) {
  const start = (c.start_s || 0) * 1000 + (c.ramp_s ? (i / c.customers) * c.ramp_s * 1000 : rnd(0, 5000));
  const end = (c.end_s ? c.end_s * 1000 : DURATION);
  while (rel() < start) await sleep(200);
  const alive = () => running() && rel() < end;
  const think = () => sleep(rnd(...(c.think_s || [3, 8])) * 1000);
  const xff = process.env.XFF === '0' ? undefined : `100.64.${(i >> 8) & 255}.${(i & 255) + 1}`;
  const cc = { etags: new Map(), uncond: false };
  const last = new Map(); // react-query key -> last fetch (ms)
  const get = (label, path) => call('mkt', label, 'GET', path, token, undefined, xff, cc);
  const ifStale = async (key, staleMs, label, path) => { const t = last.get(key); if (t !== undefined && Date.now() - t < staleMs) return null; last.set(key, Date.now()); return get(label, path); };
  const home = pick(mktStores), loc = `lat=${(+home.lat + rnd(-0.02, 0.02)).toFixed(5)}&lng=${(+home.lng + rnd(-0.02, 0.02)).toFixed(5)}`;
  const bg = (sec, label, path) => (async () => { await sleep(rnd(0, sec) * 1000); while (alive()) { const s = Date.now(); await get(label, path); await sleep(Math.max(0, sec * 1000 - (Date.now() - s))); } })();
  const pollers = [bg(c.notif_s ?? 15, 'GET /notifications', '/notifications'), bg(c.orders_s ?? 60, 'GET /orders?scope=active', '/orders?scope=active')];
  let stores = [];
  while (alive()) {
    const [, st] = await Promise.all([
      ifStale('feed', 30000, 'GET /catalog?geo&view=cards', `/catalog?${loc}&view=cards&limit=50`),
      ifStale('stores1', 60000, 'GET /stores?geo&page', `/stores?${loc}&page=1&per_page=10`),
    ]);
    if (st?.data?.length) stores = st.data;
    await think(); if (!alive()) break;
    if (Math.random() * 100 < (c.scroll_pct ?? 30)) {
      const p2 = await ifStale('stores2', 60000, 'GET /stores?geo&page', `/stores?${loc}&page=2&per_page=10`);
      if (p2?.data?.length && Math.random() < 0.5) stores = stores.concat(p2.data);
      if (Math.random() < 1 / 3) await ifStale('stores3', 60000, 'GET /stores?geo&page', `/stores?${loc}&page=3&per_page=10`);
      await think(); if (!alive()) break;
    }
    const s = stores.length ? pick(stores) : { uuid: home.store_uuid, id: home.store_id };
    await Promise.all([get('GET /catalog?store_id', `/catalog?store_id=${s.uuid}`), ifStale('avail' + s.id, c.avail_s ? c.avail_s * 1000 : 20000, 'GET /catalog/availability', `/catalog/availability?store_id=${s.id}`)]);
    const dwellEnd = Date.now() + rnd(...(c.think_s || [3, 8])) * 1000 * (2 + Math.random());
    let nextAvail = Date.now() + (c.avail_s ?? 20) * 1000;
    while (alive() && Date.now() < dwellEnd) {
      await sleep(Math.max(0, Math.min(dwellEnd, nextAvail) - Date.now()));
      if (Date.now() >= nextAvail && alive()) { last.set('avail' + s.id, Date.now()); await get('GET /catalog/availability', `/catalog/availability?store_id=${s.id}`); nextAvail = Date.now() + (c.avail_s ?? 20) * 1000; }
    }
    if (!alive()) break;
    if (Math.random() * 100 < (c.order_pct ?? 2)) {
      const ms = mktStores.find((x) => x.store_id === s.id) || home;
      await call('mkt', 'POST /orders', 'POST', '/orders', token, { store_id: ms.store_id, type: 'pickup', branch_id: ms.branch_id, payment_method: 'cash', items: clean(items(ms, 2)) }, xff);
    }
    await think();
  }
  await Promise.all(pollers);
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

// Redis (the kit's instance, env.sh SCALE_REDIS_PORT): commands processed, network bytes, per-command counts
async function redisStats() {
  const out = await sh(`redis-cli -p ${process.env.SCALE_REDIS_PORT || 16379} info all`);
  const v = (k) => +(out.match(new RegExp(`^${k}:(\\d+)`, 'm'))?.[1] || 0);
  const by = {};
  for (const m of out.matchAll(/^cmdstat_([a-z|]+):calls=(\d+)/gm)) by[m[1]] = +m[2];
  return { cmds: v('total_commands_processed'), in: v('total_net_input_bytes'), out: v('total_net_output_bytes'), by };
}

// probe304: ONE client, sequential: for every polled GET, `reps` full reads (no If-None-Match: 200) then `reps`
// conditional reads of the same URL (304 while nothing changes), each batch measured on its own: statements
// (pg_stat_statements calls), PHP-FPM / Postgres / PgBouncer / Redis CPU ms per request (from /proc; the two psql reads per
// batch add their own Postgres time, about 1 ms per request at 40 reps) and p50 latency.
let probe304Out;
async function probe304(c) {
  const reps = c.reps || 40;
  const s = posStores.filter((x) => x.grp === 1).sort((a, b) => b.pos_per_day - a.pos_per_day)[0];
  const m = mktStores.filter((x) => x.grp === 1).sort((a, b) => b.mkt_per_day - a.mkt_per_day)[0];
  const till = s.tills[0], cust = T.customers[0];
  const o = await call('probe', 'POST /pos/orders', 'POST', '/pos/orders', till.staff_token, { channel: 'pos_takeaway', items: clean(items(s, 3)) });
  const loc = `lat=${m.lat}&lng=${m.lng}`;
  const list = [
    ['GET /pos/floor', '/pos/floor', till.staff_token], ['GET /pos/orders/:id', `/pos/orders/${o.data?.uuid}`, till.staff_token],
    ['GET /staff/kitchen/tickets', '/staff/kitchen/tickets', s.kds], ['GET /pos/display/state', '/pos/display/state', s.display],
    ['GET /staff/orders', '/staff/orders', s.owner], ['GET /staff/orders?status=new', '/staff/orders?status=new', s.owner],
    ['GET /notifications (owner)', '/notifications', s.owner], ['GET /notifications', '/notifications', cust], ['GET /orders?scope=active', '/orders?scope=active', cust],
    ['GET /stores?geo&page', `/stores?${loc}&page=1&per_page=10`, cust], ['GET /catalog?geo&view=cards', `/catalog?${loc}&view=cards&limit=50`, cust],
    ['GET /catalog?store_id', `/catalog?store_id=${m.store_uuid}`, cust], ['GET /catalog/availability', `/catalog/availability?store_id=${m.store_id}`, cust],
  ];
  const calls = async () => +(await psql(`select sum(calls) from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%'`));
  probe304Out = {};
  for (const [label, path, tok] of list) {
    const cc = { etags: new Map(), uncond: true };
    const row = {};
    for (const mode of ['200', '304']) {
      cc.uncond = mode === '200';
      await call('probe', label, 'GET', path, tok, undefined, undefined, cc); // warm (and the ETag for the 304 batch)
      await sleep(1000);
      const p0 = await calls(), c0 = await cpuSnap(); const lat = [], st = {};
      // market-pool paths share nginx's per-client 5 r/s + 20 burst: those go at ~4 r/s (the gap is idle time, not CPU)
      const gap = /^\/(stores|catalog|orders|notifications)/.test(path) ? 250 : 0;
      for (let k = 0; k < reps; k++) { if (gap) await sleep(gap); const t = performance.now(); const r = await call('probe', `${label} [${mode}]`, 'GET', path, tok, undefined, undefined, cc); lat.push(performance.now() - t); st[r.status] = (st[r.status] || 0) + 1; }
      const c1 = await cpuSnap(), p1 = await calls();
      lat.sort((a, b) => a - b);
      row[mode] = { status: st, stmts: +((p1 - p0) / reps).toFixed(1), php_ms: +(((c1.php - c0.php) * 10) / reps).toFixed(1), pg_ms: +(((c1.postgres - c0.postgres) * 10) / reps).toFixed(1), pgb_ms: +(((c1.pgbouncer - c0.pgbouncer) * 10) / reps).toFixed(2), redis_ms: +(((c1.redis - c0.redis) * 10) / reps).toFixed(2), p50: +pct(lat, 50).toFixed(1) };
    }
    probe304Out[label] = row;
  }
}

// ---------------------------------------------------------------- main
// Postgres CPU of the real-time processes' backends (role scale_rt, RT=1): by backend pid from pg_stat_activity
async function rtPgTicks() {
  const pids = (await psql(`select pid from pg_stat_activity where usename='scale_rt'`)).split('\n').filter(Boolean);
  const out = {};
  for (const pid of pids) try { const st = fs.readFileSync(`/proc/${pid}/stat`, 'utf8'); const f = st.slice(st.lastIndexOf(')') + 2).split(' '); out[pid] = +f[11] + +f[12]; } catch {}
  return out;
}
const rtPg0 = await rtPgTicks();
const cpu0 = await cpuSnap(); const db0 = (await psql(dbStatSql)).split('|').map(Number);
const lunchStores = take(cfg.lunch?.stores, posStores), pollStores = take(cfg.poll?.stores, posStores);
const needShift = new Set([...(cfg.lunch ? lunchStores : []), ...(cfg.sync || cfg.hub ? posStores : []), ...(cfg.probe || cfg.probe304 ? [posStores.filter((x) => x.grp === 1).sort((a, b) => b.pos_per_day - a.pos_per_day)[0]] : [])]);
if (args.pgss) await psql('select pg_stat_statements_reset()');
// open a shift on every till that takes money (real API path), 6 at a time; not counted in the scenario stats window
const setup = [...needShift].flatMap((s) => s.tills);
for (let i = 0; i < setup.length; i += 6) await Promise.all(setup.slice(i, i + 6).map(ensureShift));
// M2 screens: register them, open their sockets (cfg.client.refresh = 'ws') during setup, outside the stats window
if (cfg.poll && cfg.client?.cond) pollStores.forEach((s) => pollStoreM2(s, cfg.poll));
if (cfg.client?.refresh === 'ws') for (let i = 0; i < devs.length; i += 10) await Promise.all(devs.slice(i, i + 10).map(openSocket));
const rtSetup = { ...rt };
const redis0 = await redisStats();
const setupMs = now();
samples.length = 0; for (const k of Object.keys(errors)) if (k.startsWith('POST /pos/shifts') || k.startsWith('GET /pos/shifts')) delete errors[k];
const shift0 = now(); S0 = shift0;
const jobs = [sampleActivity()];
if (cfg.lunch) lunchStores.forEach((s) => s.tills.forEach((t, i) => jobs.push(lunchTill(s, t, i, cfg.lunch))));
if (cfg.poll && !cfg.client?.cond) pollStores.forEach((s) => jobs.push(...pollStore(s, cfg.poll)));
if (cfg.poll && cfg.client?.cond) devs.forEach((d) => jobs.push(d.start()));
if (cfg.reports) take(cfg.reports.owners, posStores.concat(mktStores)).forEach((s) => jobs.push(owner(s, cfg.reports)));
if (cfg.market) take(cfg.market.customers, T.customers).forEach((tok, i) => jobs.push((cfg.market.mode === 'm2' ? customerM2 : customer)(tok, i, cfg.market)));
if (cfg.probe304) jobs.push(probe304(cfg.probe304).then(() => { S0 = -1e15; }));
if (cfg.sync) take(cfg.sync.tills, posStores.flatMap((s) => s.tills.map((t) => [s, t]))).forEach(([s, t]) => jobs.push(syncBurst(s, t, cfg.sync)));
if (cfg.hub) take(cfg.hub.stores, posStores).forEach((s) => jobs.push(hubStore(s, cfg.hub)));
if (cfg.probe) jobs.push(probe(cfg.probe).then(() => { S0 = -1e15; }));
await Promise.all(jobs);
const wall = (now() - shift0) / 1000;
const redis1 = await redisStats();
const rtPg1 = await rtPgTicks();
const rtPgS = Object.entries(rtPg1).reduce((a, [pid, t]) => a + t - (rtPg0[pid] || 0), 0) / 100;
for (const d of devs) try { d.ws?.close(); } catch {}
const cpu1 = await cpuSnap(); const db1 = (await psql(dbStatSql)).split('|').map(Number);
const hz = 100;
const scen = samples.filter((x) => x[0] >= shift0);
const res = {
  label: args.label || '', cfg, wall_s: +wall.toFixed(1), setup_s: +(setupMs / 1000).toFixed(1),
  requests: scen.length, rps: +(scen.length / wall).toFixed(2),
  endpoints: summarize(scen, wall),
  by_group: Object.fromEntries(['pos', 'poll', 'report', 'mkt', 'sync', 'hub', 'rt'].map((g) => [g, scen.filter((x) => x[4] === g).length]).filter(([, n]) => n)),
  errors, retry_after: retryAfter, sync_outcomes: syncOutcome,
  sync_drain_ms: drain.length ? { n: drain.length, p50: pct(drain.sort((a, b) => a - b), 50), p95: pct(drain, 95), max: drain[drain.length - 1] } : undefined,
  cpu_s: Object.fromEntries(Object.keys(cpu1).map((k) => [k, +((cpu1[k] - cpu0[k]) / hz).toFixed(2)])),
  postgres_rt_s: rtPgS,
  redis: { commands: redis1.cmds - redis0.cmds, cmd_per_s: +((redis1.cmds - redis0.cmds) / wall).toFixed(1), net_in: redis1.in - redis0.in, net_out: redis1.out - redis0.out, top: Object.fromEntries(Object.entries(redis1.by).map(([k, v]) => [k, v - (redis0.by[k] || 0)]).filter(([, v]) => v > 0).sort((a, b) => b[1] - a[1]).slice(0, 12)) },
  realtime: cfg.client ? { event_names: eventNames, setup: rtSetup, run: Object.fromEntries(Object.entries(rt).map(([k, v]) => [k, v - (rtSetup[k] || 0)])), screens: devs.length } : undefined,
  probe304: probe304Out,
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
    const mkt = list.filter((x) => x[4] === 'mkt'), mst = {};
    for (const x of mkt) mst[x[3]] = (mst[x[3]] || 0) + 1;
    const mlat = mkt.filter((x) => x[3] >= 200 && x[3] < 300).map((x) => x[2]).sort((p, q) => p - q);
    res.phases[k] = { secs: +secs.toFixed(0), total_rps: +(list.length / secs).toFixed(1), till_n: lat.length, till_p50: pct(lat, 50), till_p95: pct(lat, 95), till_p99: pct(lat, 99), endpoints: summarize(pos, secs),
      mkt_status: mst, mkt_ok_p50: pct(mlat, 50), mkt_ok_p95: pct(mlat, 95) };
  }
}
if (args.pgss) {
  const q = (order) => `select round(total_exec_time::numeric,1) total_ms, calls, round(mean_exec_time::numeric,3) mean_ms, round(100*shared_blks_hit::numeric/nullif(shared_blks_hit+shared_blks_read,0),1) hit_pct, rows, left(regexp_replace(query, '\\s+', ' ', 'g'), 400) q from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%' order by ${order} desc limit 25`;
  fs.writeFileSync(args.out.replace(/\.json$/, '.pgss.txt'), `== by total time\n${await psql(q('total_exec_time'))}\n\n== by mean time (calls>=5)\n${await psql(q('mean_exec_time').replace('order by', 'and calls>=5 order by'))}\n\n== totals\n${await psql(`select sum(calls), round(sum(total_exec_time)::numeric) from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}')`)}\n`);
  res.pgss_calls_total = +(await psql(`select sum(calls) from pg_stat_statements where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%'`));
  // RT=1: the queue worker / Reverb / heartbeat connect as their own role (server.sh: scale_rt, direct as in compose),
  // so the web requests' statements are split from the broadcast jobs'
  res.pgss_by_role = Object.fromEntries((await psql(`select r.rolname, sum(calls), round(sum(total_exec_time)::numeric) from pg_stat_statements s join pg_roles r on r.oid=s.userid where dbid=(select oid from pg_database where datname='${DB}') and query not like '%pg_stat%' group by 1`)).split('\n').filter(Boolean).map((l) => { const [r, c, t] = l.split('|'); return [r, { calls: +c, ms: +t }]; }));
  const rtCalls = res.pgss_by_role.scale_rt?.calls || 0;
  res.statements_per_request = +((res.pgss_calls_total - rtCalls) / Math.max(1, res.requests)).toFixed(1);
  fs.appendFileSync(args.out.replace(/\.json$/, '.pgss.txt'), `\n== by role (scale_rt = queue worker, Reverb, heartbeat)\n${JSON.stringify(res.pgss_by_role)}\n\n== scale_rt (broadcast jobs) top statements\n${await psql(`select round(total_exec_time::numeric,1) total_ms, calls, round(mean_exec_time::numeric,3) mean_ms, left(regexp_replace(query, '\\s+', ' ', 'g'), 300) q from pg_stat_statements where userid=(select oid from pg_roles where rolname='scale_rt') and dbid=(select oid from pg_database where datname='${DB}') order by calls desc limit 12`)}\n`);
}
fs.writeFileSync(args.out, JSON.stringify(res, null, 1));
const brief = Object.entries(res.endpoints).map(([k, v]) => `${k.padEnd(36)} n=${String(v.n).padStart(5)} rps=${String(v.rps).padStart(6)} p50=${v.p50} p95=${v.p95} p99=${v.p99} err%=${v.err_pct}${v.sent_inm || v.status[304] ? ` 304%=${v.pct304} inm=${v.sent_inm} full=${v.sent_full} p50_200=${v.p50_200} p50_304=${v.p50_304}` : ''} kB=${Math.round(v.body_bytes / 1024)} st=${JSON.stringify(v.status)}`).join('\n');
console.log(`${res.label} wall=${res.wall_s}s req=${res.requests} rps=${res.rps} cpu php=${res.cpu_s.php}s pg=${res.cpu_s.postgres}s pgbouncer=${res.cpu_s.pgbouncer}s nginx=${res.cpu_s.nginx}s redis=${res.cpu_s.redis}s reverb=${res.cpu_s.reverb}s queue=${res.cpu_s.queue}s scheduler=${res.cpu_s.scheduler}s pg_rt=${res.postgres_rt_s}s xacts=${res.db.xacts} conn_max=${res.activity?.max_conn} lockwaits=${res.activity?.lock_wait_samples} stmts/req=${res.statements_per_request}\nby_group: ${JSON.stringify(res.by_group)} pgss_by_role: ${JSON.stringify(res.pgss_by_role)}\nredis: ${JSON.stringify(res.redis)}\nrealtime: ${JSON.stringify(res.realtime)}\n${brief}\nerrors: ${JSON.stringify(errors).slice(0, 1500)}\nretry_after: ${JSON.stringify(retryAfter)}\nsync: ${JSON.stringify(syncOutcome)}${res.phases ? `\nphases: ${JSON.stringify(Object.fromEntries(Object.entries(res.phases).map(([k, v]) => [k, { secs: v.secs, rps: v.total_rps, till_n: v.till_n, till_p50: v.till_p50 && +v.till_p50.toFixed(1), till_p95: v.till_p95 && +v.till_p95.toFixed(1), till_p99: v.till_p99 && +v.till_p99.toFixed(1), mkt_status: v.mkt_status, mkt_ok_p50: v.mkt_ok_p50 && +v.mkt_ok_p50.toFixed(1), mkt_ok_p95: v.mkt_ok_p95 && +v.mkt_ok_p95.toFixed(1) }])))}` : ''}${probe304Out ? `\nprobe304:\n${Object.entries(probe304Out).map(([k, v]) => `${k.padEnd(34)} ${JSON.stringify(v)}`).join('\n')}` : ''}`);
process.exit(0);
