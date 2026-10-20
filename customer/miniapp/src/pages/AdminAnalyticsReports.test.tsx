import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { formatMoney } from '../lib/format';
import { useLanguageStore } from '../store/languageStore';
import { makeAnalytics } from '../test/factories';
import { failedRequest } from '../test/httpErrors';
import type { OwnerAnalytics } from '../types';
import AdminAnalytics from './AdminAnalytics';
import AdminReports from './AdminReports';

// The owner's analytics and reports screens read the server's aggregates: one request per screen open and per timeframe,
// never a page-by-page walk through the orders

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const calls = () => get.mock.calls.map(([url, config]) => ({ url, params: (config as { params?: Record<string, string> } | undefined)?.params }));

let client: QueryClient;

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));
const uzs = (value: number) => formatMoney(value, 'uz').replace(/\s+/g, ' ');

function serve(analytics: OwnerAnalytics | ((params?: Record<string, string>) => OwnerAnalytics)) {
  get.mockImplementation((_url, config) =>
    Promise.resolve({ data: { success: true, data: typeof analytics === 'function' ? analytics((config as { params?: Record<string, string> }).params) : analytics } }),
  );
}

async function renderScreen(Screen: () => React.JSX.Element) {
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Screen />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await settle();
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(new Date(2026, 9, 18, 12, 0, 0));
  useLanguageStore.setState({ language: 'uz' });
  client = new QueryClient();
  get.mockReset();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
});

describe('AdminAnalytics', () => {
  it('opens with one request and shows the server figures', async () => {
    serve(makeAnalytics());

    await renderScreen(AdminAnalytics);

    expect(calls()).toEqual([{ url: '/owner/analytics', params: { period: 'today' } }]);
    expect(screen.getByText(uzs(130000))).toBeInTheDocument();
    expect(screen.getByText('3 ta muvaffaqiyatli buyurtmadan')).toBeInTheDocument();
    // 150 s of kitchen time is 3 whole minutes, 1500 s of delivery 25
    expect(screen.getByText('3m')).toBeInTheDocument();
    expect(screen.getByText('25m')).toBeInTheDocument();
    // Cash and online (everything that is not cash)
    expect(screen.getByText(uzs(90000))).toBeInTheDocument();
    expect(screen.getByText(uzs(40000))).toBeInTheDocument();
    // The rankings
    const products = screen.getByRole('region', { name: "Eng ko'p sotilganlar" });
    expect(within(products).getByText('Cheeseburger')).toBeInTheDocument();
    expect(within(products).getByText('5 ta')).toBeInTheDocument();
    const couriers = screen.getByRole('region', { name: 'Kuryerlar reytingi' });
    expect(within(couriers).getByText('Sardor')).toBeInTheDocument();
    expect(within(couriers).getByText('2 ta buyurtma')).toBeInTheDocument();
  });

  it('never talks about a partial period or truncated orders: the server counts them all', async () => {
    serve(makeAnalytics());

    await renderScreen(AdminAnalytics);

    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(calls().every((call) => call.url === '/owner/analytics')).toBe(true);
  });

  it('asks for the new period with one request when the timeframe changes', async () => {
    serve((params) => makeAnalytics({ summary: { ...makeAnalytics().summary, revenue: params?.period === 'year' ? 9_000_000 : 130000 } }));
    await renderScreen(AdminAnalytics);

    fireEvent.click(screen.getByRole('button', { name: 'Yil' }));
    await settle();

    expect(calls().map((call) => call.params?.period)).toEqual(['today', 'year']);
    expect(screen.getByText(uzs(9_000_000))).toBeInTheDocument();
  });

  it('shows the top three of the rankings', async () => {
    serve(
      makeAnalytics({
        top_products: ['A', 'B', 'C', 'D', 'E'].map((name, i) => ({ name: `Taom ${name}`, quantity: 10 - i, revenue: 1000 })),
      }),
    );

    await renderScreen(AdminAnalytics);

    expect(screen.getByText('Taom C')).toBeInTheDocument();
    expect(screen.queryByText('Taom D')).not.toBeInTheDocument();
  });

  it('shows dashes while nothing was timed, and zeros for a quiet day', async () => {
    serve(
      makeAnalytics({
        summary: { orders_count: 0, delivered_count: 0, canceled_count: 0, refunded_count: 0, active_count: 0, revenue: 0, average_check: 0 },
        by_payment_method: [],
        top_products: [],
        couriers: [],
        times: { average_prep_seconds: null, average_delivery_seconds: null },
      }),
    );

    await renderScreen(AdminAnalytics);

    expect(screen.getAllByText('--')).toHaveLength(2);
    expect(screen.getByText('Sotuvlar mavjud emas')).toBeInTheDocument();
    expect(screen.getByText(uzs(0), { selector: 'p' })).toBeInTheDocument();
  });

  it('shows an error with a retry when there is nothing to show, and recovers', async () => {
    get.mockRejectedValue(failedRequest(500, {}, '/owner/analytics'));
    await renderScreen(AdminAnalytics);
    await settle(5_000);
    expect(screen.getByRole('alert')).toBeInTheDocument();

    serve(makeAnalytics());
    fireEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    await settle();

    expect(screen.getByText(uzs(130000))).toBeInTheDocument();
  });

  it('refreshes once a minute', async () => {
    serve(makeAnalytics());
    await renderScreen(AdminAnalytics);

    await settle(5 * 60_000);

    expect(get).toHaveBeenCalledTimes(6);
  });
});

describe('AdminReports', () => {
  it('opens with one request and shows the counts and the success rate', async () => {
    serve(makeAnalytics());

    await renderScreen(AdminReports);

    expect(calls()).toEqual([{ url: '/owner/analytics', params: { period: 'today' } }]);
    expect(screen.getByText(uzs(130000))).toBeInTheDocument();
    expect(screen.getByText('3 ta')).toBeInTheDocument(); // delivered
    expect(screen.getByText('2 ta')).toBeInTheDocument(); // canceled
    expect(screen.getByText('50%')).toBeInTheDocument(); // 3 of 6
  });

  it('reads a custom period from the chosen days, and does not ask for one that is too long or reversed', async () => {
    serve(makeAnalytics());
    await renderScreen(AdminReports);
    fireEvent.click(screen.getByRole('button', { name: 'Davr' }));
    await settle();

    // The default custom period is today
    expect(calls().at(-1)).toEqual({ url: '/owner/analytics', params: { period: 'custom', from: '2026-10-18', to: '2026-10-18' } });
    const before = get.mock.calls.length;

    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-01-01' } });
    await settle();
    expect(get).toHaveBeenCalledTimes(before); // 291 days
    expect(screen.getByRole('alert')).toHaveTextContent("Davr ko'pi bilan 93 kun bo'lishi mumkin.");

    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-10-10' } });
    await settle();
    expect(calls().at(-1)).toEqual({ url: '/owner/analytics', params: { period: 'custom', from: '2026-10-10', to: '2026-10-18' } });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('uses the local day for the default dates', async () => {
    vi.setSystemTime(new Date(2026, 9, 18, 1, 0, 0)); // 01:00 local: the UTC date can be the day before
    serve(makeAnalytics());
    await renderScreen(AdminReports);

    fireEvent.click(screen.getByRole('button', { name: 'Davr' }));
    await settle();

    expect(screen.getByLabelText('Boshlanish sanasi')).toHaveValue('2026-10-18');
    expect(screen.getByLabelText('Tugash sanasi')).toHaveValue('2026-10-18');
  });
});
