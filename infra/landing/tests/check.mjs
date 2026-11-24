#!/usr/bin/env node
// rizqim.uz static checks, Node built-ins only (no dependencies).
//   node tests/check.mjs          validate (exit 1 on errors)
//   node tests/check.mjs --sync   first rewrite the default Uzbek text in the HTML from site/i18n/uz.js (the single source), then validate
// Checks: uz/ru dictionaries have the same keys, every data-i18n key exists, the HTML's Uzbek text equals uz.js, internal links,
// #anchors, ids, asset paths, sitemap, config keys used by data-cfg*, FAQ ids, no inline style/script (CSP), photo slots, fonts.
import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const SITE = join(dirname(fileURLToPath(import.meta.url)), '..', 'site');
const sync = process.argv.includes('--sync');
const errors = [];
const warn = [];
const err = (m) => errors.push(m);

function load(file) {
  const ctx = { window: {} };
  vm.createContext(ctx);
  vm.runInContext(readFileSync(join(SITE, file), 'utf8'), ctx, { filename: file });
  return ctx.window;
}
const dictWin = {};
load('i18n/uz.js'); // syntax check
const ctx = { window: {} };
vm.createContext(ctx);
for (const f of ['i18n/uz.js', 'i18n/ru.js']) vm.runInContext(readFileSync(join(SITE, f), 'utf8'), ctx, { filename: f });
const uz = ctx.window.RZ_I18N.uz, ru = ctx.window.RZ_I18N.ru;
const cfg = load('site.config.js').RZ_SITE;

for (const k of Object.keys(uz)) if (!(k in ru)) err(`ru.js missing key ${k}`);
for (const k of Object.keys(ru)) if (!(k in uz)) err(`uz.js missing key ${k}`);
for (const [k, v] of Object.entries(uz)) {
  if (typeof v !== 'string' || !v.trim()) err(`uz.${k} empty`);
  if (typeof ru[k] !== 'string' || !ru[k].trim()) err(`ru.${k} empty`);
  const tags = (s) => (String(s).match(/<\/?[a-z]+>/g) || []).join('');
  if (tags(v) !== tags(ru[k])) err(`key ${k}: html tags differ between uz and ru`);
}

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const escAttr = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
const pages = readdirSync(SITE).filter((f) => f.endsWith('.html'));
const used = new Set();
const ids = {};
const html = {};

for (const p of pages) {
  let src = readFileSync(join(SITE, p), 'utf8');
  const need = (k) => { used.add(k); if (!(k in uz)) { err(`${p}: unknown i18n key ${k}`); return null; } return uz[k]; };
  src = src.replace(/(<(\w+)\b[^>]*\bdata-i18n(-html)?="([^"]+)"[^>]*>)([\s\S]*?)(<\/\2>)/g, (m, open, tag, isHtml, key, inner, close) => {
    const v = need(key);
    if (v === null) return m;
    const next = isHtml ? v : esc(v);
    if (!sync && inner !== next) err(`${p}: text of ${key} differs from uz.js (run --sync)`);
    return open + next + close;
  });
  src = src.replace(/<(\w+)\b([^>]*\bdata-i18n-attr="([^"]*)"[^>]*)>/g, (m, tag, attrs, spec) => {
    let out = m;
    for (const pair of spec.split(',')) {
      const i = pair.indexOf(':');
      const name = pair.slice(0, i).trim(), key = pair.slice(i + 1).trim();
      const v = need(key);
      if (v === null) continue;
      const re = new RegExp(`(\\s${name}=")[^"]*(")`);
      if (re.test(out)) {
        const cur = out.match(re)[0];
        const next = out.replace(re, `$1${escAttr(v).replace(/\$/g, '$$$$')}$2`);
        if (!sync && cur !== next.match(re)[0]) err(`${p}: attribute ${name} of ${key} differs from uz.js (run --sync)`);
        out = next;
      } else out = out.replace(/\s*\/?>$/, ` ${name}="${escAttr(v)}">`);
    }
    return out;
  });
  if (sync) writeFileSync(join(SITE, p), src);
  html[p] = src;
  ids[p] = new Set([...src.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1]));
  const seen = new Set();
  for (const m of src.matchAll(/\sid="([^"]+)"/g)) { if (seen.has(m[1])) err(`${p}: duplicate id ${m[1]}`); seen.add(m[1]); }
  if (/\sstyle="/.test(src)) err(`${p}: inline style attribute (CSP style-src 'self')`);
  if (/<script(?![^>]*\bsrc=)[^>]*>/.test(src)) err(`${p}: inline script (CSP script-src 'self')`);
  if (!/<html lang="uz">/.test(src)) err(`${p}: <html lang="uz"> missing`);
  if (!/<title[^>]*>[^<]+<\/title>/.test(src)) err(`${p}: empty <title>`);
  if (p !== '404.html' && !/rel="canonical"/.test(src)) err(`${p}: canonical missing`);
}

const pageOf = { '/': 'index.html', '/hamkor': 'hamkor.html', '/maxfiylik': 'maxfiylik.html', '/shartlar': 'shartlar.html' };
function resolve(href) {
  const [path, hash] = href.split('#');
  const clean = path.split('?')[0];
  if (clean === '' && hash !== undefined) return { page: null, hash };
  if (pageOf[clean]) return { page: pageOf[clean], hash };
  if (existsSync(join(SITE, clean)) && clean !== '/') return { file: clean, hash };
  return null;
}
for (const [p, src] of Object.entries(html)) {
  for (const m of src.matchAll(/\s(?:href|src)="([^"]+)"/g)) {
    const h = m[1];
    if (/^(https?:|tel:|mailto:)/.test(h)) continue;
    const r = resolve(h);
    if (!r) { err(`${p}: broken link ${h}`); continue; }
    if (r.hash !== undefined && r.hash !== '' && r.hash.length) {
      const target = r.page ?? p;
      if (r.page !== undefined && !ids[target]?.has(r.hash)) err(`${p}: anchor ${h} has no id #${r.hash} in ${target}`);
    }
  }
  for (const m of src.matchAll(/aria-controls="([^"]+)"/g)) if (!ids[p].has(m[1])) err(`${p}: aria-controls ${m[1]} missing`);
  for (const m of src.matchAll(/\sfor="([^"]+)"/g)) if (!ids[p].has(m[1])) err(`${p}: label for=${m[1]} missing`);
  // config keys
  const get = (path) => path.split('.').reduce((o, k) => (o == null ? o : o[k]), cfg);
  for (const m of src.matchAll(/data-cfg(?:-href|-show|-host|-i18n|-mailto)?="([^"]+)"/g)) if (get(m[1]) === undefined) err(`${p}: config key ${m[1]} missing in site.config.js`);
  for (const m of src.matchAll(/data-faq-ans="([^"]+)"/g)) if (!cfg.faq || !(m[1] in cfg.faq)) err(`${p}: faq answer key ${m[1]} not in config faq`);
  for (const m of src.matchAll(/data-tariff="([^"]+)"/g)) if (!(m[1] in (cfg.tariffs || {}))) err(`${p}: tariff key ${m[1]} not in config`);
  if (!/<img\b[^>]*\balt=/.test(src) && /<img\b/.test(src)) err(`${p}: img without alt`);
}
// sprite symbols referenced exist
const sprite = readFileSync(join(SITE, 'img/icons.svg'), 'utf8');
for (const [p, src] of Object.entries(html)) for (const m of src.matchAll(/icons\.svg#(i-[\w]+[\w-]*)/g)) if (!sprite.includes(`id="${m[1]}"`)) err(`${p}: sprite symbol ${m[1]} missing`);
for (const f of ['js/form.js', 'js/app.js']) for (const m of readFileSync(join(SITE, f), 'utf8').matchAll(/icons\.svg#(i-[\w]+[\w-]*)/g)) if (!sprite.includes(`id="${m[1]}"`)) err(`${f}: sprite symbol ${m[1]} missing`);
// keys used from JS (t('...'), 'err.' + ..., 'fmt.' + ...)
const js = readFileSync(join(SITE, 'js/form.js'), 'utf8') + readFileSync(join(SITE, 'js/app.js'), 'utf8');
for (const m of js.matchAll(/RZ\.t\('([^']+)'\)/g)) { used.add(m[1]); if (!(m[1] in uz)) err(`form.js: unknown key ${m[1]}`); }
for (const k of Object.keys(uz)) if (/^(err|fmt)\./.test(k)) used.add(k);
['faq.soon', 'nav.close', 'nav.menu'].forEach((k) => used.add(k));
for (const k of Object.keys(uz)) if (!used.has(k)) warn.push(`unused dictionary key ${k}`);

// sitemap
const sm = readFileSync(join(SITE, 'sitemap.xml'), 'utf8');
for (const m of sm.matchAll(/<loc>([^<]+)<\/loc>/g)) { const path = m[1].replace(cfg.siteUrl, ''); if (!pageOf[path]) err(`sitemap: ${m[1]} is not a page`); }
if (!/Sitemap:/.test(readFileSync(join(SITE, 'robots.txt'), 'utf8'))) err('robots.txt: no Sitemap line');
for (const f of ['img/favicon.svg', 'img/logo.svg', 'img/logo-light.svg', 'img/logo-mark.svg']) if (!existsSync(join(SITE, f))) err(`missing ${f}`);

// photo slots: data-photo names exist in config photos and in img/photos/README.md; enabled ones need the file
const photoReadme = existsSync(join(SITE, 'img/photos/README.md')) ? readFileSync(join(SITE, 'img/photos/README.md'), 'utf8') : '';
if (!photoReadme) err('img/photos/README.md missing');
for (const [p, src] of Object.entries(html)) for (const m of src.matchAll(/<img\b[^>]*\bdata-photo="([^"]+)"[^>]*>/g)) {
  const name = m[1];
  if (!cfg.photos || !(name in cfg.photos)) err(`${p}: photo slot ${name} not in config photos`);
  if (!photoReadme.includes('`' + name + '.webp`')) err(`${p}: photo slot ${name} not listed in img/photos/README.md`);
  if (!/\bwidth="\d+"/.test(m[0]) || !/\bheight="\d+"/.test(m[0])) err(`${p}: photo slot ${name} needs width/height (fixed aspect box)`);
  if (cfg.photos?.[name] === true) for (const f of [name + '.webp', name + '-1x.webp']) if (!existsSync(join(SITE, 'img/photos', f))) err(`config photos.${name} is true but img/photos/${f} is missing`);
}
// self-hosted fonts referenced from the stylesheet exist, with their licence
const css = readFileSync(join(SITE, 'css/styles.css'), 'utf8');
for (const m of css.matchAll(/url\("?(\/fonts\/[^")]+)"?\)/g)) if (!existsSync(join(SITE, m[1]))) err(`styles.css: font ${m[1]} missing`);
for (const f of ['fonts/OFL-PlusJakartaSans.txt', 'fonts/OFL-Manrope.txt']) if (!existsSync(join(SITE, f))) err(`missing ${f}`);

// M1: no sample contacts may ship anywhere in site/ (owner supplies them in site.config.js)
function walk(d) { return readdirSync(d, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(join(d, e.name)) : [join(d, e.name)])); }
for (const f of walk(SITE)) {
  if (/\.(woff2|webp|png)$/.test(f)) continue;
  const t = readFileSync(f, 'utf8');
  for (const bad of ['90 123 45 67', '@rizqim']) if (t.includes(bad)) err(`${f.slice(SITE.length + 1)}: sample contact "${bad}" must not ship`);
}
// M2: unfinished legal drafts must stay out of the sitemap and carry noindex
for (const [p, src] of Object.entries(html)) {
  if (!src.includes('[Yurist')) continue;
  const slug = '/' + p.replace(/\.html$/, '');
  if (new RegExp('<loc>[^<]*' + slug + '</loc>').test(sm)) err(`sitemap: ${slug} contains "[Yurist" placeholders and must not be listed`);
  if (!/<meta name="robots" content="noindex">/.test(src)) err(`${p}: draft page without <meta name="robots" content="noindex">`);
}

warn.forEach((w) => console.log('warn:', w));
if (errors.length) { errors.forEach((e) => console.error('ERROR:', e)); console.error(`${errors.length} error(s)`); process.exit(1); }
console.log(`ok: ${pages.length} pages, ${Object.keys(uz).length} keys in uz and ru${sync ? ' (synced)' : ''}`);
