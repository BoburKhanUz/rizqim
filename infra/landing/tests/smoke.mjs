#!/usr/bin/env node
// Browser smoke test (Playwright is NOT a dependency of this folder: it reuses the one in partner/web, or PLAYWRIGHT_REQUIRE_FROM).
//   node infra/landing/tests/smoke.mjs        (Chromium: PW_CHROMIUM_PATH if set, else Playwright's own)
// Serves infra/landing/site with tests/serve.mjs, mocks /api/v1 and checks both pages at 375 and 1440 px, the language
// switch, the two-step form and its states (field errors, sending, success, server 422, 429, network error + retry).
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import assert from 'node:assert/strict';
import { start } from './serve.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
const require = createRequire(process.env.PLAYWRIGHT_REQUIRE_FROM || join(root, 'partner/web/package.json'));
const { chromium } = require('playwright');

const srv = await start(0);
const base = `http://127.0.0.1:${srv.address().port}`;
const browser = await chromium.launch({ executablePath: process.env.PW_CHROMIUM_PATH || undefined });
let passed = 0;
const ok = (name) => { passed++; console.log('ok -', name); };
const REGIONS = { success: true, data: { regions: [{ id: 1, name: 'Toshkent shahri', is_active: true }, { id: 2, name: 'Samarqand', is_active: true }, { id: 9, name: 'Yopiq', is_active: false }], districts: [] } };

async function newPage(width, mock = {}) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await ctx.newPage();
  const problems = [];
  page.on('pageerror', (e) => problems.push(e.message));
  page.on('console', (m) => m.type() === 'error' && !/Failed to load resource/.test(m.text()) && problems.push(m.text()));
  const posts = [];
  await page.route('**/api/v1/locations', (r) => (mock.locations ? mock.locations(r) : r.fulfill({ json: REGIONS })));
  await page.route('**/api/v1/store-applications', (r) => { posts.push(JSON.parse(r.request().postData() || '{}')); return mock.apply ? mock.apply(r, posts.length) : r.fulfill({ status: 201, json: { success: true, data: { received: true } } }); });
  return { ctx, page, problems, posts };
}

// 1. both pages, both widths
for (const width of [375, 1440]) {
  for (const path of ['/', '/hamkor', '/maxfiylik', '/shartlar', '/no-such-page']) {
    const { ctx, page, problems } = await newPage(width);
    const resp = await page.goto(base + path);
    assert.equal(resp.status(), path === '/no-such-page' ? 404 : 200, path);
    await page.waitForSelector('html[data-ready]');
    const over = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    assert.ok(over <= 0, `${path} @${width}: horizontal overflow ${over}px`);
    assert.equal(await page.locator('h1').count(), 1, `${path}: one h1`);
    assert.deepEqual(problems, [], `${path}: console/page errors`);
    await ctx.close();
  }
  ok(`pages render without overflow or errors at ${width}px`);
}

// 2. language switch (landing) and persistence
{
  const { ctx, page } = await newPage(1440);
  await page.goto(base + '/');
  await page.waitForSelector('html[data-ready]');
  assert.equal(await page.locator('html').getAttribute('lang'), 'uz');
  assert.match(await page.locator('h1').innerText(), /Sevimli taomlaringiz/);
  await page.locator('.hdr-actions [data-lang="ru"]').click();
  assert.equal(await page.locator('html').getAttribute('lang'), 'ru');
  assert.match(await page.locator('h1').innerText(), /Любимые блюда/);
  assert.match(await page.title(), /Rizqim: заказ/);
  await page.goto(base + '/hamkor');
  await page.waitForSelector('html[data-ready]');
  assert.match(await page.locator('h1').innerText(), /Станьте партнёром/);
  // FAQ shows the neutral "soon" text while the owner has not given answers
  await page.locator('#faq summary').first().click();
  assert.match(await page.locator('#faq .ans').first().innerText(), /Скоро/);
  // tariffs block hidden without owner values
  assert.equal(await page.locator('#tariffs').isHidden(), true);
  await page.locator('.hdr-actions [data-lang="uz"]').click();
  assert.match(await page.locator('h1').innerText(), /hamkor bo'ling/);
  await ctx.close();
  ok('language switch uz/ru, persistence, FAQ "Tez orada", hidden tariffs');
}

// 3. mobile menu
{
  const { ctx, page } = await newPage(375);
  await page.goto(base + '/');
  await page.waitForSelector('html[data-ready]');
  assert.equal(await page.locator('#mobileNav').isVisible(), false);
  await page.locator('#menuBtn').click();
  assert.equal(await page.locator('#menuBtn').getAttribute('aria-expanded'), 'true');
  assert.equal(await page.locator('#mobileNav').isVisible(), true);
  await ctx.close();
  ok('mobile menu opens');
}

// 4. form
async function step1(page, name = 'Akmal Karimov', phone = '901234567') {
  await page.goto(base + '/hamkor');
  await page.waitForSelector('html[data-ready]');
  await page.fill('#f-owner_name', name);
  await page.fill('#f-phone', phone);
  await page.click('#nextBtn');
}
async function step2(page) {
  await page.fill('#f-name', 'Osh Markazi');
  await page.selectOption('#f-format', 'teahouse');
  await page.selectOption('#f-region', '2');
  await page.fill('#f-tin', '123456789');
  await page.check('#f-consent');
}
{
  const { ctx, page } = await newPage(1440);
  await page.goto(base + '/hamkor');
  await page.waitForSelector('html[data-ready]');
  // field errors on step 1
  await page.fill('#f-phone', '90 123 45');
  await page.click('#nextBtn');
  assert.match(await page.locator('#e-owner_name').innerText(), /Ismingizni kiriting/);
  assert.match(await page.locator('#e-phone').innerText(), /noto'g'ri/);
  assert.equal(await page.locator('#f-phone').getAttribute('aria-invalid'), 'true');
  // phone mask
  await page.fill('#f-phone', '');
  await page.type('#f-phone', '901234567');
  assert.equal(await page.inputValue('#f-phone'), '+998 90 123 45 67');
  await page.fill('#f-owner_name', 'Akmal Karimov');
  await page.click('#nextBtn');
  assert.equal(await page.locator('#f-name').isVisible(), true);
  // regions come from the API and skip inactive ones
  assert.deepEqual(await page.locator('#f-region option').allInnerTexts(), ['Tanlang', 'Toshkent shahri', 'Samarqand']);
  assert.equal(await page.locator('#f-format option').count(), 9);
  // step 2 errors incl. consent
  await page.click('#submitBtn');
  assert.match(await page.locator('#e-name').innerText(), /Joy nomini/);
  assert.match(await page.locator('#e-region').innerText(), /Viloyat/);
  assert.match(await page.locator('#e-consent').innerText(), /rozilik/);
  await ctx.close();
  ok('form: step 1/2 field errors, phone mask, regions from API');
}
{
  const { ctx, page, posts } = await newPage(375);
  await step1(page);
  await step2(page);
  await page.fill('#f-comment', 'Salom');
  await page.click('#submitBtn');
  await page.waitForSelector('#successArea:not([hidden])');
  assert.match(await page.locator('#successArea').innerText(), /Arizangiz qabul qilindi/);
  assert.equal(await page.locator('#formArea').isHidden(), true);
  assert.deepEqual(posts[0], { name: 'Osh Markazi', format: 'teahouse', owner_name: 'Akmal Karimov', phone: '+998901234567', source: 'website', website: '', region_id: 2, tin: '123456789', comment: 'Salom' });
  await ctx.close();
  ok('form: success screen and exact payload (source=website, honeypot empty, +998 phone)');
}
{
  let release;
  const gate = new Promise((r) => { release = r; });
  const { ctx, page } = await newPage(1440, { apply: async (r) => { await gate; return r.fulfill({ status: 201, json: { success: true } }); } });
  await step1(page); await step2(page);
  await page.click('#submitBtn');
  await page.waitForSelector('#submitBtn[disabled]');
  assert.match(await page.locator('#submitLabel').innerText(), /yuborilmoqda/);
  assert.equal(await page.locator('#submitBtn .spinner').count(), 1);
  release();
  await page.waitForSelector('#successArea:not([hidden])');
  await ctx.close();
  ok('form: sending state disables the button with a spinner');
}
{
  const { ctx, page } = await newPage(1440, { apply: (r) => r.fulfill({ status: 422, json: { success: false, message: 'x', errors: { phone: ["Telefon raqami +998 XX XXX XX XX ko'rinishida bo'lsin."], name: ['Nom noto\'g\'ri'] } } }) });
  await step1(page); await step2(page);
  await page.click('#submitBtn');
  await page.waitForSelector('#e-name:not(:empty)');
  assert.match(await page.locator('#e-name').innerText(), /Nom noto'g'ri/);
  assert.match(await page.locator('#e-phone').innerText(), /ko'rinishida/);
  assert.equal(await page.locator('#f-phone').isVisible(), true, 'goes back to step 1 for a step 1 field');
  await ctx.close();
  ok('form: server 422 messages shown per field');
}
{
  const { ctx, page } = await newPage(1440, { apply: (r) => r.fulfill({ status: 429, json: { message: 'Too many' } }) });
  await step1(page); await step2(page);
  await page.click('#submitBtn');
  await page.waitForSelector('#banner:not([hidden])');
  assert.match(await page.locator('#banner').innerText(), /Keyinroq qayta urinib ko'ring/);
  await ctx.close();
  ok('form: 429 shows "Keyinroq qayta urinib ko\'ring"');
}
{
  const { ctx, page, posts } = await newPage(375, { apply: (r, n) => (n === 1 ? r.abort('failed') : r.fulfill({ status: 201, json: { success: true } })) });
  await step1(page); await step2(page);
  await page.click('#submitBtn');
  await page.waitForSelector('#retryBtn');
  assert.match(await page.locator('#banner').innerText(), /Yuborib bo'lmadi/);
  await page.click('#retryBtn');
  await page.waitForSelector('#successArea:not([hidden])');
  assert.equal(posts.length, 2);
  await ctx.close();
  ok('form: network error with retry, then success');
}
{
  const { ctx, page, posts } = await newPage(1440, { locations: (r) => r.abort('failed') });
  await step1(page);
  await page.waitForSelector('#f-city:visible');
  await page.fill('#f-name', 'Kafe'); await page.fill('#f-city', 'Nukus'); await page.check('#f-consent');
  await page.click('#submitBtn');
  await page.waitForSelector('#successArea:not([hidden])');
  assert.equal(posts[0].city, 'Nukus'); assert.equal(posts[0].region_id, undefined);
  await ctx.close();
  ok('form: free-text city when /locations is unavailable');
}
await browser.close();
srv.close();
console.log(`${passed} groups passed`);
