import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { kazansText, parseKazans, type ContractDocument, type OpsSettings, type ProductionSheet, type Settlement, type ShoppingList } from './api';
import { ContractView } from './ContractView';
import { draftOf, inputOf, OpsSettingsView } from './OpsSettingsView';
import { ProductionSheetView } from './ProductionSheetView';
import { SettlementView } from './SettlementView';
import { ShoppingListView } from './ShoppingListView';

const EVENT = '5b1c7c2e-3f5a-4d8e-9a10-7c2b1d4e5f60';
const search = vi.hoisted(() => ({ value: '' }));
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), useSearchParams: () => new URLSearchParams(search.value) }));
const saveBlob = vi.hoisted(() => vi.fn());
vi.mock('../reports/download', () => ({ saveBlob }));

beforeEach(() => {
  signIn();
  search.value = `event=${EVENT}`;
  saveBlob.mockReset();
});
afterEach(() => setSession(null));

const osh = {
  persons: 220,
  persons_per_kg: '5.0',
  osh_kg: '44.0',
  ingredients: [
    { key: 'rice', name: 'Guruch', unit: 'kg' as const, per_kg: '1.000', quantity: '44.000' },
    { key: 'oil', name: "Yog'", unit: 'l' as const, per_kg: '0.250', quantity: '11.000' },
  ],
  kazans: [{ size_kg: 50, count: 1 }],
  kazan_capacity_kg: '50.0',
};

const sheet = (): ProductionSheet => ({
  event: { uuid: EVENT, title: "Aziz va Malika to'yi", event_type: 'toy', event_type_label: "To'y", event_date: '2027-01-20', slot: 'evening', starts_at: null, ends_at: null, local_time: '18:00–23:00', status: 'confirmed', halls: ['Oq zal'] },
  guests: { guaranteed: 200, minimum: 150, actual: null, base: 200, reserve_percent: '10.00', planned: 220 },
  stations: [
    { station_id: 3, name: 'Osh sexi', dishes: [{ product_id: 9, name: "To'y oshi", unit: 'portion', quantity_per_person: '1.000', quantity: '220.000', kg: '77.000' }] },
    { station_id: null, name: 'Umumiy', dishes: [{ product_id: null, name: 'Non', unit: 'piece', quantity_per_person: '1.000', quantity: '220.000', kg: null }] },
  ],
  osh,
  warnings: [{ code: 'guests_over_threshold', message: "Mehmonlar soni 240: 200 dan ko'p." }],
});

const settlement = (over: Partial<Settlement> = {}): Settlement => ({
  event: { uuid: EVENT, status: 'in_progress', version: 4, order_uuid: 'o-1' },
  guests: { guaranteed: 200, minimum: 150, actual: 180, billed: 200 },
  lines: [
    { uuid: 'l1', kind: 'package', name: 'Standart', unit_price: '150000.00', quantity: '1.000', persons: 200, amount: '30000000.00' },
    { uuid: 'l2', kind: 'hall_rent', name: 'Zal ijarasi: Oq zal', unit_price: '5000000.00', quantity: '1.000', persons: null, amount: '5000000.00' },
  ],
  package_total: '30000000.00',
  hall_total: '5000000.00',
  extras_total: '0.00',
  orders: { lines: '60000.00', discount: '0.00', service_charge: '0.00', other_charges: '0.00', total: '60000.00' },
  total: '35060000.00',
  advances: { total: '25000000.00', applied: '0.00', rows: [] },
  due: '10060000.00',
  excess: '0.00',
  settlement: null,
  warnings: [],
  ...over,
});

const settings = (): OpsSettings => ({
  reserve_percent: '10.00',
  osh: { persons_per_kg: '5.0', product_ids: [9], ingredients: [{ key: 'rice', name: 'Guruch', unit: 'kg', per_kg: '1.000' }], kazans: [10, 20, 50] },
  portion_weights: [{ product_id: 9, grams: 350 }],
  mxik: { package: { mxik: '10305001003000000', package_code: null }, hall: { mxik: null, package_code: null }, extra: { mxik: null, package_code: null } },
  contract_template: 'Shartnoma {{contract_no}}',
  contract_template_is_default: false,
  contract_placeholders: ['contract_no', 'customer_name'],
  version: 3,
});

describe('Ishlab chiqarish varag\'i', () => {
  it('shows the dishes by station, the osh kazans, the warnings and downloads the Excel', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['kitchen.manage']),
      [`GET /api/v1/pos/banquets/${EVENT}/production-sheet`]: () => ok(sheet()),
      [`GET /api/v1/pos/banquets/${EVENT}/production-sheet/export`]: () => new Response('xlsx', { status: 200, headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': 'attachment; filename="ishlab-chiqarish-2027-01-20.xlsx"' } }),
    });
    renderPage(<ProductionSheetView />);
    const table = await screen.findByRole('table', { name: 'Osh sexi' });
    expect(table).toHaveTextContent("To'y oshi");
    expect(table).toHaveTextContent('220 porsiya');
    expect(table).toHaveTextContent('77');
    expect(screen.getByText('220')).toBeInTheDocument();
    expect(screen.getByText('1 × 50 kg')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent("Mehmonlar soni 240");
    await userEvent.setup().click(screen.getByRole('button', { name: 'Excel yuklab olish' }));
    expect(await screen.findByText('ishlab-chiqarish-2027-01-20.xlsx yuklab olindi.')).toBeInTheDocument();
    expect(saveBlob).toHaveBeenCalledOnce();
    expect(calls.some((call) => call.url.includes('/production-sheet/export'))).toBe(true);
  });

  it('asks for an event without one', async () => {
    search.value = '';
    mockFetch({ 'GET /api/v1/me/permissions': () => ownerAnswer(['banquets.manage']) });
    renderPage(<ProductionSheetView />);
    expect(await screen.findByText('Tadbir tanlanmagan')).toBeInTheDocument();
  });
});

describe("Bozorlik ro'yxati", () => {
  it('sums the osh ingredients and the dishes of the dates', async () => {
    const list: ShoppingList = {
      branch_id: 1,
      from: '2027-01-20',
      to: '2027-01-22',
      events: [{ uuid: EVENT, title: "Aziz va Malika to'yi", event_type: 'toy', event_date: '2027-01-20', slot: 'evening', status: 'confirmed', planned_guests: 220 }],
      dishes: [{ product_id: 9, name: "To'y oshi", unit: 'portion', quantity_per_person: null, quantity: '330.000', kg: '115.500' }],
      osh: { persons: 330, osh_kg: '66.0', ingredients: osh.ingredients },
    };
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['banquets.manage']),
      'GET /api/v1/pos/banquets/shopping-list': () => ok(list),
    });
    renderPage(<ShoppingListView />);
    expect(await screen.findByRole('table', { name: 'Osh masalliqlari' })).toHaveTextContent('Guruch44 kg');
    expect(screen.getByRole('table', { name: 'Taomlar' })).toHaveTextContent('330 porsiya');
    expect(screen.getByRole('link', { name: "Aziz va Malika to'yi" })).toHaveAttribute('href', `/banquets/production?event=${EVENT}`);
    expect(calls.find((call) => call.url.includes('shopping-list'))?.url).toMatch(/from=\d{4}-\d{2}-\d{2}&to=/);
  });
});

describe('Yakuniy hisob', () => {
  it('shows the billed guests, the lines, the deposits and saves the guests who came', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['banquets.manage']),
      [`GET /api/v1/pos/banquets/${EVENT}/settlement`]: () => ok(settlement()),
      [`PATCH /api/v1/pos/banquets/${EVENT}/guests`]: () => ok({ event: {}, settlement: settlement({ guests: { guaranteed: 200, minimum: 150, actual: 260, billed: 260 } }) }),
    });
    renderPage(<SettlementView />);
    const table = await screen.findByRole('table', { name: 'Yakuniy hisob' });
    expect(table).toHaveTextContent('Standart');
    expect(table).toHaveTextContent('200 kishi');
    expect(table).toHaveTextContent('Tadbir kuni buyurtmalari');
    expect(screen.getByText('Yakuniy hisob hali qilinmagan')).toBeInTheDocument();
    const typing = userEvent.setup();
    const input = screen.getByLabelText('Kelgan mehmonlar');
    expect(input).toHaveValue('180');
    await typing.clear(input);
    await typing.type(input, '260');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Kelgan mehmonlar soni saqlandi.')).toBeInTheDocument();
    const patch = calls.find((call) => call.method === 'PATCH')!;
    expect(await patch.json()).toEqual({ guests_actual: 260 });
    expect(patch.headers.get('Idempotency-Key')).toBeTruthy();
    expect(await screen.findByText('260')).toBeInTheDocument();
  });

  it('shows a pending excess refund without the form for a cashier', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['pos.take_payment']),
      [`GET /api/v1/pos/banquets/${EVENT}/settlement`]: () => ok(settlement({ excess: '3000000.00', settlement: { uuid: 's', status: 'settled', bill_uuid: 'b', applied_amount: '17000000.00', excess_amount: '3000000.00', excess_status: 'pending', settled_at: null } })),
    });
    renderPage(<SettlementView />);
    expect(await screen.findByText(/Ortiqcha zaklad qaytarilishi kutilmoqda/)).toBeInTheDocument();
    expect(screen.getByText('Qaytariladi (ortiqcha zaklad)')).toBeInTheDocument();
    expect(screen.queryByLabelText('Kelgan mehmonlar')).not.toBeInTheDocument();
  });
});

describe('Shartnoma', () => {
  it('shows the filled text and gives it a number once', async () => {
    const document = (no: string | null): ContractDocument => ({ contract_no: no, text: `SHARTNOMA № ${no ?? '________'}\nKarimovlar oilasi`, lines: [], fields: {}, warnings: [] });
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['banquets.manage']),
      [`GET /api/v1/pos/banquets/${EVENT}/contract`]: () => ok(document(null)),
      [`POST /api/v1/pos/banquets/${EVENT}/contract/number`]: () => ok(document('2026-0001')),
    });
    renderPage(<ContractView />);
    expect(await screen.findByTestId('contract-text')).toHaveTextContent('Karimovlar oilasi');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Raqam berish' }));
    expect(await screen.findByText('Shartnoma raqami berildi: 2026-0001.')).toBeInTheDocument();
    expect(screen.getByText('Shartnoma № 2026-0001')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Raqam berish' })).not.toBeInTheDocument();
    expect(calls.find((call) => call.method === 'POST')?.headers.get('Idempotency-Key')).toBeTruthy();
  });

  it('is closed without banquets.manage', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => ownerAnswer(['pos.take_payment']) });
    renderPage(<ContractView />);
    expect(await screen.findByText("Bu bo'limni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});

describe('Ishlab chiqarish sozlamalari', () => {
  it('turns the form into the settings the server reads', () => {
    const draft = draftOf(settings());
    expect(draft.kazans).toBe('10, 20, 50');
    const input = inputOf({ ...draft, reserve: '7,5', kazans: '50, 10 x 10' }, 3);
    expect(input.reserve_percent).toBe('7.5');
    expect(input.osh.kazans).toEqual([10, 50]);
    expect(input.hall_mxik).toBeNull();
    expect(input.portion_weights).toEqual([{ product_id: 9, grams: 350 }]);
    expect(parseKazans('30;20 20')).toEqual([20, 30]);
    expect(kazansText([{ size_kg: 50, count: 2 }, { size_kg: 20, count: 1 }])).toBe('2 × 50 kg, 1 × 20 kg');
  });

  it('saves with the version and an Idempotency-Key, with the osh calculator alongside', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(['banquets.manage']),
      'GET /api/v1/owner/pos/banquets/ops-settings': () => ok(settings()),
      'PUT /api/v1/owner/pos/banquets/ops-settings': () => ok({ ...settings(), reserve_percent: '15.00', version: 4 }),
      'GET /api/v1/pos/banquets/osh-calculator': () => ok(osh),
      'GET /api/v1/owner/products': () => ok([{ id: 9, name: "To'y oshi" }]),
    });
    renderPage(<OpsSettingsView />);
    const typing = userEvent.setup();
    const reserve = await screen.findByLabelText('Zaxira, %');
    await typing.clear(reserve);
    await typing.type(reserve, '15');
    expect(await screen.findByText('1 × 50 kg')).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Sozlamalar saqlandi.')).toBeInTheDocument();
    const put = calls.find((call) => call.method === 'PUT')!;
    const body = (await put.json()) as { version: number; reserve_percent: string; contract_template: string };
    expect(body.version).toBe(3);
    expect(body.reserve_percent).toBe('15');
    expect(body.contract_template).toBe('Shartnoma {{contract_no}}');
    expect(put.headers.get('Idempotency-Key')).toBeTruthy();
  });
});
