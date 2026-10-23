import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, report, shift, signIn } from '../pos/testSupport';
import { ShiftsView } from './ShiftsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => {
  setSession(null);
  vi.restoreAllMocks();
});

const Z = shift();
const OPEN = shift({ uuid: 's2', status: 'open', z_number: null, closed_at: null, closed_by: null, expected_cash: null, counted_cash: null, counted_breakdown: null, difference: null, business_date: '2026-10-22', device: { uuid: 'dev-2', name: 'Kassa 2' } });

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/pos/shifts': () => ok([OPEN, Z]),
    [`GET /api/v1/pos/shifts/${Z.uuid}`]: () => ok({ ...Z, summary: report() }),
    [`GET /api/v1/pos/shifts/${OPEN.uuid}`]: () => ok({ ...OPEN, summary: null }),
    [`GET /api/v1/pos/shifts/${OPEN.uuid}/x-report`]: () => ok({ shift: OPEN, report: report({ cash: { ...report().cash, expected: '900000.00' }, period: { from: OPEN.opened_at, to: null } }) }),
    ...over,
  });

describe('the shift list', () => {
  it('lists the shifts with their Z number, till, expected and counted cash and the difference in words', async () => {
    base();
    renderPage(<ShiftsView />);
    const table = await screen.findByRole('table', { name: 'Smenalar' });
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]!).getByText('Ochiq')).toBeInTheDocument();
    expect(within(rows[1]!).getByText('Kassa 2')).toBeInTheDocument();
    expect(within(rows[2]!).getByText('12')).toBeInTheDocument();
    expect(within(rows[2]!).getByText("1 750 000 so'm")).toBeInTheDocument();
    expect(within(rows[2]!).getByText("1 740 000 so'm")).toBeInTheDocument();
    expect(within(rows[2]!).getByText("Kam: 10 000 so'm")).toBeInTheDocument();
  });

  it('asks the server for the shifts of a status and of business days', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<ShiftsView />);
    await typing.selectOptions(await screen.findByLabelText('Holati'), 'closed');
    await typing.type(screen.getByLabelText('Boshlanish sanasi'), '2026-10-01');
    await typing.type(screen.getByLabelText('Tugash sanasi'), '2026-10-22');
    await waitFor(() => {
      const last = new URL(calls.filter((call) => new URL(call.url).pathname === '/api/v1/pos/shifts').at(-1)!.url).searchParams;
      expect(Object.fromEntries(last)).toEqual({ status: 'closed', from: '2026-10-01', to: '2026-10-22', limit: '50' });
    });
  });

  it('says so when there are none', async () => {
    base({ 'GET /api/v1/pos/shifts': () => ok([]) });
    renderPage(<ShiftsView />);
    expect(await screen.findByText("Smena yo'q")).toBeInTheDocument();
  });

  it("shows the server's 403 and a retry when the list fails", async () => {
    base({ 'GET /api/v1/pos/shifts': () => json({ success: false, message: 'Ruxsat etilmagan' }, 403) });
    renderPage(<ShiftsView />);
    expect(await screen.findByText('Ruxsat etilmagan')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  it('is for those who close shifts or read reports: another role is told so', async () => {
    base({}, ['orders.view']);
    renderPage(<ShiftsView />);
    expect(await screen.findByText("Smenalarni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });

  it('opens for a person with only reports.view', async () => {
    base({}, ['reports.view']);
    renderPage(<ShiftsView />);
    expect(await screen.findByRole('table')).toBeInTheDocument();
  });
});

describe('the Z report', () => {
  const openZ = async (over: Parameters<typeof mockFetch>[0] = {}) => {
    const mocked = base(over);
    const typing = userEvent.setup();
    renderPage(<ShiftsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisobotni ochish: 2026-10-21, Z 12' }));
    const article = await screen.findByRole('article', { name: 'Z-hisobot № 12' });
    return { ...mocked, typing, article };
  };

  it('shows the figures of the closed shift: tenders, channels, waiters, deposits, credit, cash and the difference', async () => {
    const { article, calls } = await openZ();
    // sales
    expect(within(article).getByText('Sof tushum')).toBeInTheDocument();
    expect(within(article).getByText("3 150 000 so'm")).toBeInTheDocument();
    // tenders and cards
    const tenders = within(article).getByRole('table', { name: "To'lov turlari" });
    expect(within(tenders).getByText('Naqd')).toBeInTheDocument();
    expect(within(tenders).getByText('Avans hisobidan')).toBeInTheDocument();
    expect(within(tenders).getByText('Nasiyaga')).toBeInTheDocument();
    expect(within(tenders).getByText('↳ karta: uzcard')).toBeInTheDocument();
    // channels and waiters
    expect(within(within(article).getByRole('table', { name: 'Kanallar' })).getByText('Zal')).toBeInTheDocument();
    expect(within(within(article).getByRole('table', { name: 'Ofitsiantlar' })).getByText('Ofitsiant Vali')).toBeInTheDocument();
    // deposits, credit
    expect(within(article).getByText("400 000 so'm (2)")).toBeInTheDocument();
    expect(within(article).getByText("80 000 so'm (1)")).toBeInTheDocument();
    // cash
    expect(within(article).getByText("1 730 000 so'm")).toBeInTheDocument();
    expect(within(article).getByText("1 740 000 so'm")).toBeInTheDocument();
    expect(within(article).getByText("Kam: 10 000 so'm")).toBeInTheDocument();
    expect(within(article).getByText(/Kupyuralar: 100 000 × 17, 10 000 × 4/)).toBeInTheDocument();
    // a cash movement
    expect(within(within(article).getByRole('table', { name: 'Kassa harakatlari' })).getByText('Bozorlik')).toBeInTheDocument();
    // a closed shift with its Z is read once; no X report is asked for
    expect(calls.some((call) => call.url.includes('x-report'))).toBe(false);
  });

  it('shows "no difference" and "over" in words too', async () => {
    const { article, typing } = await openZ({ [`GET /api/v1/pos/shifts/${Z.uuid}`]: () => ok({ ...Z, difference: '0.00', summary: report() }) });
    expect(within(article).getByText("Farq yo'q")).toBeInTheDocument();
    void typing;
  });

  it('prints with the browser, and goes back to the list', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => {});
    const { typing } = await openZ();
    await typing.click(screen.getByRole('button', { name: 'Chop etish' }));
    expect(print).toHaveBeenCalledTimes(1);
    await typing.click(screen.getByRole('button', { name: "Ro'yxatga" }));
    expect(await screen.findByRole('table', { name: 'Smenalar' })).toBeInTheDocument();
  });
});

describe('the X report of an open shift', () => {
  it('reads the report now, with no counted cash yet', async () => {
    base();
    const typing = userEvent.setup();
    renderPage(<ShiftsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisobotni ochish: 2026-10-22, Z —' }));
    const article = await screen.findByRole('article', { name: 'X-hisobot (smena ochiq)' });
    expect(within(article).getByText("900 000 so'm")).toBeInTheDocument();
    expect(within(article).getByText(/Smena hali yopilmagan/)).toBeInTheDocument();
    expect(within(article).queryByText('Sanalgan naqd')).not.toBeInTheDocument();
  });

  it("shows the server's refusal on the report page and can retry", async () => {
    base({ [`GET /api/v1/pos/shifts/${OPEN.uuid}/x-report`]: () => json({ success: false, message: 'Smena topilmadi.' }, 404) });
    const typing = userEvent.setup();
    renderPage(<ShiftsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisobotni ochish: 2026-10-22, Z —' }));
    expect(await screen.findByText('Smena topilmadi.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Chop etish' })).toBeDisabled();
  });
});
