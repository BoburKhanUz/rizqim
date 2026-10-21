import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import {
  downloadReport,
  fetchLedger,
  fetchPayments,
  fetchPayouts,
  fetchSalesReport,
  fetchTopProducts,
  fetchWalletHistory,
  ReportDownloadError,
  reportFilename,
} from './reports';
import { isStaffApiError } from './errors';

afterEach(() => vi.unstubAllGlobals());

const params = (request: Request) => Object.fromEntries(new URL(request.url).searchParams);

describe('sales reports', () => {
  it('asks for a period grouped by week and returns the server figures untouched', async () => {
    const report = { period: { from: '2026-10-01', to: '2026-10-21', days: 21, timezone: 'Asia/Tashkent', basis: 'created_at', group_by: 'week' }, summary: { revenue: '1250000.50' }, series: [] };
    const { calls } = mockFetch({ 'GET /api/v1/owner/reports/sales': () => ok(report) });
    const result = await fetchSalesReport(testApi(), { from: '2026-10-01', to: '2026-10-21', group_by: 'week', branch_id: 3 });
    expect(result).toEqual(report);
    expect(params(calls[0]!)).toEqual({ from: '2026-10-01', to: '2026-10-21', group_by: 'week', branch_id: '3' });
  });

  it('asks for the best sellers sorted and limited', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/reports/top-products': () => ok({ products: [{ name: 'Osh', quantity: 1.5, revenue: '45000.00', orders: 2 }] }) });
    const result = await fetchTopProducts(testApi(), { from: '2026-10-01', to: '2026-10-01', sort: 'revenue', limit: 5 });
    expect(result.products[0]?.name).toBe('Osh');
    expect(params(calls[0]!)).toEqual({ from: '2026-10-01', to: '2026-10-01', sort: 'revenue', limit: '5' });
  });

  it('turns a refused report into a StaffApiError with the server message', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/sales': () => json({ message: 'Davr xato', errors: { to: ['Tugash sanasi xato'] } }, 422) });
    const error = await fetchSalesReport(testApi(), { from: '2026-10-05', to: '2026-10-01' }).catch((e: unknown) => e);
    expect(isStaffApiError(error) && error.fieldError('to')).toBe('Tugash sanasi xato');
  });
});

describe('CSV download', () => {
  const csv = '﻿Buyurtma,Sana\r\nA101,2026-10-01\r\n';

  it('downloads with the session fetch and names the file as the server does', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/reports/export': () =>
        new Response(csv, { status: 200, headers: { 'Content-Type': 'text/csv; charset=UTF-8', 'Content-Disposition': 'attachment; filename=orders_2026-10-01_2026-10-07.csv' } }),
    });
    const download = await downloadReport(testApi(), { report: 'orders', from: '2026-10-01', to: '2026-10-07' });
    expect(download.filename).toBe('orders_2026-10-01_2026-10-07.csv');
    expect(download.blob.size).toBe(csv.length > 0 ? new TextEncoder().encode(csv).length : 0);
    expect(await download.blob.text()).toContain('A101');
    expect(params(calls[0]!)).toEqual({ report: 'orders', from: '2026-10-01', to: '2026-10-07' });
  });

  it('reads an encoded file name and falls back to its own when the header is not readable', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/export': () => new Response(csv, { headers: { 'Content-Disposition': "attachment; filename*=UTF-8''hisobot%20oy.csv" } }) });
    expect((await downloadReport(testApi(), { report: 'sales', from: '2026-10-01', to: '2026-10-07' })).filename).toBe('hisobot oy.csv');
    mockFetch({ 'GET /api/v1/owner/reports/export': () => new Response(csv) });
    const query = { report: 'products', from: '2026-10-01', to: '2026-10-07', sort: 'revenue' } as const;
    expect((await downloadReport(testApi(), query)).filename).toBe(reportFilename(query));
    expect(reportFilename(query)).toBe('rizqim-products-2026-10-01_2026-10-07.csv');
  });

  it('passes the report options', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/reports/export': () => new Response(csv) });
    await downloadReport(testApi(), { report: 'sales', from: '2026-10-01', to: '2026-10-07', group_by: 'month', branch_id: 2 });
    expect(params(calls[0]!)).toEqual({ report: 'sales', from: '2026-10-01', to: '2026-10-07', group_by: 'month', branch_id: '2' });
  });

  it('says too_many_rows with the limit the server names', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/export': () => json({ success: false, message: "Bu davrda buyurtmalar juda ko'p", data: { reason: 'too_many_rows', limit: 50000 } }, 422) });
    const error = await downloadReport(testApi(), { report: 'orders', from: '2026-07-01', to: '2026-09-30' }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ReportDownloadError);
    expect(error).toMatchObject({ status: 422, reason: 'too_many_rows', tooManyRows: true, limit: 50000, rateLimited: false });
    expect((error as Error).message).toBe("Bu davrda buyurtmalar juda ko'p");
  });

  it('reads the rate limit and when to retry', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/export': () => json({ success: false, message: "Juda ko'p so'rov" }, 429, { 'Retry-After': '42' }) });
    const error = await downloadReport(testApi(), { report: 'sales', from: '2026-10-01', to: '2026-10-07' }).catch((e: unknown) => e);
    expect(error).toMatchObject({ status: 429, rateLimited: true, retryAfter: 42, tooManyRows: false });
  });

  it('survives a refusal that is not JSON', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/export': () => new Response('<html>bad gateway</html>', { status: 502 }) });
    const error = await downloadReport(testApi(), { report: 'sales', from: '2026-10-01', to: '2026-10-07' }).catch((e: unknown) => e);
    expect(error).toMatchObject({ status: 502, retryAfter: undefined, limit: undefined });
  });
});

describe('money history', () => {
  const page = (rows: unknown[]) => ({ current_page: 2, data: rows, last_page: 3, per_page: 25, total: 60, from: 26, to: 50 });

  it('reads a page of the ledger and the wallet with their filters', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/finance/ledger': () => ok(page([{ id: 9, type: 'commission', amount: '4500.00' }])),
      'GET /api/v1/owner/finance/wallet': () => ok(page([])),
    });
    const api = testApi();
    const ledger = await fetchLedger(api, { type: 'commission', from: '2026-10-01', to: '2026-10-21', page: 2, per_page: 25 });
    expect(ledger.data[0]?.amount).toBe('4500.00');
    expect(ledger.last_page).toBe(3);
    expect(params(calls[0]!)).toEqual({ type: 'commission', from: '2026-10-01', to: '2026-10-21', page: '2', per_page: '25' });
    await fetchWalletHistory(api, { type: 'settlement' });
    expect(params(calls[1]!)).toEqual({ type: 'settlement' });
  });

  it('reads payouts (masked card) and payments', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/payouts': () => ok(page([{ id: 1, amount: '500000.00', card: '8600 **** **** 9012', status: 'pending' }])),
      'GET /api/v1/owner/payments': () => ok(page([{ uuid: 'p1', provider: 'click', status: 'paid', amount: '45000.00' }])),
    });
    const api = testApi();
    expect((await fetchPayouts(api, { status: 'pending' })).data[0]?.card).toBe('8600 **** **** 9012');
    expect((await fetchPayments(api, { status: 'paid', provider: 'click', branch_id: 4 })).data[0]?.provider).toBe('click');
    expect(params(calls[0]!)).toEqual({ status: 'pending' });
    expect(params(calls[1]!)).toEqual({ status: 'paid', provider: 'click', branch_id: '4' });
  });

  it('refuses with the server message when the caller may not see money', async () => {
    mockFetch({ 'GET /api/v1/owner/finance/ledger': () => json({ success: false, message: 'Ruxsat etilmagan', data: [] }, 403) });
    await expect(fetchLedger(testApi())).rejects.toMatchObject({ status: 403, message: 'Ruxsat etilmagan' });
  });
});
