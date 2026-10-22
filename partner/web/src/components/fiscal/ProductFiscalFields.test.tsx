import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { ProductEditor } from '../menu/ProductEditor';
import { category, ok, permissionsAnswer, product, variant } from '../menu/menuFixtures';
import { checkFiscalDraft, emptyFiscalDraft, fiscalChanges, fiscalDraftFrom } from './productFiscal';
import { createTranslator } from '@/i18n';

const router = vi.hoisted(() => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }));
vi.mock('next/navigation', () => ({ useRouter: () => router }));
vi.mock('next/link', () => ({ default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a> }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => {
  setSession(null);
  router.replace.mockReset();
});

const MXIK = '10202001001000000';

const base = (extra: Parameters<typeof mockFetch>[0] = {}, data = product({ id: 7, mxik_code: null, kind: 'dish', variants: [variant({ id: 11 })] })) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissionsAnswer(),
    'GET /api/v1/owner/categories': () => json({ success: true, data: [category()] }),
    'GET /api/v1/owner/products': () => ok([data]),
    ...extra,
  });

describe('the fiscal part of the product form', () => {
  it('shows the stored kind, MXIK, package code and VAT, and the unit on the receipt of a variant', async () => {
    base({}, product({ id: 7, kind: 'service', mxik_code: MXIK, package_code: '1234567', vat_rate: '12.00', variants: [variant({ id: 11, unit_label: 'kg (guruch)' })] }));
    renderPage(<ProductEditor id="7" />);
    expect(await screen.findByLabelText('Turi')).toHaveValue('service');
    expect(screen.getByLabelText('MXIK kodi')).toHaveValue(MXIK);
    expect(screen.getByLabelText('Qadoq kodi')).toHaveValue('1234567');
    expect(screen.getByLabelText('QQS stavkasi (%)')).toHaveValue('12');
    expect(screen.getByLabelText('Chekdagi birlik nomi')).toHaveValue('kg (guruch)');
  });

  it('refuses a short MXIK, a package code with letters and a VAT over 100 before calling the server', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<ProductEditor id="7" />);
    await typing.type(await screen.findByLabelText('MXIK kodi'), '12345');
    await typing.type(screen.getByLabelText('Qadoq kodi'), 'abc');
    await typing.type(screen.getByLabelText('QQS stavkasi (%)'), '120');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")).toBeInTheDocument();
    expect(screen.getByText("Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).")).toBeInTheDocument();
    expect(screen.getByText('QQS stavkasi 0 dan 100 gacha son bo\'lishi kerak.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it('sends the fiscal fields that changed, and leaves out the ones that did not', async () => {
    const { calls } = base({ 'POST /api/v1/owner/products/7': () => ok(product({ id: 7, mxik_code: MXIK })) });
    const typing = userEvent.setup();
    renderPage(<ProductEditor id="7" />);
    await typing.type(await screen.findByLabelText('MXIK kodi'), MXIK);
    await typing.selectOptions(screen.getByLabelText('Turi'), 'service');
    await typing.type(screen.getByLabelText("Chekdagi birlik nomi"), 'porsiya');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('mxik_code')).toBe(MXIK);
    expect(form.get('kind')).toBe('service');
    expect(form.has('package_code')).toBe(false);
    expect(form.has('vat_rate')).toBe(false);
    expect(JSON.parse(String(form.get('variants')))[0]).toMatchObject({ id: 11, unit_label: 'porsiya' });
  });

  it('does not touch the fiscal fields of a product saved without changing them', async () => {
    const { calls } = base({ 'POST /api/v1/owner/products/7': () => ok(product({ id: 7 })) }, product({ id: 7, mxik_code: MXIK, kind: 'dish' }));
    const typing = userEvent.setup();
    renderPage(<ProductEditor id="7" />);
    await typing.clear(await screen.findByLabelText('Nomi'));
    await typing.type(screen.getByLabelText('Nomi'), 'Osh yangi');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    for (const field of ['kind', 'mxik_code', 'package_code', 'vat_rate']) expect(form.has(field)).toBe(false);
  });

  it('clears a code that was emptied (null reaches the server as an empty field)', async () => {
    const { calls } = base({ 'POST /api/v1/owner/products/7': () => ok(product({ id: 7 })) }, product({ id: 7, mxik_code: MXIK, package_code: '123' }));
    const typing = userEvent.setup();
    renderPage(<ProductEditor id="7" />);
    await typing.clear(await screen.findByLabelText('Qadoq kodi'));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('package_code')).toBe('');
    expect(form.has('mxik_code')).toBe(false);
  });

  it('shows the server\'s refusal of a code under its field', async () => {
    base({ 'POST /api/v1/owner/products/7': () => json({ message: 'Xato', errors: { mxik_code: ['Bu MXIK tasnifda yo\'q.'] } }, 422) });
    const typing = userEvent.setup();
    renderPage(<ProductEditor id="7" />);
    await typing.type(await screen.findByLabelText('MXIK kodi'), MXIK);
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Bu MXIK tasnifda yo'q.")).toBeInTheDocument();
    expect(screen.getByLabelText('MXIK kodi')).toHaveAttribute('aria-invalid', 'true');
  });
});

describe('fiscal draft helpers', () => {
  const t = createTranslator('uz');

  it('reads a product, a missing one and an older answer without fiscal fields', () => {
    expect(fiscalDraftFrom({ kind: 'service', mxik_code: MXIK, package_code: '12', vat_rate: '12.00' })).toEqual({ kind: 'service', mxik: MXIK, packageCode: '12', vat: '12' });
    expect(fiscalDraftFrom(null)).toEqual(emptyFiscalDraft());
    expect(fiscalDraftFrom({})).toEqual(emptyFiscalDraft());
  });

  it('checks codes and VAT, and accepts empty ones', () => {
    expect(checkFiscalDraft(emptyFiscalDraft(), t)).toEqual({});
    expect(Object.keys(checkFiscalDraft({ kind: 'dish', mxik: '1', packageCode: 'x', vat: '-1' }, t)).sort()).toEqual(['mxik_code', 'package_code', 'vat_rate']);
    expect(checkFiscalDraft({ kind: 'dish', mxik: MXIK, packageCode: '1234', vat: '12,5' }, t)).toEqual({});
  });

  it('lists only the changes', () => {
    const initial = fiscalDraftFrom({ kind: 'dish', mxik_code: MXIK, package_code: null, vat_rate: null });
    expect(fiscalChanges(initial, initial)).toEqual({});
    expect(fiscalChanges(initial, { ...initial, vat: '12,5', packageCode: '77', kind: 'service' })).toEqual({ kind: 'service', package_code: '77', vat_rate: 12.5 });
    expect(fiscalChanges(initial, { ...initial, mxik: '' })).toEqual({ mxik_code: null });
  });
});
