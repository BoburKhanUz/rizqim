// @vitest-environment node
import { NextRequest } from 'next/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SESSION_COOKIE_NAME, forward } from './proxy';

// P4-31: what Marketing needs of the proxy: a picture goes up on the banner and category paths only (multipart), and the stored
// picture comes back as an image (so the page can show it from its own origin) on those two paths only.

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const PNG = new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10]);

afterEach(() => {
  vi.unstubAllGlobals();
});

function upload(path: string) {
  const form = new FormData();
  form.set('title', 'Dekabr aksiyasi');
  form.set('image', new Blob([PNG], { type: 'image/png' }), 'banner.png');
  return new NextRequest(`http://localhost:3100/api/backend/${path}`, {
    method: 'POST',
    headers: { Host: 'localhost:3100', Origin: 'http://localhost:3100', Cookie: `${SESSION_COOKIE_NAME}=tok` },
    body: form,
  });
}

const get = (path: string) => new NextRequest(`http://localhost:3100/api/backend/${path}`, { method: 'GET', headers: { Host: 'localhost:3100', Cookie: `${SESSION_COOKIE_NAME}=tok` } });

describe('admin proxy: Marketing files', () => {
  it('passes a multipart upload on to banners and categories, new or changed, and nowhere else under Marketing', async () => {
    const contentTypes: Array<string | null> = [];
    vi.stubGlobal(
      'fetch',
      vi.fn(async (_input: string | URL, init: RequestInit) => {
        contentTypes.push(new Headers(init.headers).get('Content-Type'));
        return json({ success: true, data: { id: 1 } }, 201);
      }),
    );

    for (const path of ['v1/admin/marketing/promos', 'v1/admin/marketing/promos/5', 'v1/admin/marketing/categories', 'v1/admin/marketing/categories/12']) {
      const response = await forward(upload(path), path.split('/'));
      expect(response.status, path).toBe(201);
    }
    expect(contentTypes).toHaveLength(4);
    expect(contentTypes.every((type) => /^multipart\/form-data; ?boundary=/.test(type ?? ''))).toBe(true);

    for (const path of ['v1/admin/marketing/promo-codes', 'v1/admin/marketing/promos/order', 'v1/admin/marketing/promos/5/image', 'v1/admin/marketing/lookup']) {
      expect((await forward(upload(path), path.split('/'))).status, path).toBe(415);
    }
    expect(contentTypes).toHaveLength(4);
  });

  it('passes the stored picture of a banner or a category on as an image, and no other answer on those paths', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(PNG, { status: 200, headers: { 'Content-Type': 'image/png', 'Cache-Control': 'private, max-age=300' } })));
    for (const path of ['v1/admin/marketing/promos/5/image', 'v1/admin/marketing/categories/12/image']) {
      const file = await forward(get(`${path}?v=3`), path.split('/'));
      expect(file.status, path).toBe(200);
      expect(file.headers.get('Content-Type')).toBe('image/png');
      expect(file.headers.get('X-Content-Type-Options')).toBe('nosniff');
      expect(new Uint8Array(await file.arrayBuffer())).toEqual(PNG);
    }

    // Not an image path: the bytes never pass
    const other = await forward(get('v1/admin/marketing/promo-codes/5/image'), ['v1', 'admin', 'marketing', 'promo-codes', '5', 'image']);
    expect((await other.json()).data.reason).toBe('bad_gateway');

    // An image path that answers with something else (an SVG with a script) is not passed on
    vi.stubGlobal('fetch', vi.fn(async () => new Response('<svg onload=alert(1)>', { status: 200, headers: { 'Content-Type': 'image/svg+xml' } })));
    const svg = await forward(get('v1/admin/marketing/promos/5/image'), ['v1', 'admin', 'marketing', 'promos', '5', 'image']);
    expect((await svg.json()).data.reason).toBe('bad_gateway');
  });

  it('answers a missing picture with the admin envelope, not an empty image', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ success: false, message: 'Rasm topilmadi.', code: 'no_image', data: { reason: 'no_image' } }, 404)));
    const response = await forward(get('v1/admin/marketing/promos/5/image'), ['v1', 'admin', 'marketing', 'promos', '5', 'image']);
    expect(response.status).toBe(404);
    expect((await response.json()).code).toBe('no_image');
  });
});
