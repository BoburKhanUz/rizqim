// node logstats.mjs <php-fpm-access.log> <nginx-access.log> [wall_s] [pos_stores]  -> text report (M2 runs, fpm mode)
// php-fpm access log (server.sh adds `cpu=%{total}C` to the repo's format): requests per pool and status, and per
// endpoint and status the mean wall ms and the mean PHP CPU ms (FPM's %C x duration; clock-tick resolution, so a mean
// over many requests, not a single one). nginx access log (combined): wire bytes per endpoint and status (gzip included),
// and the bytes per POS store per minute of the non-market pools.
import fs from 'fs';
const [fpmLog, ngxLog, wallArg, storesArg] = process.argv.slice(2);
const wall = +(wallArg || 300), stores = +(storesArg || 33);
const norm = (uri) => {
  const [p, q = ''] = uri.split('?');
  const path = p.replace(/^\/api\/v1/, '').replace(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/g, ':id').replace(/\/\d+(?=\/|$)/g, '/:n');
  const keys = q ? q.split('&').map((kv) => { const [k, v] = kv.split('='); return ['status', 'scope', 'view', 'channel', 'page'].includes(k) ? `${k}=${k === 'page' ? (v === '1' ? '1' : 'n') : v}` : k; }).sort().join('&') : '';
  return path + (keys ? '?' + keys : '');
};
const lines = (f) => (f && fs.existsSync(f) ? fs.readFileSync(f, 'utf8').split('\n').filter(Boolean) : []);
const out = [];
// php-fpm
const pools = {}, ep = {};
for (const l of lines(fpmLog)) {
  const m = l.match(/^(\S+) .*?"(\S+) (\S+)" (\d+) ([\d.]+) ms(?: cpu=([\d.]+))?/);
  if (!m) continue;
  const [, pool, method, uri, st, ms, cpu] = m;
  pools[`${pool} ${st}`] = (pools[`${pool} ${st}`] || 0) + 1;
  const k = `${method} ${norm(uri)}`;
  const e = (ep[k] ||= { pool, st: {} });
  const s = (e.st[st] ||= { n: 0, ms: 0, cpu: 0 });
  s.n++; s.ms += +ms; s.cpu += ((+cpu || 0) * +ms) / 100;
}
out.push('== php-fpm access: requests by pool and status');
for (const [k, v] of Object.entries(pools).sort((a, b) => b[1] - a[1])) out.push(`${String(v).padStart(7)} ${k}`);
// nginx
const nb = {}; let posBytes = 0, mktBytes = 0, posReq = 0, mktReq = 0; const ngst = {};
const marketPath = /^\/api\/v1\/(stores|catalog|orders|notifications|addresses|locations|geocode|complaints)(\/|\?|$)/;
for (const l of lines(ngxLog)) {
  const m = l.match(/"(\S+) (\S+) [^"]*" (\d+) (\d+)/);
  if (!m) continue;
  const [, method, uri, st, bytes] = m;
  ngst[st] = (ngst[st] || 0) + 1;
  const k = `${method} ${norm(uri)}`;
  const e = (nb[k] ||= {});
  const s = (e[st] ||= { n: 0, b: 0 });
  s.n++; s.b += +bytes;
  const isMkt = method === 'GET' && marketPath.test(uri) && !/channel=pos_|branch_id=/.test(uri) || (method === 'POST' && /^\/api\/v1\/orders/.test(uri));
  if (isMkt) { mktBytes += +bytes; mktReq++; } else { posBytes += +bytes; posReq++; }
}
out.push('== nginx access: status counts');
for (const [k, v] of Object.entries(ngst).sort((a, b) => b[1] - a[1])) out.push(`${String(v).padStart(7)} ${k}`);
out.push('', '== per endpoint (php-fpm wall/CPU ms means; nginx wire bytes incl. gzip)');
out.push('| endpoint | pool | n | 200 | 304 | other | 304 % | wall ms 200 / 304 | PHP CPU ms 200 / 304 | bytes/resp 200 / 304 | kB total |');
out.push('|---|---|---|---|---|---|---|---|---|---|---|');
const f1 = (x) => (x === undefined ? '-' : x.toFixed(1));
for (const [k, e] of Object.entries(ep).sort((a, b) => Object.values(b[1].st).reduce((x, y) => x + y.n, 0) - Object.values(a[1].st).reduce((x, y) => x + y.n, 0))) {
  const n = Object.values(e.st).reduce((x, y) => x + y.n, 0), s2 = e.st['200'], s3 = e.st['304'];
  const other = n - (s2?.n || 0) - (s3?.n || 0);
  const g = nb[k] || {}; const kb = Object.values(g).reduce((x, y) => x + y.b, 0) / 1024;
  out.push(`| ${k} | ${e.pool} | ${n} | ${s2?.n || 0} | ${s3?.n || 0} | ${other} | ${(100 * (s3?.n || 0) / n).toFixed(1)} | ${f1(s2 && s2.ms / s2.n)} / ${f1(s3 && s3.ms / s3.n)} | ${f1(s2 && s2.cpu / s2.n)} / ${f1(s3 && s3.cpu / s3.n)} | ${g['200'] ? Math.round(g['200'].b / g['200'].n) : '-'} / ${g['304'] ? Math.round(g['304'].b / g['304'].n) : '-'} | ${kb.toFixed(0)} |`);
}
const tot = (pred) => Object.entries(ep).filter(([, e]) => pred(e)).reduce((a, [, e]) => { for (const [st, s] of Object.entries(e.st)) { a.n += s.n; a.cpu += s.cpu; if (st === '304') { a.n304 += s.n; a.cpu304 += s.cpu; } } return a; }, { n: 0, cpu: 0, n304: 0, cpu304: 0 });
for (const pool of ['critical', 'default', 'market']) { const t = tot((e) => e.pool === pool); if (t.n) out.push(`pool ${pool}: ${t.n} req, ${t.n304} x 304 (${(100 * t.n304 / t.n).toFixed(1)} %), PHP CPU from %C ${(t.cpu / 1000).toFixed(1)} s (${(t.cpu / t.n).toFixed(1)} ms/req; 304s ${(t.cpu304 / 1000).toFixed(1)} s)`); }
out.push(`bytes: non-market ${(posBytes / 1048576).toFixed(2)} MB in ${posReq} req = ${(posBytes / 1024 / stores / (wall / 60)).toFixed(1)} kB per POS store per minute (${stores} stores, ${wall} s); market ${(mktBytes / 1048576).toFixed(2)} MB in ${mktReq} req`);
console.log(out.join('\n'));
