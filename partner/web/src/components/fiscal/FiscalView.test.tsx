import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { product, variant } from '../menu/menuFixtures';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { FiscalView } from './FiscalView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
vi.mock('next/link', () => ({ default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a> }));

beforeEach(signIn);
afterEach(() => setSession(null));

const MXIK_A = '10202001001000000';
const MXIK_B = '10202001002000000';

const readiness = (over: Record<string, unknown> = {}) => ({
  enabled: true,
  tin_set: true,
  tax_regime: 'vat',
  products_missing_mxik: [
    { id: 7, uuid: 'p-7', name: 'Osh', kind: 'dish', status: 'active', category_id: 2 },
    { id: 8, uuid: 'p-8', name: 'Qozon ijarasi', kind: 'service', status: 'active', category_id: 2 },
  ],
  charges_missing_mxik: ['room_fee', 'delivery'],
  ...over,
});

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/fiscal/readiness': () => ok(readiness()),
    'GET /api/v1/owner/products': () =>
      json({ success: true, data: [product({ id: 7, name: 'Osh', variants: [variant({ id: 11, product_id: 7 })] }), product({ id: 8, name: 'Qozon ijarasi', kind: 'service', variants: [variant({ id: 12, product_id: 8, name: 'soatiga', price: '300000.00' })] })] }),
    ...over,
  });

describe('Fiskal tayyorlik: the products without an MXIK', () => {
  it('lists the products and the charges that have no code, and the state of the fiscal mode', async () => {
    base();
    renderPage(<FiscalView />);
    expect(await screen.findByText('Osh')).toBeInTheDocument();
    expect(screen.getByText('Qozon ijarasi')).toBeInTheDocument();
    expect(screen.getByText('Fiskal rejim yoqilgan')).toBeInTheDocument();
    expect(screen.getByText("MXIK'siz taom va xizmat: 2")).toBeInTheDocument();
    // the charges the settings still lack, in words
    expect(screen.getByText('Xona puli')).toBeInTheDocument();
    expect(screen.getByText('Yetkazib berish')).toBeInTheDocument();
    expect(screen.getByLabelText('Qozon ijarasi: turi')).toHaveValue('service');
  });

  it('says so when fiscal mode is off, and when nothing is missing', async () => {
    base({ 'GET /api/v1/owner/fiscal/readiness': () => ok(readiness({ enabled: false, tin_set: false, products_missing_mxik: [], charges_missing_mxik: [] })) });
    renderPage(<FiscalView />);
    expect(await screen.findByText('Hamma taomda MXIK bor')).toBeInTheDocument();
    expect(screen.getByText("Fiskal rejim o'chiq")).toBeInTheDocument();
    expect(screen.getByText(/Rejim o'chiq: MXIK'siz ham sotiladi/)).toBeInTheDocument();
    expect(screen.getByText('Kiritilmagan')).toBeInTheDocument();
  });

  it('keeps a row from saving until its MXIK is 17 digits, and says what is wrong', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    const field = await screen.findByLabelText('Osh: MXIK kodi');
    const save = screen.getByRole('button', { name: 'Osh: saqlash' });
    expect(save).toBeDisabled();
    await typing.type(field, '12345');
    expect(screen.getByText('MXIK kodi 17 ta raqamdan iborat bo\'lishi kerak.')).toBeInTheDocument();
    expect(save).toBeDisabled();
    await typing.clear(field);
    await typing.type(field, MXIK_A);
    expect(screen.queryByText('MXIK kodi 17 ta raqamdan iborat bo\'lishi kerak.')).not.toBeInTheDocument();
    expect(save).toBeEnabled();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it('saves one row: the whole product goes back with only its MXIK new, so no variant is lost', async () => {
    const { calls } = base({ 'POST /api/v1/owner/products/7': () => ok({ id: 7 }) });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.type(await screen.findByLabelText('Osh: MXIK kodi'), MXIK_A);
    await typing.click(screen.getByRole('button', { name: 'Osh: saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('1 ta taom saqlandi.'));
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('mxik_code')).toBe(MXIK_A);
    expect(form.get('name')).toBe('Osh');
    expect(form.get('category_id')).toBe('2');
    expect(form.get('is_active')).toBe('1');
    expect(form.has('kind')).toBe(false);
    expect(JSON.parse(String(form.get('variants')))).toEqual([expect.objectContaining({ id: 11, name: '1 porsiya', price: 35000, unit: 'piece' })]);
  });

  it('saves a changed kind with the code', async () => {
    const { calls } = base({ 'POST /api/v1/owner/products/7': () => ok({ id: 7 }) });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.selectOptions(await screen.findByLabelText('Osh: turi'), 'service');
    await typing.type(screen.getByLabelText('Osh: MXIK kodi'), MXIK_A);
    await typing.type(screen.getByLabelText('Osh: qadoq kodi'), '1234567');
    await typing.click(screen.getByRole('button', { name: 'Osh: saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('kind')).toBe('service');
    expect(form.get('package_code')).toBe('1234567');
  });

  it('saves every filled row in one go and shows the reason of the one the server refuses, keeping its text', async () => {
    const { calls } = base({
      'POST /api/v1/owner/products/7': () => ok({ id: 7 }),
      'POST /api/v1/owner/products/8': () => json({ success: false, message: 'Xato', errors: { mxik_code: ["MXIK kodi 17 ta raqamdan iborat bo'lishi kerak (tasnifda topilmadi)."] } }, 422),
    });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.type(await screen.findByLabelText('Osh: MXIK kodi'), MXIK_A);
    await typing.type(screen.getByLabelText('Qozon ijarasi: MXIK kodi'), MXIK_B);
    const all = screen.getByRole('button', { name: 'Hammasini saqlash (2)' });
    await typing.click(all);
    await waitFor(() => expect(calls.filter((call) => call.method === 'POST')).toHaveLength(2));
    expect(await screen.findByText("1 ta taom saqlandi.")).toBeInTheDocument();
    expect(screen.getByText("1 ta taom saqlanmadi: sababi qatorda ko'rsatilgan.")).toBeInTheDocument();
    expect(screen.getByText("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak (tasnifda topilmadi).")).toBeInTheDocument();
    expect(screen.getByLabelText('Qozon ijarasi: MXIK kodi')).toHaveValue(MXIK_B);
  });

  it('filters the table by name', async () => {
    base();
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.type(await screen.findByLabelText("Nomi bo'yicha qidirish"), 'qozon');
    expect(screen.queryByLabelText('Osh: MXIK kodi')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Qozon ijarasi: MXIK kodi')).toBeInTheDocument();
    await typing.clear(screen.getByLabelText("Nomi bo'yicha qidirish"));
    await typing.type(screen.getByLabelText("Nomi bo'yicha qidirish"), 'yo\'q taom');
    expect(screen.getByText('Hech narsa topilmadi')).toBeInTheDocument();
  });

  it('leads to the settings for the charges', async () => {
    base({ 'GET /api/v1/owner/fiscal/settings': () => ok(settings()) });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('button', { name: "Soliq sozlamalarida to'ldirish" }));
    expect(await screen.findByLabelText('STIR')).toBeInTheDocument();
  });

  it('does not let a person without the right to change the menu edit codes', async () => {
    base({}, ['menu.view', 'reports.view']);
    renderPage(<FiscalView />);
    expect(await screen.findByText('Osh')).toBeInTheDocument();
    expect(screen.getByText('Kodni kiritish uchun menyuni o\'zgartirish huquqi kerak.')).toBeInTheDocument();
    expect(screen.getByLabelText('Osh: MXIK kodi')).toBeDisabled();
    expect(screen.queryByRole('button', { name: /Hammasini saqlash/ })).not.toBeInTheDocument();
  });
});

const settings = (over: Record<string, unknown> = {}) => ({
  tin: '123456789',
  tax_regime: 'vat',
  fiscal_settings: {
    enabled: true,
    driver: 'sandbox',
    active_driver: 'sandbox',
    charges: {
      room_fee: { name: 'Xona puli', mxik: null, package_code: null, unit: 'xizmat' },
      place_fee: { name: 'Joy puli', mxik: null, package_code: null, unit: 'xizmat' },
      min_spend_topup: { name: "Minimal summaga qo'shimcha", mxik: null, package_code: null, unit: 'xizmat' },
      service_charge: { name: 'Xizmat haqi', mxik: '10202002002000000', package_code: null, unit: 'xizmat' },
      deposit: { name: 'Bron avansi', mxik: '10202002004000001', package_code: null, unit: 'xizmat' },
      booking_fee: { name: 'Bron xizmati', mxik: '10202002003000002', package_code: null, unit: 'xizmat' },
      credit: { name: "Qarz to'lovi", mxik: '10202002004000001', package_code: null, unit: 'xizmat' },
      delivery: { name: 'Yetkazib berish', mxik: null, package_code: null, unit: 'xizmat' },
    },
    sandbox: { latency_ms: 0, fail_attempts: 0, failure_rate: 0, reject: false },
    deposit_refund_receipt: null,
    retained_deposit_receipt: null,
  },
  drivers: ['sandbox', 'null'],
  tax_regimes: [
    { value: 'vat', label: "QQS to'lovchi", vat_rate: '12' },
    { value: 'vat_simplified', label: 'Soddalashtirilgan', vat_rate: '6' },
    { value: 'turnover', label: "Aylanma solig'i", vat_rate: '0' },
  ],
  ...over,
});

describe('Soliq sozlamalari: the store\'s fiscal settings', () => {
  const open = async () => {
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('tab', { name: 'Soliq sozlamalari' }));
    await screen.findByLabelText('STIR');
    return typing;
  };

  it('shows the driver as what it is: a test, not the tax office, and the deposit rules as waiting for the accountant', async () => {
    base({ 'GET /api/v1/owner/fiscal/settings': () => ok(settings()) });
    await open();
    expect(screen.getByRole('option', { name: 'Sinov (sandbox)' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Demo (fiskal emas)' })).toBeInTheDocument();
    expect(screen.getByText(/Hozir cheklar haqiqiy soliq tizimiga yuborilmaydi/)).toBeInTheDocument();
    expect(screen.getByText(/Bu fiskal chek emas/)).toBeInTheDocument();
    expect(screen.getByText('Hozir ishlayotgan usul: Sinov (sandbox)')).toBeInTheDocument();
    expect(screen.getByText(/buxgalter bilan tasdiqlanmagan/)).toBeInTheDocument();
    // the standard codes the server gives are already in the fields
    expect(screen.getByLabelText('Bron avansi: MXIK')).toHaveValue('10202002004000001');
    // and the charges with no official code say so
    expect(screen.getAllByText("Rasmiy kod topilmagan: tasnif.soliq.uz da tekshirib kiriting.")).toHaveLength(5);
  });

  it('shows the demo driver by name when nothing is sent anywhere', async () => {
    base({ 'GET /api/v1/owner/fiscal/settings': () => ok(settings({ fiscal_settings: { ...settings().fiscal_settings, driver: 'null', active_driver: 'null' } })) });
    await open();
    expect(screen.getByText('Hozir ishlayotgan usul: Demo (fiskal emas)')).toBeInTheDocument();
  });

  it('checks the TIN and the codes before calling the server', async () => {
    const { calls } = base({ 'GET /api/v1/owner/fiscal/settings': () => ok(settings()) });
    const typing = await open();
    await typing.clear(screen.getByLabelText('STIR'));
    await typing.type(screen.getByLabelText('STIR'), '12345');
    await typing.type(screen.getByLabelText('Xona puli: MXIK'), '123');
    await typing.type(screen.getByLabelText('Joy puli: Qadoq kodi'), 'abc');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('STIR 9 ta raqamdan iborat bo\'lishi kerak.')).toBeInTheDocument();
    expect(screen.getByText("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")).toBeInTheDocument();
    expect(screen.getByText("Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('sends only what changed: a charge code, the driver and an accountant rule', async () => {
    const { calls } = base({
      'GET /api/v1/owner/fiscal/settings': () => ok(settings()),
      'PUT /api/v1/owner/fiscal/settings': () => ok(settings({ fiscal_settings: { ...settings().fiscal_settings, driver: 'null', active_driver: 'null' } })),
    });
    const typing = await open();
    await typing.type(screen.getByLabelText('Xona puli: MXIK'), '10202002009000000');
    await typing.selectOptions(screen.getByLabelText('Chek yuborish usuli'), 'null');
    await typing.selectOptions(screen.getByLabelText('Avans qaytarilganda qaytarish cheki'), 'yes');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Fiskal sozlamalar saqlandi.')).toBeInTheDocument();
    const body = await calls.find((call) => call.method === 'PUT')!.json();
    expect(body).toEqual({
      fiscal_settings: { enabled: true, driver: 'null', charges: { room_fee: { mxik: '10202002009000000' } }, deposit_refund_receipt: true },
    });
    // the form now shows what the server answered
    expect(screen.getByText('Hozir ishlayotgan usul: Demo (fiskal emas)')).toBeInTheDocument();
  });

  it('clears a charge code to the default with null, and puts a switched-on mode without a TIN to the server\'s word', async () => {
    const { calls } = base({
      'GET /api/v1/owner/fiscal/settings': () => ok(settings({ fiscal_settings: { ...settings().fiscal_settings, enabled: false } })),
      'PUT /api/v1/owner/fiscal/settings': () => json({ success: false, message: 'Xato', errors: { 'fiscal_settings.enabled': ['Fiskal rejimni yoqish uchun STIR va soliq rejimi kerak.'] } }, 422),
    });
    const typing = await open();
    await typing.clear(screen.getByLabelText('Xizmat haqi: MXIK'));
    await typing.click(screen.getByRole('checkbox', { name: /Fiskal rejim yoqilgan/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Fiskal rejimni yoqish uchun STIR va soliq rejimi kerak.')).toBeInTheDocument();
    const body = await calls.find((call) => call.method === 'PUT')!.json();
    expect(body.fiscal_settings.charges.service_charge).toEqual({ mxik: null });
    expect(body.fiscal_settings.enabled).toBe(true);
  });

  it('is not offered to a manager who cannot change settings', async () => {
    base({}, ['menu.view', 'menu.manage', 'reports.view']);
    renderPage(<FiscalView />);
    await screen.findByRole('tab', { name: 'Fiskal tayyorlik' });
    expect(screen.getByRole('tab', { name: 'Fiskal cheklar' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Soliq sozlamalari' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Soliq sozlamalarida to'ldirish" })).not.toBeInTheDocument();
  });

  it('says there is nothing to see for a role with none of the fiscal permissions', async () => {
    base({}, ['orders.view']);
    renderPage(<FiscalView />);
    expect(await screen.findByText("Fiskal bo'limlarini ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument();
  });
});

const receipt = (over: Record<string, unknown> = {}) => ({
  uuid: 'r1',
  type: 'sale',
  status: 'failed',
  payment_form: 'card',
  driver: 'sandbox',
  provider: 'sandbox',
  non_fiscal: true,
  tax_regime: 'vat',
  total: '228000.00',
  vat_total: '24428.57',
  fiscal_sign: null,
  receipt_number: null,
  qr_url: null,
  terminal_id: null,
  error: 'Provayder javob bermadi',
  attempts: 3,
  next_attempt_at: null,
  issued_at: null,
  created_at: '2026-10-21T10:00:00+05:00',
  branch_id: 1,
  device_id: 1,
  subject_type: 'order_bill',
  subject_id: 4,
  original_receipt_uuid: null,
  lines: [{ name: 'Osh', mxik_code: MXIK_A, package_code: null, unit: 'dona', quantity: '2.000', unit_price: '35000.00', amount: '70000.00', vat_rate: '12', vat_amount: '7500.00', no_vat: false, commissioner_tin: null, marking_code: null }],
  payments: [{ form: 'card', amount: '228000.00', prepayment: false, credit: false, card_type: 'uzcard', transaction_id: null }],
  ...over,
});

describe('Fiskal cheklar', () => {
  const page = (rows: unknown[]) => ok({ data: rows, current_page: 1, last_page: 1, per_page: 25, total: rows.length, from: 1, to: rows.length });

  it('lists the receipts, says "not fiscal" for a test driver, and filters by status and type', async () => {
    const { calls } = base({ 'GET /api/v1/owner/fiscal/receipts': () => page([receipt(), receipt({ uuid: 'r2', status: 'issued', type: 'advance', non_fiscal: false, fiscal_sign: '987654321' })]) });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('tab', { name: 'Fiskal cheklar' }));
    const table = await screen.findByRole('table');
    expect(within(table).getByText('Xato')).toBeInTheDocument();
    expect(within(table).getAllByText('Fiskal emas')).toHaveLength(1);
    expect(screen.getByText('987654321')).toBeInTheDocument();
    await typing.selectOptions(screen.getByLabelText('Holati'), 'failed');
    await typing.selectOptions(screen.getByLabelText('Turi'), 'sale');
    await waitFor(() => {
      const last = calls.filter((call) => new URL(call.url).pathname === '/api/v1/owner/fiscal/receipts').at(-1)!;
      expect(Object.fromEntries(new URL(last.url).searchParams)).toMatchObject({ status: 'failed', type: 'sale', page: '1', per_page: '25' });
    });
  });

  it('says so when there are no receipts', async () => {
    base({ 'GET /api/v1/owner/fiscal/receipts': () => page([]) });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('tab', { name: 'Fiskal cheklar' }));
    expect(await screen.findByText("Cheklar yo'q")).toBeInTheDocument();
  });

  it('opens a receipt with its lines and payments and sends a failed one again', async () => {
    let state = receipt();
    const { calls } = base({
      'GET /api/v1/owner/fiscal/receipts': () => page([state]),
      'GET /api/v1/owner/fiscal/receipts/r1': () => ok(state),
      'POST /api/v1/owner/fiscal/receipts/r1/retry': () => {
        state = receipt({ status: 'issued', error: null, non_fiscal: true });
        return ok(state);
      },
    });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('tab', { name: 'Fiskal cheklar' }));
    await typing.click(await screen.findByRole('button', { name: /Chekni ochish/ }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Provayder javob bermadi')).toBeInTheDocument();
    expect(within(dialog).getByText(MXIK_A)).toBeInTheDocument();
    expect(within(dialog).getByText('Osh')).toBeInTheDocument();
    await typing.click(within(dialog).getByRole('button', { name: 'Qayta yuborish' }));
    expect(await within(dialog).findByText('Chek qayta yuborildi.')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1);
    // issued now: no more retry
    await waitFor(() => expect(within(dialog).queryByRole('button', { name: 'Qayta yuborish' })).not.toBeInTheDocument());
  });

  it('shows the server\'s refusal of a retry', async () => {
    base({
      'GET /api/v1/owner/fiscal/receipts': () => page([receipt({ status: 'pending' })]),
      'GET /api/v1/owner/fiscal/receipts/r1': () => ok(receipt({ status: 'pending' })),
      'POST /api/v1/owner/fiscal/receipts/r1/retry': () => json({ success: false, message: 'Chek allaqachon yuborilgan.', data: { reason: 'already_issued' } }, 422),
    });
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('tab', { name: 'Fiskal cheklar' }));
    await typing.click(await screen.findByRole('button', { name: /Chekni ochish/ }));
    await typing.click(await screen.findByRole('button', { name: 'Qayta yuborish' }));
    expect(await screen.findByText('Chek allaqachon yuborilgan.')).toBeInTheDocument();
  });

  it('does not offer a retry to someone who may only read the receipts, and says why', async () => {
    base(
      {
        'GET /api/v1/owner/fiscal/receipts': () => page([receipt()]),
        'GET /api/v1/owner/fiscal/receipts/r1': () => ok(receipt()),
      },
      ['reports.view'],
    );
    const typing = userEvent.setup();
    renderPage(<FiscalView />);
    await typing.click(await screen.findByRole('button', { name: /Chekni ochish/ }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText("Qayta yuborishni faqat do'kon egasi bajaradi.")).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Qayta yuborish' })).not.toBeInTheDocument();
  });
});
