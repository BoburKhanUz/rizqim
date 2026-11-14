import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, category, ok, permissionsAnswer, OWNER_PERMISSIONS, product } from '../menu/menuFixtures';
import { readableOn } from './BrandBadge';
import { BrandsView } from './BrandsView';
import { ChannelRevenueView } from './ChannelRevenueView';
import { IngredientsView } from './IngredientsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }), usePathname: () => '/brands' }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const brand = (over: Record<string, unknown> = {}) => ({
  id: 4,
  uuid: 'b-4',
  name: 'Burger Bro',
  short_code: 'BB',
  color: '#DC2626',
  logo_url: null,
  is_active: true,
  sort_order: 0,
  products_count: 1,
  channels: [{ channel: 'aggregator', is_hidden: false, price_percent: '15.00' }],
  off_branch_ids: [],
  ...over,
});

const ingredient = (over: Record<string, unknown> = {}) => ({
  id: 2,
  uuid: 'i-2',
  name: 'Tovuq',
  unit: 'kg',
  links: [
    { product_id: 1, name: 'Tovuq burger', is_critical: true },
    { product_id: 3, name: 'Salat', is_critical: false },
  ],
  stopped: [],
  stopped_here: false,
  ...over,
});

const figures = (over: Record<string, unknown> = {}) => ({ orders: 1, gross: '100000.00', discounts: '10000.00', refunds: '0.00', commission: '9000.00', net: '81000.00', ...over });

describe('Brendlar', () => {
  it('lists the brands, edits channels and moves dishes under a brand', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/brands': () => ok({ brands: [brand()], channels_available: ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'] }),
      'GET /api/v1/owner/products': () => json({ success: true, data: [product({ id: 1, name: 'Tovuq burger', brand_id: 4 }), product({ id: 2, name: "Sho'rva" })] }),
      'PUT /api/v1/owner/brands/4/channels': () => ok(brand()),
      'PUT /api/v1/owner/product-brands': () => ok({ changed: 1, brand_id: 4 }),
    });
    renderPage(<BrandsView />);
    const user = userEvent.setup();

    expect(await screen.findByRole('heading', { name: /Burger Bro/ })).toBeInTheDocument();
    expect(screen.getByText(/Hamma kanalda sotiladi · Narx: Agregator \+15%/)).toBeInTheDocument();
    const table = await screen.findByRole('table', { name: 'Taomlar va brendlar' });
    expect(within(table).getByText('Asosiy brend')).toBeInTheDocument();

    // Channels: not sold on Telegram, +10 % on the mobile app
    await user.click(screen.getByRole('button', { name: 'Kanallar va narx' }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('switch', { name: 'Telegramda sotiladi' }));
    await user.type(within(dialog).getByRole('textbox', { name: 'Mobil ilova: foiz' }), '10');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/owner/brands/4/channels'))).toBe(true));
    const put = calls.find((call) => call.method === 'PUT' && call.url.endsWith('/owner/brands/4/channels'))!;
    expect(await put.json()).toEqual({
      channels: [
        { channel: 'telegram', is_hidden: true, price_percent: null },
        { channel: 'mobile', is_hidden: false, price_percent: '10' },
        { channel: 'pos_hall', is_hidden: false, price_percent: null },
        { channel: 'pos_takeaway', is_hidden: false, price_percent: null },
        { channel: 'aggregator', is_hidden: false, price_percent: '15' },
      ],
    });
    expect(await screen.findByText('Kanallar saqlandi.')).toBeInTheDocument();

    // Move the soup under Burger Bro
    await user.click(screen.getByRole('checkbox', { name: "Sho'rva ni tanlash" }));
    await user.click(screen.getByRole('button', { name: "O'tkazish (1)" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/owner/product-brands'))).toBe(true));
    expect(await calls.find((call) => call.url.endsWith('/owner/product-brands'))!.json()).toEqual({ brand_id: 4, product_ids: [2] });
    expect(await screen.findByText("1 ta taom o'tkazildi.")).toBeInTheDocument();
  });

  it("shows the server's module refusal", async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/brands': () => json({ success: false, message: "Ko'p brend moduli bu filialda o'chirilgan.", data: { reason: 'module_disabled' } }, 403),
    });
    renderPage(<BrandsView />);
    expect(await screen.findByText("Ko'p brend moduli bu filialda o'chirilgan.")).toBeInTheDocument();
  });

  it('picks a readable text colour for the badge', () => {
    expect(readableOn('#FACC15')).toBe('#000000');
    expect(readableOn('#1E3A8A')).toBe('#FFFFFF');
    expect(readableOn('oops')).toBe('#FFFFFF');
  });
});

describe('Ingredientlar', () => {
  it('"Tovuq tugadi": stops the ingredient on the branch with a reason, then reopens it', async () => {
    let stopped = false;
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/branches': () => ok([branch()]),
      'GET /api/v1/owner/ingredients': () =>
        ok({ branch_id: 1, ingredients: [ingredient(stopped ? { stopped: [{ branch_id: 1, entry_id: 9, reason: 'Kelmadi', until: null }], stopped_here: true } : {})] }),
      'POST /api/v1/owner/ingredients/2/stop': () => {
        stopped = true;
        return json({ success: true, data: { entry_id: 9, branch_id: 1, ingredient_id: 2, reason: 'Kelmadi', until: null, closed_product_ids: [1, 4] } }, 201);
      },
      'DELETE /api/v1/owner/ingredients/2/stop': () => {
        stopped = false;
        return ok({ branch_id: 1, ingredient_id: 2 });
      },
    });
    renderPage(<IngredientsView />);
    const user = userEvent.setup();

    const table = await screen.findByRole('table', { name: 'Ingredientlar' });
    expect(within(table).getByText("1 ta yopiladi (2 ta bog'langan)")).toBeInTheDocument();
    await user.click(within(table).getByRole('button', { name: 'Tovuq tugadi' }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Tovuq burger')).toBeInTheDocument();
    await user.type(within(dialog).getByRole('textbox', { name: 'Sabab' }), 'Kelmadi');
    await user.click(within(dialog).getByRole('button', { name: "To'xtatish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST' && call.url.endsWith('/owner/ingredients/2/stop'))).toBe(true));
    expect(await calls.find((call) => call.url.endsWith('/owner/ingredients/2/stop'))!.json()).toEqual({ branch_id: 1, reason: 'Kelmadi' });
    expect(await screen.findByText("Tovuq to'xtatildi: 2 ta taom yopildi.")).toBeInTheDocument();

    expect(await screen.findByText('Kelmadi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Qayta ochish' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE' && call.url.includes('/owner/ingredients/2/stop?branch_id=1'))).toBe(true));
    expect(await screen.findByText('Tovuq qayta ochildi: taomlar sotuvga qaytdi.')).toBeInTheDocument();
  });

  it('edits the links: a dish ticked, one only marked, a whole category', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/branches': () => ok([branch()]),
      'GET /api/v1/owner/ingredients': () => ok({ branch_id: 1, ingredients: [ingredient()] }),
      'GET /api/v1/owner/products': () => json({ success: true, data: [product({ id: 1, name: 'Tovuq burger' }), product({ id: 3, name: 'Salat' }), product({ id: 5, name: 'Lavash' })] }),
      'GET /api/v1/owner/categories': () => ok([category()]),
      'PUT /api/v1/owner/ingredients/2/products': () => ok(ingredient()),
      'POST /api/v1/owner/ingredients/2/link-categories': () => ok({ ...ingredient(), added: 3 }),
    });
    renderPage(<IngredientsView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Taomlar' }));
    let dialog = await screen.findByRole('dialog');
    await user.click(await within(dialog).findByRole('checkbox', { name: 'Lavash ishlatadi' }));
    await user.click(within(dialog).getByRole('switch', { name: 'Salat uchun muhim' }));
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/owner/ingredients/2/products'))).toBe(true));
    expect(await calls.find((call) => call.url.endsWith('/owner/ingredients/2/products'))!.json()).toEqual({
      links: [
        { product_id: 1, is_critical: true },
        { product_id: 3, is_critical: true },
        { product_id: 5, is_critical: true },
      ],
    });
    expect(await screen.findByText("Bog'lanishlar saqlandi.")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Taomlar' }));
    dialog = await screen.findByRole('dialog');
    await user.selectOptions(await within(dialog).findByRole('combobox', { name: "Kategoriyani butunlay bog'lash" }), '2');
    await user.click(within(dialog).getByRole('button', { name: "Bog'lash" }));
    expect(await screen.findByText("3 ta taom bog'landi.")).toBeInTheDocument();
    expect(await calls.find((call) => call.url.endsWith('/link-categories'))!.json()).toEqual({ category_ids: [2], is_critical: true });
  });

  it('a manager without menu.manage stops but does not edit', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view', 'stoplist.manage']),
      'GET /api/v1/owner/branches': () => ok([branch()]),
      'GET /api/v1/owner/ingredients': () => ok({ branch_id: 1, ingredients: [ingredient()] }),
    });
    renderPage(<IngredientsView />);
    expect(await screen.findByRole('button', { name: 'Tovuq tugadi' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Ingredient qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Taomlar' })).not.toBeInTheDocument();
  });
});

describe('Kanal tushumi', () => {
  const report = {
    period: { from: '2026-11-15', to: '2026-11-21', timezone: 'Asia/Tashkent' },
    branch_id: null,
    currency: 'UZS',
    rows: [
      { channel: 'aggregator', channel_label: 'Agregator', commission_percent: '25.00', commission_source: 'owner', brand_id: 4, brand_name: 'Burger Bro', brand_code: 'BB', brand_color: '#DC2626', ...figures({ gross: '50000.00', discounts: '0.00', commission: '12500.00', net: '37500.00' }) },
      { channel: 'telegram', channel_label: 'Telegram', commission_percent: null, commission_source: 'billing', brand_id: null, brand_name: null, brand_code: null, brand_color: null, ...figures() },
    ],
    by_channel: [
      { channel: 'aggregator', channel_label: 'Agregator', commission_percent: '25.00', commission_source: 'owner', ...figures({ net: '37500.00' }) },
      { channel: 'telegram', channel_label: 'Telegram', commission_percent: null, commission_source: 'billing', ...figures() },
    ],
    by_brand: [
      { brand_id: null, brand_name: null, brand_code: null, brand_color: null, ...figures() },
      { brand_id: 4, brand_name: 'Burger Bro', brand_code: 'BB', brand_color: '#DC2626', ...figures({ net: '37500.00' }) },
    ],
    totals: figures({ orders: 2, gross: '150000.00', commission: '21500.00', net: '118500.00' }),
  };

  it('shows the channel × brand rows and the totals, and saves the aggregator commission', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer([...OWNER_PERMISSIONS, 'reports.view', 'settings.manage']),
      'GET /api/v1/owner/reports/channel-revenue': () => ok(report),
      'GET /api/v1/owner/channel-commissions': () => ok({ rates: [{ channel: 'aggregator', percent: '25.00', note: 'Yandex' }], channels: [] }),
      'PUT /api/v1/owner/channel-commissions': () => ok({ rates: [] }),
    });
    renderPage(<ChannelRevenueView />);
    const user = userEvent.setup();

    const table = await screen.findByRole('table', { name: 'Kanal va brend' });
    const rows = within(table).getAllByRole('row');
    expect(rows[1]).toHaveTextContent(/Agregator · 25%/);
    expect(rows[1]).toHaveTextContent('BB');
    expect(rows[2]).toHaveTextContent('Asosiy brend');
    expect(rows[3]).toHaveTextContent('Jami');
    expect(rows[3]).toHaveTextContent(/118\s?500/);
    const query = new URL(calls.find((call) => call.url.includes('/owner/reports/channel-revenue'))!.url).searchParams;
    expect(query.get('from')).toMatch(/^\d{4}-\d{2}-\d{2}$/);

    await user.click(screen.getByRole('button', { name: 'Komissiyalar' }));
    const dialog = await screen.findByRole('dialog');
    const aggregator = await within(dialog).findByRole('textbox', { name: 'Agregator: foiz' });
    await waitFor(() => expect(aggregator).toHaveValue('25'));
    await user.clear(aggregator);
    await user.type(aggregator, '22,5');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/owner/channel-commissions'))).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ rates: [{ channel: 'aggregator', percent: '22.5', note: 'Yandex' }] });
    expect(await screen.findByText('Komissiyalar saqlandi.')).toBeInTheDocument();
  });

  it('a person without reports.view is told so', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']) });
    renderPage(<ChannelRevenueView />);
    expect(await screen.findByText("Hisobotlarni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});
