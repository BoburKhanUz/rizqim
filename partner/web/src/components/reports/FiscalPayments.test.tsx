import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { PaymentsPanel } from './HistoryPanels';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
vi.mock('next/link', () => ({ default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a> }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const payment = (over: Record<string, unknown> = {}) => ({
  uuid: 'pay-1',
  provider: 'payme',
  provider_transaction_id: 'tx-1',
  status: 'paid',
  amount: '85000.00',
  paid_at: '2026-10-21T10:05:00+05:00',
  cancelled_at: null,
  created_at: '2026-10-21T10:00:00+05:00',
  order: { uuid: 'o-1', short_code: 'E101', branch_id: 1, total_price: '85000.00' },
  fiscal: null,
  ...over,
});

const page = (rows: unknown[]) => json({ success: true, message: 'ok', data: { data: rows, current_page: 1, last_page: 1, per_page: 25, total: rows.length, from: 1, to: rows.length } });

describe('the fiscal status of online payments', () => {
  it('explains each problem of a blocked payment in Uzbek and points to the place to fix it', async () => {
    mockFetch({ 'GET /api/v1/owner/payments': () => page([payment({ fiscal: { status: 'blocked', problems: ['mxik_missing', 'store_tin_missing'] } })]) });
    renderPage(<PaymentsPanel />);
    const table = await screen.findByRole('table');
    const row = within(table).getAllByRole('row')[1]!;
    expect(within(row).getByText("To'xtagan (tuzatish kerak)")).toBeInTheDocument();
    expect(within(row).getByText("Taomda MXIK kodi yo'q. Menyuda kodni kiriting.")).toBeInTheDocument();
    expect(within(row).getByText("Do'kon STIRi kiritilmagan. Soliq sozlamalarida yozing.")).toBeInTheDocument();
    expect(within(row).getByRole('link', { name: "Fiskal bo'limiga o'tish" })).toHaveAttribute('href', '/fiscal');
  });

  it('shows the other statuses and a dash where the store is not in fiscal mode', async () => {
    mockFetch({
      'GET /api/v1/owner/payments': () =>
        page([
          payment({ uuid: 'a', fiscal: { status: 'recorded', problems: [] } }),
          payment({ uuid: 'b', fiscal: { status: 'pending', problems: [] } }),
          payment({ uuid: 'c', fiscal: { status: 'failed', problems: ['click_submit_failed'] } }),
          payment({ uuid: 'd', fiscal: null }),
        ]),
    });
    renderPage(<PaymentsPanel />);
    const table = await screen.findByRole('table');
    expect(within(table).getByText('Yozilgan')).toBeInTheDocument();
    expect(within(table).getAllByText('Kutilmoqda').length).toBeGreaterThan(0);
    expect(within(table).getByText("Click chek ma'lumotini qabul qilmadi yoki urinishlar tugadi.")).toBeInTheDocument();
    expect(within(table).queryByRole('link', { name: "Fiskal bo'limiga o'tish" })).not.toBeInTheDocument();
    expect(within(table).getAllByText('—').length).toBeGreaterThan(0);
  });

  it('shows a problem code it has no words for as the server sent it', async () => {
    mockFetch({ 'GET /api/v1/owner/payments': () => page([payment({ fiscal: { status: 'failed', problems: ['new_future_code'] } })]) });
    renderPage(<PaymentsPanel />);
    expect(await screen.findByText("Noma'lum muammo: new_future_code")).toBeInTheDocument();
  });

  it('asks the server for the payments of one fiscal status', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/payments': () => page([payment()]) });
    const typing = userEvent.setup();
    renderPage(<PaymentsPanel />);
    await typing.selectOptions(await screen.findByLabelText('Fiskal chek'), 'blocked');
    await waitFor(() => {
      const last = calls.at(-1)!;
      expect(new URL(last.url).searchParams.get('fiscal_status')).toBe('blocked');
      expect(new URL(last.url).searchParams.get('page')).toBe('1');
    });
    await typing.selectOptions(screen.getByLabelText('Fiskal chek'), '');
    await waitFor(() => expect(new URL(calls.at(-1)!.url).searchParams.has('fiscal_status')).toBe(false));
  });
});
