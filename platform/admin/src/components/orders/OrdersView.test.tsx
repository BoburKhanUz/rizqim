import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPlatformOps, setPlatformOps } from '@/lib/realtime/platformOps';
import { FakeEcho } from '@/test/fakeEcho';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { NOW, page, refundRequest, row } from './fixtures';
import { ORDERS_EVENT_COALESCE_MS, OrdersView } from './OrdersView';

const PERMS = ['orders.view', 'orders.intervene', 'orders.refund_request', 'orders.refund_approve', 'system.manage'].map((name) => `admin.${name}`);
const SLA = { limits: { accept: 5, prepare: 30, deliver: 45 }, defaults: { accept: 5, prepare: 30, deliver: 45 }, bounds: { accept: [1, 120], prepare: [5, 300], deliver: [5, 300] }, warning_ratio: 0.8 };

let echo: FakeEcho;
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(NOW);
  echo = new FakeEcho();
});
afterEach(() => {
  setPlatformOps(null);
  vi.useRealTimers();
});

function setup(routes: Parameters<typeof mockFetch>[0] = {}, { permissions = PERMS, realtime = false } = {}) {
  setPlatformOps(createPlatformOps({ loadConfig: async () => (realtime ? { key: 'k', host: 'h', port: 443, forceTLS: true } : null), createEcho: async () => echo }));
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /orders': () => ok(page([row(), row({ uuid: 'u-2', short_code: 'ORD-ZZ99', status: 'preparing', status_label: 'Tayyorlanmoqda', status_group: 'kitchen', sla: { stage: 'prepare', stage_label: 'Tayyorlash', state: 'warning', limit_minutes: 30, started_at: new Date(NOW - 25 * 60_000).toISOString(), warning_at: new Date(NOW - 60_000).toISOString(), due_at: new Date(NOW + 5 * 60_000).toISOString(), late_by_minutes: 0, minutes_left: 5 }, refund_pending: true })])),
    'GET /orders/sla': () => ok(SLA),
    'GET /refund-requests': () => ok({ data: [{ ...refundRequest(), order: { uuid: 'u-9', short_code: 'ORD-RF01', status: 'delivered', status_label: 'Yetkazildi', total: '50000.00', payment_method: 'payme' }, store: { id: 3, name: 'Lazzat Oshxona' } }], next_cursor: null }),
    ...routes,
  });
  renderPage(<OrdersView />);
  return { ...mocks, user: userEvent.setup() };
}

// List requests only (not /orders/sla)
const orderCalls = (callsTo: ReturnType<typeof setup>['callsTo']) => callsTo('GET', '/orders').length;

describe('OrdersView', () => {
  it('lists the orders of every store with the SLA in words, counts on the chips and links to the card', async () => {
    setup();
    const list = await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" });
    const first = within(list).getByRole('link', { name: '#ORD-AB12CD buyurtmasini ochish' });
    expect(first).toHaveAttribute('href', '/orders/01a17dcb-0000-7000-8000-000000000001');
    expect(within(first).getByText('Qabul qilish: 12 daqiqa kechikdi')).toBeInTheDocument();
    expect(within(first).getByText('Lazzat Oshxona · Chilonzor · Toshkent shahri')).toBeInTheDocument();
    expect(within(list).getByText('Tayyorlash: 5 daqiqa qoldi, kechikish xavfi')).toBeInTheDocument();
    expect(within(list).getByText('Qaytarish tasdiq kutmoqda')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Faol (2)' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Yakunlangan (4)' })).toBeInTheDocument();
    expect(screen.getByText('SLA: qabul qilish 5 daq, tayyorlash 30 daq, yetkazish 45 daq')).toBeInTheDocument();
    expect(screen.getByTestId('live-status')).toHaveTextContent('Har 15 soniyada yangilanadi');
  });

  it('filters by chips and search, one request per change', async () => {
    const { user, callsTo } = setup();
    await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" });
    await user.click(screen.getByRole('button', { name: 'Kechikkan' }));
    await vi.waitFor(() => expect(callsTo('GET', '/orders').at(-1)?.query.get('sla')).toBe('late'));
    await user.click(screen.getByRole('button', { name: /^Oshxonada/ }));
    await vi.waitFor(() => expect(callsTo('GET', '/orders').at(-1)?.query.get('status')).toBe('kitchen'));
    await user.type(screen.getByRole('searchbox', { name: 'Qidirish' }), '#ORD-AB');
    await vi.waitFor(() => expect(callsTo('GET', '/orders').at(-1)?.query.get('q')).toBe('#ORD-AB'));
    expect(callsTo('GET', '/orders').at(-1)?.query.get('sla')).toBe('late');
  });

  it('shows the pending refund queue to an approver and the SLA settings to system.manage', async () => {
    const { user, callsTo } = setup({ 'PUT /orders/sla': () => ok({ ...SLA, limits: { accept: 7, prepare: 30, deliver: 45 } }) });
    const queue = await screen.findByRole('region', { name: 'Tasdiq kutayotgan qaytarishlar' });
    expect(within(queue).getByRole('link')).toHaveAttribute('href', '/orders/u-9');
    expect(within(queue).getByText(/#ORD-RF01 · Lazzat Oshxona · 50 000.so'm · so'radi: Yordam 1/)).toBeInTheDocument();

    await user.click(await screen.findByRole('button', { name: 'SLA chegaralari' }));
    const dialog = await screen.findByRole('dialog', { name: 'SLA chegaralari' });
    const accept = within(dialog).getByLabelText('Qabul qilish (daqiqa)');
    await user.clear(accept);
    await user.type(accept, '7');
    const save = within(dialog).getByRole('button', { name: 'Saqlash' });
    expect(save).toBeDisabled(); // no reason yet
    await user.type(within(dialog).getByLabelText('Sabab'), 'Tushlik vaqti');
    await user.click(save);
    await vi.waitFor(() => expect(callsTo('PUT', '/orders/sla')[0]?.body).toEqual({ accept: 7, prepare: 30, deliver: 45, reason: 'Tushlik vaqti' }));
    expect(await screen.findByText('SLA chegaralari saqlandi.')).toBeInTheDocument();
  });

  it('hides what the member may not do', async () => {
    setup({}, { permissions: ['admin.orders.view'] });
    await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'SLA chegaralari' })).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Tasdiq kutayotgan qaytarishlar' })).not.toBeInTheDocument();
  });

  it('refuses a member without orders.view', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody({ permissions: ['admin.today.view'] })) });
    setPlatformOps(createPlatformOps({ loadConfig: async () => null, createEcho: async () => echo }));
    renderPage(<OrdersView />);
    expect(await screen.findByText(/ruxsat/i)).toBeInTheDocument();
  });

  it('refetches at once on a live event and says it is live', async () => {
    const { callsTo } = setup({}, { realtime: true });
    await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" });
    await vi.waitFor(() => expect(echo.channels).toHaveLength(1));
    echo.connectAll();
    expect(await screen.findByText('Jonli: yangi buyurtmalar darhol chiqadi')).toBeInTheDocument();
    const before = callsTo('GET', '/orders').length;
    echo.channel('platform.ops').emit('ops.order.created', { order: { uuid: 'u-3' } });
    await vi.waitFor(() => expect(callsTo('GET', '/orders').length).toBeGreaterThan(before));
  });

  it('refetches at once on an event and once more at the end of a burst, never dropping its last change', async () => {
    const { callsTo } = setup({}, { realtime: true });
    await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" });
    await vi.waitFor(() => expect(echo.channels).toHaveLength(1));
    echo.connectAll();
    await screen.findByText('Jonli: yangi buyurtmalar darhol chiqadi');
    await vi.waitFor(() => expect(orderCalls(callsTo)).toBeGreaterThan(0));
    const before = orderCalls(callsTo);

    echo.channel('platform.ops').emit('ops.order.created', { order: { uuid: 'u-3' } });
    await vi.waitFor(() => expect(orderCalls(callsTo)).toBe(before + 1));
    // The rest of the burst inside the window: one more refetch at its end, not one each, and not none
    echo.channel('platform.ops').emit('ops.order.updated', { order: { uuid: 'u-3' } });
    echo.channel('platform.ops').emit('ops.order.updated', { order: { uuid: 'u-1' } });
    expect(orderCalls(callsTo)).toBe(before + 1);
    await vi.waitFor(() => expect(orderCalls(callsTo)).toBe(before + 2), { timeout: ORDERS_EVENT_COALESCE_MS + 1500 });
    await new Promise((resolve) => setTimeout(resolve, ORDERS_EVENT_COALESCE_MS + 200));
    expect(orderCalls(callsTo)).toBe(before + 2);
  });

  it('says when the list cannot be loaded and retries', async () => {
    let fail = true;
    const { user } = setup({ 'GET /orders': () => (fail ? refusal(500, 'Server xatosi') : ok(page([row()]))) });
    expect(await screen.findByText('Buyurtmalar yuklanmadi')).toBeInTheDocument();
    fail = false;
    await user.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByRole('list', { name: "Buyurtmalar ro'yxati" })).toBeInTheDocument();
  });
});
