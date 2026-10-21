import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { ok, salesReport, todayAnalytics, topProducts } from './fixtures';
import { ReportsView } from './ReportsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
const saveBlob = vi.hoisted(() => vi.fn());
const scope = vi.hoisted(() => ({ branchId: null as number | null }));
vi.mock('../shell/BranchScope', () => ({ useBranchScope: () => ({ branchId: scope.branchId, branchName: (id: number | null) => (id === 2 ? 'Yunusobod' : null) }) }));
vi.mock('./download', () => ({ saveBlob }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: { id: 7, name: 'Aziz', role: 'owner' } }));
afterEach(() => {
  setSession(null);
  saveBlob.mockReset();
  scope.branchId = null;
});

const params = (request: Request) => Object.fromEntries(new URL(request.url).searchParams);
const reportCalls = (calls: Request[], name: string) => calls.filter((call) => new URL(call.url).pathname === `/api/v1/owner/reports/${name}`);

function routes(over: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return {
    'GET /api/v1/owner/analytics': () => json(ok(todayAnalytics)),
    'GET /api/v1/owner/reports/sales': () => json(ok(salesReport)),
    'GET /api/v1/owner/reports/top-products': () => json(ok(topProducts)),
    ...over,
  };
}

describe('ReportsView', () => {
  it('asks for the last 7 days of the STORE\'s calendar and shows the server\'s figures untouched', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<ReportsView />);

    // KPIs: the very numbers of the API, through the shared formatters (non-breaking spaces match as plain ones)
    expect(await screen.findByText("1 250 000 so'm")).toBeInTheDocument(); // revenue
    expect(screen.getByText("41 667 so'm")).toBeInTheDocument(); // average check
    expect(screen.getByText('42')).toBeInTheDocument(); // orders
    expect(screen.getByText('Yetkazildi: 30')).toBeInTheDocument();
    expect(screen.getByText('Bekor qilingan')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument(); // canceled
    expect(screen.getByText('Qaytarilgan: 1')).toBeInTheDocument();

    // The period is counted back from the day the SERVER calls today (period.from of the analytics answer)
    expect(params(reportCalls(calls, 'sales')[0]!)).toEqual({ from: '2026-10-15', to: '2026-10-21', group_by: 'day' });
    expect(params(reportCalls(calls, 'top-products')[0]!)).toEqual({ from: '2026-10-15', to: '2026-10-21', limit: '10', sort: 'quantity' });
    expect(calls.find((call) => new URL(call.url).pathname.endsWith('/sales'))?.headers.get('Authorization')).toBe('Bearer token');
    expect(screen.getByText(/Asia\/Tashkent/)).toBeInTheDocument();
  });

  it('shows the breakdowns by channel, branch, payment method and order type, and the best sellers', async () => {
    mockFetch(routes());
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");

    const section = (name: string) => within(screen.getByRole('region', { name }));
    expect(section("Kanal bo'yicha").getByText('Telegram')).toBeInTheDocument();
    expect(section("Kanal bo'yicha").getByText("900 000 so'm")).toBeInTheDocument();
    expect(section("Kanal bo'yicha").getByText('Zal (POS)')).toBeInTheDocument();
    expect(section("Filial bo'yicha").getByText('Chilonzor')).toBeInTheDocument();
    expect(section("Filial bo'yicha").getByText('Filialsiz')).toBeInTheDocument(); // a null branch name
    expect(section("To'lov usuli bo'yicha").getByText('Naqd pul')).toBeInTheDocument();
    expect(section("To'lov usuli bo'yicha").getByText("550 000 so'm")).toBeInTheDocument();
    expect(section("Buyurtma turi bo'yicha").getAllByRole('listitem')).toHaveLength(2);

    const table = within(screen.getByRole('table', { name: "Eng ko'p sotilgan taomlar" }));
    expect(table.getByText('Osh')).toBeInTheDocument();
    expect(table.getByText('540 000 so\'m')).toBeInTheDocument();
    expect(table.getByText('2,5')).toBeInTheDocument(); // a weight keeps its fraction
  });

  it('draws one bar per bucket with the server\'s revenue, and the same rows as a table', async () => {
    mockFetch(routes());
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");

    const group = screen.getByRole('group', { name: 'Tushum grafigi: 7 ta davr' });
    const bars = within(group).getAllByRole('button');
    expect(bars).toHaveLength(7);
    expect(bars[1]).toHaveAccessibleName(expect.stringMatching(/300\s000\sso'm/));
    fireEvent.mouseEnter(bars[1]!);
    expect(screen.getByText(/300 000 so'm · 8 ta buyurtma/)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Jadval' }));
    const rows = within(screen.getByRole('table', { name: 'Tushum dinamikasi' })).getAllByRole('row');
    expect(rows).toHaveLength(8); // header + 7 days
    expect(within(rows[1]!).getByText("150 000 so'm")).toBeInTheDocument();
  });

  it('asks again when the period or the grouping changes', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");

    await userEvent.click(screen.getByRole('button', { name: 'Kecha' }));
    await waitFor(() => expect(reportCalls(calls, 'sales')).toHaveLength(2));
    expect(params(reportCalls(calls, 'sales')[1]!)).toMatchObject({ from: '2026-10-20', to: '2026-10-20' });

    await userEvent.click(screen.getByRole('button', { name: 'Shu oy' }));
    await waitFor(() => expect(reportCalls(calls, 'sales')).toHaveLength(3));
    expect(params(reportCalls(calls, 'sales')[2]!)).toMatchObject({ from: '2026-10-01', to: '2026-10-21' });

    await userEvent.selectOptions(screen.getByLabelText('Guruhlash'), 'week');
    await waitFor(() => expect(reportCalls(calls, 'sales')).toHaveLength(4));
    expect(params(reportCalls(calls, 'sales')[3]!)).toMatchObject({ group_by: 'week' });
  });

  it('follows the branch chosen in the top bar of the panel', async () => {
    scope.branchId = 2;
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/reports/export': () => new Response('a,b') }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    expect(params(reportCalls(calls, 'sales')[0]!)).toMatchObject({ branch_id: '2' });
    expect(params(reportCalls(calls, 'top-products')[0]!)).toMatchObject({ branch_id: '2' });
    expect(screen.getByText(/Filial: Yunusobod/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalled());
    expect(params(calls.find((call) => new URL(call.url).pathname.endsWith('/export'))!)).toMatchObject({ branch_id: '2' });
  });

  it('sorts the best sellers by revenue on the server', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    await userEvent.click(screen.getByRole('button', { name: 'Tushum bo\'yicha' }));
    await waitFor(() => expect(reportCalls(calls, 'top-products')).toHaveLength(2));
    expect(params(reportCalls(calls, 'top-products')[1]!)).toMatchObject({ sort: 'revenue' });
  });

  it('takes a custom period, and does not ask the server for one it would refuse', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");

    await userEvent.click(screen.getByRole('button', { name: 'Boshqa davr' }));
    // starts from the period that was on screen
    expect(screen.getByLabelText('Boshlanish sanasi')).toHaveValue('2026-10-15');
    const before = reportCalls(calls, 'sales').length;

    fireEvent.change(screen.getByLabelText('Tugash sanasi'), { target: { value: '2026-10-10' } });
    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-10-20' } });
    expect(await screen.findByRole('alert')).toHaveTextContent("Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.");
    expect(screen.queryByText("1 250 000 so'm")).not.toBeInTheDocument(); // the old report is not shown for a period that is not valid
    expect(reportCalls(calls, 'sales').filter((call) => params(call).from === '2026-10-20')).toHaveLength(0);

    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2025-01-01' } });
    expect(await screen.findByRole('alert')).toHaveTextContent("Davr ko'pi bilan 366 kun bo'lishi mumkin.");

    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-10-01' } });
    await waitFor(() => expect(reportCalls(calls, 'sales').length).toBeGreaterThan(before));
    expect(params(reportCalls(calls, 'sales').at(-1)!)).toMatchObject({ from: '2026-10-01', to: '2026-10-10' });
  });

  it('counts the days in the store\'s own timezone, not the browser\'s', async () => {
    const tokyo = { ...todayAnalytics, period: { ...todayAnalytics.period, from: '2026-10-22', to: '2026-10-22', timezone: 'Asia/Tokyo' } };
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/analytics': () => json(ok(tokyo)) }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    expect(params(reportCalls(calls, 'sales')[0]!)).toMatchObject({ from: '2026-10-16', to: '2026-10-22' });
  });

  it('falls back to the default timezone\'s day when the analytics request fails', async () => {
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/analytics': () => json({ message: 'boom' }, 500) }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    const { from, to } = params(reportCalls(calls, 'sales')[0]!);
    expect(from).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(to).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('says so when the period has no orders', async () => {
    const empty = { ...salesReport, summary: { ...salesReport.summary, orders_count: 0, delivered_count: 0, canceled_count: 0, revenue: '0.00', average_check: '0.00' }, series: [] };
    mockFetch(routes({ 'GET /api/v1/owner/reports/sales': () => json(ok(empty)) }));
    renderPage(<ReportsView />);
    expect(await screen.findByText("Bu davrda buyurtma yo'q")).toBeInTheDocument();
  });

  it('shows an inline error with a retry when the report fails', async () => {
    let fail = true;
    mockFetch(routes({ 'GET /api/v1/owner/reports/sales': () => (fail ? json({ message: 'boom' }, 500) : json(ok(salesReport))) }));
    renderPage(<ReportsView />);
    expect(await screen.findByRole('alert')).toHaveTextContent("Ma'lumot yuklanmadi");
    fail = false;
    await userEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByText("1 250 000 so'm")).toBeInTheDocument();
  });

  it('says what the server said when the caller may not see reports', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/reports/sales': () => json({ success: false, message: 'Ruxsat etilmagan', data: [] }, 403) }));
    renderPage(<ReportsView />);
    expect(await screen.findByText('Ruxsat etilmagan')).toBeInTheDocument();
  });

  it('renders in Russian', async () => {
    mockFetch(routes());
    renderPage(<ReportsView />, 'ru');
    expect(await screen.findByText('1 250 000 сум')).toBeInTheDocument();
    expect(screen.getAllByText('Выручка').length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Вчера' })).toBeInTheDocument();
  });
});

describe('CSV export', () => {
  const csvResponse = () => new Response('﻿a,b', { headers: { 'Content-Type': 'text/csv; charset=UTF-8', 'Content-Disposition': 'attachment; filename=sales_2026-10-15_2026-10-21.csv' } });

  it('downloads the sales file of the period through the session fetch and saves it', async () => {
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/reports/export': csvResponse }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");

    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(1));
    const exportCall = calls.find((call) => new URL(call.url).pathname.endsWith('/export'))!;
    expect(params(exportCall)).toEqual({ report: 'sales', from: '2026-10-15', to: '2026-10-21', group_by: 'day' });
    expect(exportCall.headers.get('Authorization')).toBe('Bearer token');
    expect(saveBlob.mock.calls[0]![1]).toBe('sales_2026-10-15_2026-10-21.csv');
    expect(await screen.findByRole('status')).toHaveTextContent('Fayl yuklab olindi: sales_2026-10-15_2026-10-21.csv');
  });

  it('asks for the file kind chosen: orders take neither grouping nor sort, products take the sort', async () => {
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/reports/export': csvResponse }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    const kind = screen.getByLabelText('Fayl turi');

    await userEvent.selectOptions(kind, 'orders');
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(1));
    await userEvent.selectOptions(kind, 'products');
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(2));

    const exports = calls.filter((call) => new URL(call.url).pathname.endsWith('/export')).map(params);
    expect(exports[0]).toEqual({ report: 'orders', from: '2026-10-15', to: '2026-10-21' });
    expect(exports[1]).toEqual({ report: 'products', from: '2026-10-15', to: '2026-10-21', sort: 'quantity' });
  });

  it('says too many rows with the limit the server names, and saves nothing', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/reports/export': () => json({ success: false, message: 'ko\'p', data: { reason: 'too_many_rows', limit: 50000 } }, 422) }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    expect(await screen.findByRole('alert')).toHaveTextContent("Bu davrda buyurtmalar juda ko'p (50000 tadan ortiq). Qisqaroq davr tanlang.");
    expect(saveBlob).not.toHaveBeenCalled();
  });

  it('says how long to wait when the rate limit is hit', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/reports/export': () => json({ success: false, message: 'Too many' }, 429, { 'Retry-After': '37' }) }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    expect(await screen.findByRole('alert')).toHaveTextContent("37 soniyadan so'ng qayta urinib ko'ring");
  });

  it('says it without a time when the server gave none, and says the server\'s validation text for a bad period', async () => {
    let answer: Response = json({ success: false, message: 'Too many' }, 429);
    mockFetch(routes({ 'GET /api/v1/owner/reports/export': () => answer.clone() }));
    renderPage(<ReportsView />);
    await screen.findByText("1 250 000 so'm");
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('daqiqada 6 ta, kuniga 100 ta');

    answer = json({ message: 'x', errors: { to: ["Davr ko'pi bilan 93 kun bo'lishi mumkin."] } }, 422);
    await userEvent.click(screen.getByRole('button', { name: 'CSV yuklab olish' }));
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent("Davr ko'pi bilan 93 kun bo'lishi mumkin."));
  });
});
