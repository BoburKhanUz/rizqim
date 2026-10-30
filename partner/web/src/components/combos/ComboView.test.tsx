import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { ok, permissionsAnswer, OWNER_PERMISSIONS, product, variant } from '../menu/menuFixtures';
import { ComboView } from './ComboView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }), usePathname: () => '/menu/combos' }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const option = (over: Record<string, unknown> = {}) => ({
  id: 1,
  uuid: 'o-1',
  variant_id: 21,
  variant_uuid: 'v-21',
  product_id: 2,
  name: 'Lavash',
  variant_name: 'Katta',
  quantity: 1,
  price_delta: '0.00',
  modifiers: [],
  is_default: true,
  label: null,
  receipt_label: 'Asosiy — Lavash (Katta)',
  ...over,
});

const combo = (over: Record<string, unknown> = {}) => ({
  id: 5,
  uuid: 'c-5',
  product_id: 1,
  product_name: 'Lavash kombo',
  prices: ['45000.00'],
  is_active: true,
  slots: [
    { id: 1, uuid: 's-1', name: 'Asosiy', min_picks: 1, max_picks: 1, is_required: true, allow_modifiers: false, options: [option(), option({ id: 2, uuid: 'o-2', variant_id: 22, name: 'Tovuq lavash', variant_name: null, price_delta: '5000.00', is_default: false })] },
    { id: 2, uuid: 's-2', name: 'Sous', min_picks: 0, max_picks: 2, is_required: false, allow_modifiers: false, options: [option({ id: 3, uuid: 'o-3', variant_id: 23, name: 'Achchiq sous', variant_name: null, is_default: false })] },
  ],
  ...over,
});

const menu = [
  product({ id: 1, name: 'Lavash kombo', variants: [variant({ id: 11, product_id: 1, name: 'Kombo', price: '45000.00' })] }),
  product({ id: 2, name: 'Lavash', variants: [variant({ id: 21, product_id: 2, name: 'Katta', price: '32000.00' })] }),
  product({ id: 3, name: 'Tovuq lavash', variants: [variant({ id: 22, product_id: 3, name: 'Tovuq lavash', price: '30000.00' })] }),
  product({ id: 4, name: 'Achchiq sous', variants: [variant({ id: 23, product_id: 4, name: 'Achchiq sous', price: '2000.00' })] }),
  product({ id: 6, name: 'Osh', variants: [variant({ id: 61, product_id: 6, name: '1 kg', unit: 'kg' })] }),
];

const preview = (over: Record<string, unknown> = {}) => ({
  name: 'Lavash kombo',
  quantity: 1,
  base_price: '45000.00',
  delta: '0.00',
  price: '45000.00',
  parts: [{ slot_name: 'Asosiy', name: 'Lavash', variant_name: 'Katta', quantity: 1, label: 'Asosiy — Lavash (Katta)' }],
  fiscal_mode: 'single',
  fiscal: {
    single: [{ name: 'Lavash kombo', mxik_code: '10202001009000001', quantity: 1, amount: '45000.00' }],
    split: [{ name: 'Lavash (Katta)', mxik_code: '10202001011000001', quantity: 1, amount: '45000.00' }],
  },
  ...over,
});

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}, permissions: string[] = [...OWNER_PERMISSIONS, 'settings.manage']) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(permissions),
    'GET /api/v1/owner/combos': () => ok({ combos: [combo()], fiscal_mode: 'single' }),
    'GET /api/v1/owner/products': () => json({ success: true, data: menu }),
    'GET /api/v1/owner/modifier-groups': () => ok([]),
    ...extra,
  };
}

describe('Kombo', () => {
  it('lists the combo with its slots, the required and optional ones, the deltas and the default', async () => {
    mockFetch(routes());
    renderPage(<ComboView />);

    expect(await screen.findByText('Lavash kombo', { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByText('Majburiy')).toBeInTheDocument();
    expect(screen.getByText('Ixtiyoriy')).toBeInTheDocument();
    expect(screen.getByText('0–2 ta')).toBeInTheDocument();
    expect(screen.getByText(/\+5\s000/)).toBeInTheDocument();
    expect(screen.getByText('Standart')).toBeInTheDocument();
    // The owner with settings.manage picks the fiscal mode here
    expect(screen.getByRole('combobox', { name: 'Kombo fiskal chekda' })).toHaveValue('single');
  });

  it('builds a new combo: product, slots, options, delta and the default, sent to the server as it checks them', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/combos': () => ok(combo({ id: 6 }), {}) }));
    renderPage(<ComboView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: "Kombo qo'shish" }));
    const dialog = await screen.findByRole('dialog');
    // The kombo product: only a product that is not a combo yet
    await user.selectOptions(within(dialog).getByRole('combobox', { name: /Kombo mahsuloti/ }), '4');
    await user.type(within(dialog).getByRole('textbox', { name: 'Slot nomi' }), 'Sous');
    // A dish sold by weight is never a part
    const dish = within(dialog).getByRole('combobox', { name: 'Taom' });
    expect(within(dish).queryByRole('option', { name: /Osh/ })).not.toBeInTheDocument();
    await user.selectOptions(dish, '21');
    const deltaField = within(dialog).getByRole('textbox', { name: 'Narx farqi' });
    await user.clear(deltaField);
    await user.type(deltaField, '1500');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(calls.some((call) => call.method === 'POST' && call.url.endsWith('/owner/combos'))).toBe(true));
    const post = calls.find((call) => call.method === 'POST' && call.url.endsWith('/owner/combos'))!;
    expect(await post.json()).toEqual({
      product_id: 4,
      slots: [{ name: 'Sous', min_picks: 1, max_picks: 1, allow_modifiers: false, options: [{ variant_id: 21, quantity: 1, price_delta: '1500', modifiers: [], is_default: true, label: null }] }],
    });
    expect(await screen.findByText('Kombo saqlandi.')).toBeInTheDocument();
  });

  it("shows the server's refusal next to the option it names", async () => {
    mockFetch(
      routes({
        'PUT /api/v1/owner/combos/5': () => json({ success: false, message: "Kombo o'zini, boshqa kombo yoki kompleksni o'z ichiga ololmaydi.", errors: { 'slots.0.options.1.variant_id': ["Kombo o'zini, boshqa kombo yoki kompleksni o'z ichiga ololmaydi."] } }, 422),
      }),
    );
    renderPage(<ComboView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: /^Tahrirlash/ }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getAllByRole('textbox', { name: 'Slot nomi' }).map((input) => (input as HTMLInputElement).value)).toEqual(['Asosiy', 'Sous']);
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect((await within(dialog).findAllByText("Kombo o'zini, boshqa kombo yoki kompleksni o'z ichiga ololmaydi.")).length).toBeGreaterThan(0);
  });

  it('previews the receipt lines for a pick: one combo line, its parts without prices, both fiscal modes', async () => {
    const { calls } = mockFetch(
      routes({
        'POST /api/v1/owner/combos/5/preview': async (request) => {
          const body = (await request.json()) as { picks: { option_id: string }[] };
          return ok(body.picks.length === 0 ? preview() : preview({ delta: '5000.00', price: '50000.00', parts: [{ slot_name: 'Asosiy', name: 'Tovuq lavash', variant_name: null, quantity: 1, label: 'Asosiy — Tovuq lavash' }] }));
        },
      }),
    );
    renderPage(<ComboView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: /^Chek ko'rinishi/ }));
    const dialog = await screen.findByRole('dialog');
    const guest = await within(dialog).findByRole('region', { name: 'Mijoz cheki' });
    expect(within(guest).getByText('Asosiy — Lavash (Katta)')).toBeInTheDocument();
    expect(within(dialog).getByText('10202001009000001')).toBeInTheDocument();
    expect(within(dialog).getByText('(hozirgi rejim)')).toBeInTheDocument();

    await user.selectOptions(within(dialog).getByRole('combobox', { name: 'Asosiy' }), 'o-2');
    expect(await within(dialog).findByText('Asosiy — Tovuq lavash')).toBeInTheDocument();
    const last = calls.filter((call) => call.url.endsWith('/preview')).at(-1)!;
    expect(await last.json()).toEqual({ picks: [{ option_id: 'o-2' }] });
  });

  it('switches the store to fiscal lines by parts', async () => {
    const { calls } = mockFetch(routes({ 'PUT /api/v1/owner/fiscal/settings': () => ok({}) }));
    renderPage(<ComboView />);
    const user = userEvent.setup();

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Kombo fiskal chekda' }), 'split');
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ fiscal_settings: { combo_fiscal_mode: 'split' } });
    expect(await screen.findByText('Fiskal chek rejimi saqlandi.')).toBeInTheDocument();
  });

  it('a reader sees the combos without the edit buttons or the fiscal choice', async () => {
    mockFetch(routes({}, ['menu.view']));
    renderPage(<ComboView />);

    expect(await screen.findByText('Lavash kombo', { selector: 'p' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Kombo qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Tahrirlash/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Kombo fiskal chekda' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Chek ko'rinishi/ })).toBeInTheDocument();
  });

  it('says so when the module is off', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/combos': () => json({ success: false, message: "Kombo bu filial formatida o'chirilgan.", code: 'module_disabled', data: { reason: 'module_disabled' } }, 403) }));
    renderPage(<ComboView />);
    expect(await screen.findByText("Kombo bu filial formatida o'chirilgan.")).toBeInTheDocument();
  });
});
