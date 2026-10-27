import { act, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { createTranslator } from '@/i18n';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import type { TodayAttention, TodaySummary } from '@/lib/api/today';
import { createPlatformOps, setPlatformOps } from '@/lib/realtime/platformOps';
import { FakeEcho } from '@/test/fakeEcho';
import { changeText, TODAY_EVENT_COALESCE_MS, TodayView } from './TodayView';

const kpi = <V extends string | number>(value: V, yesterdaySameTime: V, yesterday: V, lastWeekSameTime: V, lastWeek: V, vsYesterday: TodaySummary['kpis']['gmv']['vs_yesterday'], vsLastWeek: TodaySummary['kpis']['gmv']['vs_last_week']) => ({
  value,
  yesterday_same_time: yesterdaySameTime,
  yesterday,
  last_week_same_time: lastWeekSameTime,
  last_week: lastWeek,
  vs_yesterday: vsYesterday,
  vs_last_week: vsLastWeek,
});

const SUMMARY: TodaySummary = {
  date: '2026-11-10',
  timezone: 'Asia/Tashkent',
  generated_at: '2026-11-10T14:30:00+05:00',
  stats_computed_at: '2026-11-10T14:25:00+05:00',
  live_from: '2026-11-10T14:00:00+05:00',
  kpis: {
    gmv: kpi('1250000.00', '1000000.00', '1800000.00', '2500000.00', '3000000.00', { direction: 'up', percent: '25.0' }, { direction: 'down', percent: '50.0' }),
    orders: kpi(42, 42, 60, 30, 55, { direction: 'same', percent: '0.0' }, { direction: 'up', percent: '40.0' }),
    commission: kpi('125000.00', '0.00', '180000.00', '100000.00', '300000.00', { direction: 'up', percent: null }, { direction: 'up', percent: '25.0' }),
    subscription_revenue: kpi('249000.00', '0.00', '0.00', '0.00', '0.00', { direction: 'up', percent: null }, { direction: 'up', percent: null }),
    canceled_orders: kpi(3, 1, 2, 0, 4, { direction: 'up', percent: '200.0' }, { direction: 'up', percent: null }),
  },
  channels: [
    { channel: 'telegram', label: 'Telegram', marketplace: true, orders: 40, gmv: '1200000.00' },
    { channel: 'mobile', label: 'Mobil ilova', marketplace: true, orders: 2, gmv: '50000.00' },
    { channel: 'pos_hall', label: 'Zal (POS)', marketplace: false, orders: 15, gmv: '900000.00' },
    { channel: 'aggregator', label: 'Agregator', marketplace: false, orders: 0, gmv: '0.00' },
  ],
  regions: [
    { region_id: 1, name: 'Toshkent shahri', orders: 30, gmv: '900000.00', active_stores: 12 },
    { region_id: null, name: "Viloyat ko'rsatilmagan", orders: 12, gmv: '350000.00', active_stores: 3 },
  ],
};

const ATTENTION: TodayAttention = {
  generated_at: '2026-11-10T14:30:00+05:00',
  groups: [
    {
      kind: 'fiscal_failed',
      label: 'Yiqilgan fiskal cheklar',
      count: 7,
      severity: 'critical',
      items: [{ kind: 'fiscal_failed', severity: 'critical', title: 'Lazzat Oshxona · Chilonzor', subtitle: "50 000 so'm · MXIK noto'g'ri", href: '/pos?fiscal=f1', since: '2026-11-10T13:05:00+05:00', store_id: 12 }],
    },
    {
      kind: 'device_offline',
      label: 'Ish vaqtida oflayn kassalar',
      count: 1,
      severity: 'warning',
      items: [{ kind: 'device_offline', severity: 'warning', title: 'Kassa 1 · Lazzat Oshxona', subtitle: 'Kassa (POS)', href: '/pos?device=d1', since: '2026-11-09T21:00:00+05:00', store_id: 12 }],
    },
  ],
};

function setup(routes: Parameters<typeof mockFetch>[0] = {}) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody()),
    'GET /today': () => ok(SUMMARY),
    'GET /today/attention': () => ok(ATTENTION),
    ...routes,
  });
  renderPage(<TodayView />);
  return { ...mocks, user: userEvent.setup() };
}

// The texts read times as the team sees them (the browser's zone): pin it to Tashkent for this file
const zone = process.env.TZ;
beforeAll(() => {
  process.env.TZ = 'Asia/Tashkent';
});
afterAll(() => {
  if (zone === undefined) delete process.env.TZ;
  else process.env.TZ = zone;
});

let echo: FakeEcho;
let realtime = false;
beforeEach(() => {
  echo = new FakeEcho();
  realtime = false;
  setPlatformOps(createPlatformOps({ loadConfig: async () => (realtime ? { key: 'k', host: 'h', port: 443, forceTLS: true } : null), createEcho: async () => echo }));
});

afterEach(() => {
  vi.useRealTimers();
  setPlatformOps(null);
});

describe('Bugun', () => {
  it('shows the four numbers with comparisons in words', async () => {
    setup();
    const kpis = await screen.findByRole('list', { name: "Bugungi ko'rsatkichlar" });
    const [gmv, orders, commission, subscription] = within(kpis).getAllByRole('listitem');

    expect(within(gmv!).getByRole('heading', { name: 'GMV (marketpleys)' })).toBeInTheDocument();
    expect(gmv).toHaveTextContent('1 250 000 so\'m');
    expect(gmv).toHaveTextContent("Kechagi shu vaqtga:25% ko'p (1 000 000 so'm)");
    expect(gmv).toHaveTextContent("O'tgan haftaning shu kuniga:50% kam (2 500 000 so'm)");
    expect(gmv).toHaveTextContent("Kecha jami: 1 800 000 so'm");

    expect(orders).toHaveTextContent('42');
    expect(orders).toHaveTextContent("o'zgarmadi (42)");
    expect(orders).toHaveTextContent('Bekor qilingan: 3');

    expect(commission).toHaveTextContent("ko'p (u paytda 0 so'm edi)");
    expect(subscription).toHaveTextContent("249 000 so'm");

    expect(screen.getByText('Yangilandi: 14:30')).toBeInTheDocument();
    expect(screen.getByText('10-noy, Toshkent vaqti')).toBeInTheDocument();
  });

  it('lists what needs attention, most urgent first, each row a link to its card', async () => {
    setup();
    const list = await screen.findByRole('list', { name: 'Diqqat talab qiladi' });
    const groups = within(list).getAllByRole('listitem').filter((item) => item.parentElement === list);
    expect(groups).toHaveLength(2);
    expect(groups[0]).toHaveTextContent('Yiqilgan fiskal cheklar');
    expect(groups[0]).toHaveTextContent('7 ta');
    expect(groups[0]).toHaveTextContent('Shoshilinch');
    expect(groups[0]).toHaveTextContent('Yana 6 ta bor');
    expect(groups[1]).toHaveTextContent('Diqqat');

    const fiscal = within(groups[0]!).getByRole('link', { name: /Lazzat Oshxona · Chilonzor/ });
    expect(fiscal).toHaveAttribute('href', '/pos?fiscal=f1');
    expect(fiscal).toHaveTextContent('13:05 dan beri');
    expect(within(groups[1]!).getByRole('link')).toHaveAttribute('href', '/pos?device=d1');
  });

  it('says "Hammasi joyida" when nothing needs attention', async () => {
    setup({ 'GET /today/attention': () => ok({ groups: [], generated_at: ATTENTION.generated_at }) });
    expect(await screen.findByText('Hammasi joyida')).toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Diqqat talab qiladi' })).toBeNull();
  });

  it('shows channels (POS outside GMV) and regions', async () => {
    setup();
    const channels = await screen.findByRole('list', { name: "Kanallar bo'yicha" });
    const rows = within(channels).getAllByRole('listitem');
    // A non-marketplace channel without orders is left out
    expect(rows.map((row) => row.textContent)).toHaveLength(3);
    expect(rows[2]).toHaveTextContent('Zal (POS)');
    expect(rows[2]).toHaveTextContent('GMV ga kirmaydi');
    expect(rows[0]).toHaveTextContent("40 buyurtma · 1 200 000 so'm");

    const regions = screen.getByRole('list', { name: "Viloyatlar bo'yicha" });
    const [tashkent, none] = within(regions).getAllByRole('listitem');
    expect(tashkent).toHaveTextContent('Toshkent shahri');
    expect(tashkent).toHaveTextContent('12 faol ovqatxona');
    expect(none).toHaveTextContent("Viloyat ko'rsatilmagan");
  });

  it('keeps the numbers when the attention list fails, and the other way round', async () => {
    setup({ 'GET /today/attention': () => refusal(500, 'Server xatosi') });
    expect(await screen.findByText("Diqqat talab qiladigan ro'yxat yuklanmadi")).toBeInTheDocument();
    expect(screen.getByRole('list', { name: "Bugungi ko'rsatkichlar" })).toBeInTheDocument();
  });

  it('says it when the numbers cannot be loaded and retries', async () => {
    let fail = true;
    const { callsTo, user } = setup({ 'GET /today': () => (fail ? refusal(500, 'Server xatosi') : ok(SUMMARY)) });
    expect(await screen.findByText('Bugungi raqamlar yuklanmadi')).toBeInTheDocument();
    expect(await screen.findByRole('list', { name: 'Diqqat talab qiladi' })).toBeInTheDocument();
    fail = false;
    await user.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByRole('list', { name: "Bugungi ko'rsatkichlar" })).toBeInTheDocument();
    expect(callsTo('GET', '/today').length).toBeGreaterThanOrEqual(2);
  });

  it('asks for the attention list with a limit and refreshes both every 30 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const { callsTo } = setup();
    await screen.findByRole('list', { name: 'Diqqat talab qiladi' });
    expect(callsTo('GET', '/today/attention')[0]!.query.get('limit')).toBe('5');
    const before = { summary: callsTo('GET', '/today').length, attention: callsTo('GET', '/today/attention').length };

    await act(async () => {
      await vi.advanceTimersByTimeAsync(30_000);
    });
    expect(callsTo('GET', '/today').length).toBeGreaterThan(before.summary);
    expect(callsTo('GET', '/today/attention').length).toBeGreaterThan(before.attention);
  });

  it('refreshes on the button', async () => {
    const { callsTo, user } = setup();
    await screen.findByRole('list', { name: 'Diqqat talab qiladi' });
    const before = callsTo('GET', '/today').length;
    await user.click(screen.getByRole('button', { name: 'Yangilash' }));
    await vi.waitFor(() => expect(callsTo('GET', '/today').length).toBeGreaterThan(before));
  });

  it('asks again shortly after platform.ops says an order or an alert changed, once per burst', async () => {
    realtime = true;
    const { callsTo } = setup();
    await screen.findByRole('list', { name: 'Diqqat talab qiladi' });
    await vi.waitFor(() => expect(echo.channels.map((channel) => channel.name)).toContain('platform.ops'));
    echo.connectAll();
    const before = callsTo('GET', '/today/attention').length;

    vi.useFakeTimers({ shouldAdvanceTime: true });
    act(() => {
      echo.channel('platform.ops').emit('ops.order.created', { order: { uuid: 'u-1' } });
      echo.channel('platform.ops').emit('ops.alert.opened', { alert: { id: 3 } });
      echo.channel('platform.ops').emit('ops.order.updated', { order: { uuid: 'u-1' } });
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(TODAY_EVENT_COALESCE_MS + 100);
    });
    await vi.waitFor(() => expect(callsTo('GET', '/today/attention').length).toBe(before + 1));
    expect(callsTo('GET', '/today').length).toBeGreaterThanOrEqual(2);
  });

  it('quietly says when the stats job is late', async () => {
    setup({ 'GET /today': () => ok({ ...SUMMARY, stats_computed_at: '2026-11-10T12:00:00+05:00' }) });
    expect(await screen.findByText(/Yig'ma jadvallar 12:00 da yangilangan/)).toBeInTheDocument();
  });
});

describe('changeText', () => {
  const t = createTranslator('uz');
  it('puts the change in plain words', () => {
    expect(changeText({ direction: 'up', percent: '12.5' }, '100', t)).toBe("12,5% ko'p (100)");
    expect(changeText({ direction: 'down', percent: '50.0' }, '100', t)).toBe('50% kam (100)');
    expect(changeText({ direction: 'same', percent: '0.0' }, '100', t)).toBe("o'zgarmadi (100)");
    expect(changeText({ direction: 'up', percent: null }, '0', t)).toBe("ko'p (u paytda 0 edi)");
  });
});
