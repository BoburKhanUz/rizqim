import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { parseBankRows } from '@rizqim/staff';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { ok, ownerAnswer, POS_OWNER, shift, signIn } from '../pos/testSupport';
import { FiscalView } from './FiscalView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
vi.mock('next/link', () => ({ default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a> }));

beforeEach(signIn);
afterEach(() => setSession(null));

const item = (key: string, done = false) => ({ key, label: key, hint: key, done, done_at: done ? '2026-10-29T08:00:00+05:00' : null, done_by: done ? { id: 1, name: 'Ega Aziz' } : null });

const setup = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  card_mode: null,
  card_mode_label: null,
  checklist: [item('old_kassa_unused'), item('bank_terminal_mode'), item('scanner_2d')],
  checklist_complete: false,
  difference_threshold: '0.00',
  ask_bank_total: true,
  updated_at: null,
  ...over,
});

const till = (over: Record<string, unknown> = {}) => ({ uuid: 'd-1', name: 'Kassa 1', branch_id: 1, smart_kassa: false, fiscal_kassa_id: null, kassa_status: null, last_seen_at: null, ...over });

const overview = (branches: unknown[] = [{ branch: { id: 1, name: 'Chilonzor' }, module_enabled: true, setup: setup(), devices: [till()] }]) => ({
  fiscal: { enabled: true, active_driver: 'sandbox', smart_kassa_driver: 'sandbox' },
  card_modes: [],
  checklist_items: [],
  branches,
});

const bankReport = (over: Record<string, unknown> = {}) => ({
  uuid: 'b-1',
  shift_uuid: 's0000000-0000-4000-8000-000000000001',
  z_number: 12,
  branch_id: 1,
  business_date: '2026-10-28',
  source: 'manual',
  terminal_ref: null,
  bank_total: '1200000.00',
  bank_count: 9,
  rizqim_total: '1250000.00',
  rizqim_count: 10,
  rizqim_breakdown: { payments: '1250000.00', deposits: '0.00', repayments: '0.00', refunds: '0.00', deposit_refunds: '0.00' },
  difference: '-50000.00',
  threshold: '0.00',
  over_threshold: true,
  transactions: null,
  unmatched: null,
  note: null,
  entered_by: { id: 1, name: 'Ega Aziz' },
  alerted_at: '2026-10-28T23:00:00+05:00',
  created_at: null,
  updated_at: null,
  ...over,
});

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys: string[] = [...POS_OWNER, 'finance.manage']) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/fiscal/readiness': () => ok({ enabled: true, tin_set: true, tax_regime: 'vat', products_missing_mxik: [], charges_missing_mxik: [] }),
    'GET /api/v1/owner/fiscal/terminal-flow': () => ok(overview()),
    'GET /api/v1/owner/fiscal/bank-reports': () => ok([bankReport()]),
    'GET /api/v1/pos/shifts': () => ok([shift()]),
    ...over,
  });

async function openTab() {
  const typing = userEvent.setup();
  renderPage(<FiscalView />);
  await typing.click(await screen.findByRole('tab', { name: 'Karta terminali' }));
  return typing;
}

describe('Karta terminali: the card flow of each branch', () => {
  it('saves only what changed: the card mode, a ticked checklist item and the threshold', async () => {
    const { calls } = base({
      'PUT /api/v1/owner/branches/1/terminal-flow': () => ok(setup({ card_mode: 'terminal_only', checklist: [item('old_kassa_unused', true), item('bank_terminal_mode'), item('scanner_2d')], difference_threshold: '1000.00' })),
    });
    const typing = await openTab();
    const branch = await screen.findByRole('form', { name: 'Chilonzor' });
    expect(within(branch).getByText("O'rnatish tugallanmagan")).toBeInTheDocument();

    await typing.selectOptions(within(branch).getByLabelText('Karta qanday qabul qilinadi'), 'terminal_only');
    await typing.click(within(branch).getByRole('checkbox', { name: /Eski kassa sotuvda ishlatilmaydi/ }));
    const threshold = within(branch).getByLabelText("Farq chegarasi (so'm)");
    await typing.clear(threshold);
    await typing.type(threshold, '1 000');
    await typing.click(within(branch).getByRole('button', { name: 'Saqlash' }));

    expect(await within(branch).findByText('Saqlandi.')).toBeInTheDocument();
    const put = calls.find((call) => call.method === 'PUT')!;
    expect(await put.json()).toEqual({ card_mode: 'terminal_only', checklist: { old_kassa_unused: true }, difference_threshold: '1000' });
    expect(within(branch).getByText(/Ega Aziz/)).toBeInTheDocument();
  });

  it('marks the branch as sharing one bank terminal (review P6 C#M2)', async () => {
    const { calls } = base({ 'PUT /api/v1/owner/branches/1/terminal-flow': () => ok(setup({ shared_terminal: true })) });
    const typing = await openTab();
    const branch = await screen.findByRole('form', { name: 'Chilonzor' });
    const shared = within(branch).getByRole('checkbox', { name: /Umumiy bank terminali/ });
    expect(shared).not.toBeChecked();
    await typing.click(shared);
    await typing.click(within(branch).getByRole('button', { name: 'Saqlash' }));
    expect(await within(branch).findByText('Saqlandi.')).toBeInTheDocument();
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ shared_terminal: true });
    expect(within(branch).getByRole('checkbox', { name: /Umumiy bank terminali/ })).toBeChecked();
  });

  it('refuses a threshold that is not an amount before anything is sent', async () => {
    const { calls } = base();
    const typing = await openTab();
    const branch = await screen.findByRole('form', { name: 'Chilonzor' });
    const threshold = within(branch).getByLabelText("Farq chegarasi (so'm)");
    await typing.clear(threshold);
    await typing.type(threshold, 'ko‘p');
    await typing.click(within(branch).getByRole('button', { name: 'Saqlash' }));
    expect(await within(branch).findByText("Chegarani so'mda kiriting (masalan 1000).")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('says so when the branch format has the flow off', async () => {
    base({ 'GET /api/v1/owner/fiscal/terminal-flow': () => ok(overview([{ branch: { id: 2, name: 'Yunusobod' }, module_enabled: false, setup: setup({ branch_id: 2 }), devices: [] }])) });
    await openTab();
    expect(await screen.findByText(/Bu filial formatida bank terminali oqimi o'chiq/)).toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Yunusobod' })).not.toBeInTheDocument();
  });

  it('marks a till as a smart kassa with its provider kassa id', async () => {
    const { calls } = base({ 'PUT /api/v1/owner/fiscal/devices/d-1': () => ok(till({ smart_kassa: true, fiscal_kassa_id: 'RHMT-1', kassa_status: { registered: true, kassa_id: 'RHMT-1', message: null } })) });
    const typing = await openTab();
    const row = await screen.findByRole('form', { name: 'Kassa 1: smart-kassa' });
    await typing.click(within(row).getByRole('checkbox', { name: /Smart-kassa/ }));
    await typing.type(within(row).getByLabelText('Provayder kassa raqami'), 'RHMT-1');
    await typing.click(within(row).getByRole('button', { name: 'Saqlash' }));
    expect(await within(row).findByText('Provayderda ulangan')).toBeInTheDocument();
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ smart_kassa: true, fiscal_kassa_id: 'RHMT-1' });
  });
});

describe('Karta terminali: the bank terminal against the Z', () => {
  it('lists the reports with the mismatch marked and filters to the mismatches', async () => {
    const { calls } = base();
    const typing = await openTab();
    const table = await screen.findByRole('table', { name: 'Bank terminali va Z' });
    expect(within(table).getByText('Z #12')).toBeInTheDocument();
    expect(within(table).getByText('Mos emas')).toBeInTheDocument();
    await typing.click(screen.getByRole('checkbox', { name: 'Faqat farq bor smenalar' }));
    await vi.waitFor(() => expect(calls.some((call) => call.url.includes('over_threshold=1'))).toBe(true));
  });

  it("shows a shared terminal's day report as pending until every shift closes, and a corrected alert as an alert", async () => {
    base({
      'GET /api/v1/owner/fiscal/bank-reports': () =>
        ok([
          bankReport({
            uuid: 'b-2',
            shared: true,
            final: false,
            shifts: [
              { uuid: 's-a', z_number: 12, status: 'closed' },
              { uuid: 's-b', z_number: null, status: 'open' },
            ],
            rizqim_total: null,
            difference: null,
            threshold: null,
            over_threshold: null,
            alerted_at: null,
          }),
          bankReport({ uuid: 'b-1', final: true, difference: '0.00', over_threshold: true, history: [{ source: 'manual', bank_total: '1200000.00', bank_count: null, difference: '-50000.00', over_threshold: true, entered_by: 1, entered_at: null }] }),
        ]),
    });
    await openTab();
    const table = await screen.findByRole('table', { name: 'Bank terminali va Z' });
    const [, pending, corrected] = within(table).getAllByRole('row');
    expect(within(pending!).getByText('Umumiy terminal: 2 ta smena')).toBeInTheDocument();
    expect(within(pending!).getByText('Z #12, Ochiq smena')).toBeInTheDocument();
    expect(within(pending!).getByText('Kutilmoqda')).toBeInTheDocument();
    expect(within(pending!).getAllByText('—')).toHaveLength(2);
    expect(within(corrected!).getByText('Mos emas')).toBeInTheDocument();
    expect(within(corrected!).getByText('Tuzatilgan (1)')).toBeInTheDocument();
  });

  it('enters the pasted transactions of a closed shift and shows the answer', async () => {
    const { calls } = base({ 'PUT /api/v1/owner/fiscal/bank-reports/s0000000-0000-4000-8000-000000000001': () => ok(bankReport({ source: 'import', over_threshold: false, difference: '0.00' })) });
    const typing = await openTab();
    const form = await screen.findByRole('form', { name: 'Bank terminali summasini kiritish' });
    await vi.waitFor(() => expect(within(form).getAllByRole('option').length).toBeGreaterThan(1));
    await typing.selectOptions(within(form).getByLabelText('Smena'), 's0000000-0000-4000-8000-000000000001');
    await typing.selectOptions(within(form).getByLabelText('Qanday kiritiladi'), 'import');
    await typing.type(within(form).getByLabelText('Terminal tranzaksiyalari'), '000123;600000{enter}bad;x{enter}');
    await typing.click(within(form).getByRole('button', { name: 'Solishtirish' }));
    expect(await within(form).findByText("Bu qatorlarda summa noto'g'ri: 2.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);

    await typing.clear(within(form).getByLabelText('Terminal tranzaksiyalari'));
    await typing.type(within(form).getByLabelText('Terminal tranzaksiyalari'), '000123;600000{enter}650000');
    await typing.click(within(form).getByRole('button', { name: 'Solishtirish' }));
    const put = await vi.waitFor(() => calls.find((call) => call.method === 'PUT')!);
    expect(await put.json()).toEqual({ source: 'import', transactions: [{ rrn: '000123', amount: '600000' }, { rrn: null, amount: '650000' }] });
  });

  it('keeps the answer of a correction made from a row of the list', async () => {
    base({ 'PUT /api/v1/owner/fiscal/bank-reports/s0000000-0000-4000-8000-000000000001': () => ok(bankReport({ source: 'manual', over_threshold: false, difference: '0.00' })) });
    const typing = await openTab();
    const table = await screen.findByRole('table', { name: 'Bank terminali va Z' });
    await typing.click(within(table).getByRole('button', { name: /hisobotini tuzatish/ }));
    const form = await screen.findByRole('form', { name: 'Bank terminali summasini kiritish' });
    await vi.waitFor(() => expect(within(form).getByLabelText('Smena')).toHaveValue('s0000000-0000-4000-8000-000000000001'));
    await typing.type(within(form).getByLabelText("Terminal kunlik jami (so'm)"), '1200000');
    await typing.click(within(form).getByRole('button', { name: 'Solishtirish' }));
    // The form starts again after the save; the message stays
    expect(await screen.findByText(/^Saqlandi\. Farq: /)).toBeInTheDocument();
  });

  it('has no entry form for pos.close_shift alone: after the Z only finance.manage corrects (review P6 S#5)', async () => {
    base({}, ['reports.view', 'pos.close_shift']);
    await openTab();
    expect(await screen.findByRole('table', { name: 'Bank terminali va Z' })).toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Bank terminali summasini kiritish' })).not.toBeInTheDocument();
  });

  it('is read-only for someone who reads reports but cannot enter them, and has no setup without settings.manage', async () => {
    base({}, ['reports.view']);
    await openTab();
    expect(await screen.findByRole('table', { name: 'Bank terminali va Z' })).toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Bank terminali summasini kiritish' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tuzatish' })).not.toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Chilonzor' })).not.toBeInTheDocument();
  });
});

describe('parseBankRows', () => {
  it('reads "RRN;amount", tabs, commas or a bare amount, and names the bad lines', () => {
    expect(parseBankRows('123;1000\n\n456\t2 500.50\n 9000 \nx;y')).toEqual({
      rows: [
        { rrn: '123', amount: '1000' },
        { rrn: '456', amount: '2500.50' },
        { rrn: null, amount: '9000' },
      ],
      bad: [5],
    });
  });
});

