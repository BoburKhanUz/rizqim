// Tiny static server for local runs and the smoke test (Node built-ins only): clean URLs like nginx.conf, 404.html for the rest.
// Usage: node tests/serve.mjs [port]   ->  http://127.0.0.1:8095
import { createServer } from 'node:http';
import { readFileSync, existsSync, statSync } from 'node:fs';
import { join, extname, dirname, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const SITE = join(dirname(fileURLToPath(import.meta.url)), '..', 'site');
const TYPES = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.txt': 'text/plain', '.xml': 'application/xml', '.json': 'application/json' };

export function start(port = 8095, host = '127.0.0.1') {
  const server = createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    let p = normalize(decodeURIComponent(url.pathname)).replace(/^(\.\.[/\\])+/, '');
    let file = join(SITE, p === '/' ? 'index.html' : p);
    if (!(existsSync(file) && statSync(file).isFile())) file = existsSync(file + '.html') ? file + '.html' : null;
    if (!file || !file.startsWith(SITE)) { res.writeHead(404, { 'Content-Type': TYPES['.html'] }); return res.end(readFileSync(join(SITE, '404.html'))); }
    res.writeHead(200, { 'Content-Type': TYPES[extname(file)] || 'application/octet-stream' });
    res.end(readFileSync(file));
  });
  return new Promise((resolve) => server.listen(port, host, () => resolve(server)));
}
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const port = Number(process.argv[2] || 8095);
  await start(port);
  console.log(`http://127.0.0.1:${port}`);
}
