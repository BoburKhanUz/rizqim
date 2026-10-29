import { vi } from 'vitest';
import { ADMIN, type Call } from '@/test/helpers';
import type { Banner, Category, PromoCode, PromoCodeCard, PromoCodePage } from '@/lib/api/marketing';

// Builders for the Marketing tests: one realistic row each, overridden per test.

export const banner = (overrides: Partial<Banner> = {}): Banner => ({
  id: 1,
  title: 'Dekabr aksiyasi',
  image_url: 'https://rizqim.test/storage/promos/dekabr.png',
  image_preview: '/marketing/promos/1/image?v=1',
  store: { id: 12, name: 'Lazzat Oshxona' },
  product: null,
  is_active: true,
  starts_at: null,
  ends_at: null,
  sort_order: 1,
  status: 'live',
  status_label: "Ko'rinmoqda",
  created_at: '2026-10-20T10:00:00+05:00',
  updated_at: '2026-10-20T10:00:00+05:00',
  ...overrides,
});

export const bannerList = (rows: Banner[] = [banner(), banner({ id: 2, title: 'Yangi yil', sort_order: 2, store: null, status: 'scheduled', starts_at: '2026-12-25T09:00:00+05:00' })]) => ({
  data: rows,
  statuses: { live: "Ko'rinmoqda", scheduled: 'Rejalashtirilgan', ended: 'Tugagan', inactive: "O'chiq" },
});

export const code = (overrides: Partial<PromoCode> = {}): PromoCode => ({
  id: 1,
  code: 'YANGI10',
  type: 'percentage',
  type_label: 'Foiz',
  value: '10.00',
  min_order_amount: '50000.00',
  max_discount_amount: '8000.00',
  usage_limit: 100,
  per_user_limit: 1,
  store: null,
  is_active: true,
  expires_at: '2026-12-31T23:59:00+05:00',
  status: 'active',
  status_label: 'Faol',
  stats: { uses: 12, unique_customers: 9, discount_total: '84000.00', gmv: '1250000.00' },
  locked: true,
  created_at: '2026-10-01T10:00:00+05:00',
  updated_at: '2026-10-01T10:00:00+05:00',
  ...overrides,
});

export const codeCard = (overrides: Partial<PromoCodeCard> = {}): PromoCodeCard => ({
  ...code(),
  recent_orders: [{ uuid: 'ab12cd34-1111-4222-8333-444455556666', status: 'delivered', discount: '8000.00', total: '92000.00', created_at: '2026-10-27T13:00:00+05:00' }],
  ...overrides,
});

export const codePage = (rows: PromoCode[] = [code(), code({ id: 2, code: 'BEPUL5000', type: 'fixed', value: '5000.00', max_discount_amount: null, usage_limit: null, per_user_limit: null, locked: false, stats: { uses: 0, unique_customers: 0, discount_total: '0.00', gmv: '0.00' } })], overrides: Partial<PromoCodePage> = {}): PromoCodePage => ({
  data: rows,
  current_page: 1,
  last_page: 1,
  per_page: 30,
  total: rows.length,
  statuses: { active: 'Faol', inactive: "O'chirilgan", expired: 'Muddati tugagan', exhausted: 'Limit tugagan' },
  types: { percentage: 'Foiz', fixed: 'Belgilangan summa' },
  ...overrides,
});

export const category = (overrides: Partial<Category> = {}): Category => ({
  id: 1,
  uuid: 'c0000000-0000-4000-8000-000000000001',
  name: 'Taomlar',
  slug: 'taomlar',
  parent: null,
  image_url: null,
  image_preview: null,
  sort_order: 1,
  is_active: true,
  products_count: 14,
  children_count: 1,
  updated_at: '2026-10-20T10:00:00+05:00',
  ...overrides,
});

export const categoryList = (): Category[] => [
  category(),
  category({ id: 2, uuid: 'c0000000-0000-4000-8000-000000000002', name: 'Burgerlar', slug: 'burgerlar', parent: { id: 1, name: 'Taomlar' }, sort_order: 2, products_count: 6, children_count: 0 }),
  category({ id: 3, uuid: 'c0000000-0000-4000-8000-000000000003', name: 'Ichimliklar', slug: 'ichimliklar', sort_order: 3, products_count: 0, children_count: 0, is_active: false }),
];

// --- fetch ---------------------------------------------------------------------------------------------------------------

type Handler = (request: Request) => Response | Promise<Response>;

// Like the shared mockFetch (test/helpers), but a multipart/form-data body is read too: its fields, and each file as {name, size, type}
export function mockMarketingFetch(routes: Record<string, Handler>) {
  const calls: Call[] = [];
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const request = input instanceof Request ? input : new Request(new URL(String(input), 'http://localhost:3000'), init);
    const url = new URL(request.url);
    const method = request.method;
    let body: unknown = null;
    if (method !== 'GET') {
      if ((request.headers.get('content-type') ?? '').startsWith('multipart/form-data')) {
        const fields: Record<string, unknown> = {};
        for (const [name, value] of (await request.clone().formData()).entries()) fields[name] = typeof value === 'string' ? value : { name: value.name, size: value.size, type: value.type };
        body = fields;
      } else {
        const text = await request.clone().text();
        body = text ? JSON.parse(text) : null;
      }
    }
    calls.push({ method, path: url.pathname.replace(ADMIN, ''), query: url.searchParams, body });
    const key = `${method} ${url.pathname.replace(ADMIN, '')}`;
    const handler = routes[key];
    if (!handler) throw new Error(`Unexpected request: ${key}`);
    return handler(request);
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, calls, callsTo: (method: string, path: string) => calls.filter((call) => call.method === method && call.path === path) };
}
