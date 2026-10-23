import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { getApiFieldError } from './errors';
import {
  fetchFiscalReadiness,
  fetchFiscalReceipt,
  fetchFiscalReceipts,
  fetchFiscalSettings,
  isMxik,
  isOnlineFiscalProblem,
  isPackageCode,
  ONLINE_FISCAL_PROBLEMS,
  productFiscalPatch,
  retryFiscalReceipt,
  saveFiscalSettings,
  saveProductFiscal,
} from './fiscal';
import { productFormData } from './menu';

afterEach(() => vi.unstubAllGlobals());

describe('codes', () => {
  it('knows an MXIK (17 digits) and a package code (digits, up to 20)', () => {
    expect(isMxik('10202001001000000')).toBe(true);
    expect(isMxik('1020200100100000')).toBe(false);
    expect(isMxik('1020200100100000a')).toBe(false);
    expect(isPackageCode('1234567')).toBe(true);
    expect(isPackageCode('')).toBe(false);
    expect(isPackageCode('1'.repeat(21))).toBe(false);
  });

  it('lists the problem codes of an online payment', () => {
    expect(ONLINE_FISCAL_PROBLEMS).toContain('mxik_missing');
    expect(isOnlineFiscalProblem('click_qr_missing')).toBe(true);
    expect(isOnlineFiscalProblem('something_new')).toBe(false);
  });
});

describe('product fiscal fields', () => {
  it('sends kind, MXIK, package code and VAT as form fields; null clears; undefined leaves the field out', () => {
    const form = productFormData({ name: 'Osh', category_id: 3, variants: [], kind: 'service', mxik_code: '10202001001000000', package_code: null, vat_rate: 12 });
    expect(form.get('kind')).toBe('service');
    expect(form.get('mxik_code')).toBe('10202001001000000');
    expect(form.get('package_code')).toBe('');
    expect(form.get('vat_rate')).toBe('12');
    const bare = productFormData({ name: 'Osh', category_id: 3, variants: [] });
    for (const field of ['kind', 'mxik_code', 'package_code', 'vat_rate']) expect(bare.has(field)).toBe(false);
  });

  it('patches only the fiscal fields: "" and null clear, undefined leaves the field out', async () => {
    expect(productFiscalPatch({ mxik_code: '10202001001000000', package_code: '', vat_rate: '12' })).toEqual({ mxik_code: '10202001001000000', package_code: null, vat_rate: 12 });
    expect(productFiscalPatch({ kind: 'service', vat_rate: null })).toEqual({ kind: 'service', vat_rate: null });
    expect(productFiscalPatch({})).toEqual({});

    const { calls } = mockFetch({ 'PATCH /api/v1/owner/products/12/fiscal': () => ok({ id: 12, mxik_code: '10202001001000000' }) });
    await saveProductFiscal(testApi(), 12, { mxik_code: '10202001001000000' }, [{ id: 100, unit_label: 'kg' }]);
    expect(await calls[0]!.json()).toEqual({ mxik_code: '10202001001000000', variants: [{ id: 100, unit_label: 'kg' }] });
  });
});

describe('fiscal endpoints', () => {
  it('reads the readiness', async () => {
    mockFetch({ 'GET /api/v1/owner/fiscal/readiness': () => ok({ enabled: true, tin_set: false, tax_regime: null, products_missing_mxik: [{ id: 1, name: 'Osh' }], charges_missing_mxik: ['room_fee'] }) });
    const readiness = await fetchFiscalReadiness(testApi());
    expect(readiness.charges_missing_mxik).toEqual(['room_fee']);
    expect(readiness.tin_set).toBe(false);
  });

  it('reads the settings and sends a partial update with a null charge field', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/fiscal/settings': () => ok({ tin: null, tax_regime: null, fiscal_settings: { enabled: false, driver: 'sandbox', active_driver: 'null', charges: {} }, drivers: ['sandbox', 'null'], tax_regimes: [] }),
      'PUT /api/v1/owner/fiscal/settings': () => ok({ tin: '123456789', tax_regime: 'vat', fiscal_settings: { enabled: true, driver: 'sandbox', active_driver: 'sandbox', charges: {} }, drivers: [], tax_regimes: [] }),
    });
    const api = testApi();
    expect((await fetchFiscalSettings(api)).fiscal_settings.active_driver).toBe('null');
    const saved = await saveFiscalSettings(api, { tin: '123456789', tax_regime: 'vat', fiscal_settings: { enabled: true, charges: { room_fee: { mxik: '10202002001000000', unit: null } } } });
    expect(saved.fiscal_settings.enabled).toBe(true);
    expect(await calls[1]!.json()).toEqual({ tin: '123456789', tax_regime: 'vat', fiscal_settings: { enabled: true, charges: { room_fee: { mxik: '10202002001000000', unit: null } } } });
  });

  it('carries the 422 of a TIN that is not nine digits', async () => {
    mockFetch({ 'PUT /api/v1/owner/fiscal/settings': () => json({ success: false, message: 'Xato', errors: { tin: ["STIR 9 ta raqamdan iborat bo'lishi kerak."] } }, 422) });
    const error = await saveFiscalSettings(testApi(), { tin: '12' }).catch((e: unknown) => e);
    expect(getApiFieldError(error, 'tin')).toContain('9 ta raqam');
  });

  it('lists receipts with the filters in the query, shows one and retries one', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/fiscal/receipts': () => ok({ data: [{ uuid: 'r1', status: 'failed' }], current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 }),
      'GET /api/v1/owner/fiscal/receipts/r1': () => ok({ uuid: 'r1', status: 'failed', error: 'timeout' }),
      'POST /api/v1/owner/fiscal/receipts/r1/retry': () => ok({ uuid: 'r1', status: 'pending' }),
    });
    const api = testApi();
    const page = await fetchFiscalReceipts(api, { status: 'failed', type: 'sale', branch_id: 2, from: '2026-10-01', to: '2026-10-22', page: 1 });
    expect(page.total).toBe(1);
    const url = new URL(calls[0]!.url);
    expect(Object.fromEntries(url.searchParams)).toEqual({ status: 'failed', type: 'sale', branch_id: '2', from: '2026-10-01', to: '2026-10-22', page: '1' });
    expect((await fetchFiscalReceipt(api, 'r1')).error).toBe('timeout');
    expect((await retryFiscalReceipt(api, 'r1')).status).toBe('pending');
  });

  it('says already issued when the retry is refused', async () => {
    mockFetch({ 'POST /api/v1/owner/fiscal/receipts/r2/retry': () => json({ success: false, message: 'Chek allaqachon yuborilgan.', data: { reason: 'already_issued' } }, 422) });
    const error = (await retryFiscalReceipt(testApi(), 'r2').catch((e: unknown) => e)) as { reason?: string; message: string };
    expect(error.reason).toBe('already_issued');
  });
});
