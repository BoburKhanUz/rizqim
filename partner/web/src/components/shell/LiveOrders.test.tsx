import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { StaffApiProvider, useStaffOrders } from '@rizqim/staff';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '../AuthProvider';
import { I18nProvider } from '../I18nProvider';
import { ApiConfigContext, RealtimeConfigContext } from '@/lib/apiConfig';
import type { RealtimeConfig } from '@/lib/realtime';
import { revisions } from '@/lib/revisions';
import type { RealtimeStatus } from '@rizqim/staff';
import { getStaffApi, setSession } from '@/lib/session/client';
import type { Chime } from '@/lib/sound';
import { FakeEcho } from '@/test/fakeEcho';
import { API, json, mockFetch } from '@/test/helpers';
import { BranchScopeProvider } from './BranchScope';
import { LIST_READ_PACING, LiveOrdersProvider, LIVE_POLL_MS, ORDER_READ_PACING, POLL_MS, useLiveOrders, type BoardPacing } from './LiveOrders';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), usePathname: () => '/orders' }));

const CONFIG: RealtimeConfig = { key: 'k', host: 'ws.test', port: 443, forceTLS: true, authEndpoint: `${API}/broadcasting/auth` };

beforeEach(() => {
  window.localStorage.clear();
  document.title = 'Rizqim Hamkor';
});
afterEach(() => {
  setSession(null);
  revisions.clear();
});

const STATUS: RealtimeStatus = {
  live: true,
  heartbeatSeconds: 30,
  silenceSeconds: 90,
  pollLiveSeconds: 60,
  pollFallbackSeconds: 15,
  reverb: { key: 'server-key', host: null, port: null, scheme: null },
};

const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: 'ok', data, ...extra });

function Probe() {
  const live = useLiveOrders();
  return (
    <p data-testid="live">
      {live.realtime} {live.pollInterval} {live.newCount}
    </p>
  );
}

function fakeChime(): Chime & { play: ReturnType<typeof vi.fn> } {
  return { ready: true, unlock: vi.fn(async () => true), play: vi.fn(() => true), close: vi.fn() };
}

// The tests' pacing: no spread, short windows (the real ones: order reads 3 s window, 0-1 s spread; list reads 30 s, 0-5 s)
const FAST: BoardPacing = { order: { windowMs: 300, spreadMs: 0 }, list: { windowMs: 300, spreadMs: 0 } };

type TestOrder = { uuid: string; status: string; branch_id?: number | null; created_at?: string; items?: { id: number; kitchen_status: string; ready_at: string | null }[] };
const full = (order: TestOrder) => ({
  short_code: order.uuid.toUpperCase(),
  type: 'delivery',
  channel: 'telegram',
  branch_id: 5,
  created_at: '2026-10-20T10:00:00+05:00',
  items: [],
  items_total: 0,
  items_ready: 0,
  ...order,
});

// The complete open list of the scope on screen (what the board and the dashboard read)
function OpenList() {
  const open = useStaffOrders({ scope: 'active' }, { refetchInterval: false });
  return <p data-testid="open">{open.data?.orders.length ?? '-'}</p>;
}

function setup({
  role = 'owner',
  branch = { id: null as number | null, scoped: false },
  config = CONFIG as RealtimeConfig | null,
  status = null as RealtimeStatus | null,
  pacing = FAST,
  withOpenList = false,
} = {}) {
  const state = {
    orders: [] as TestOrder[],
    revision: null as string | null,
    // the answer of GET /staff/orders/{uuid} by status (200 by default: the order as it is in `orders`, or `details`)
    detailStatus: {} as Record<string, number>,
    details: {} as Record<string, TestOrder>,
  };
  const withRevision = (response: Response) => {
    // Wave M2: a single-branch read names its change token (when the backend exposes the header)
    if (state.revision !== null && branch.id !== null) {
      response.headers.set('X-Revision', state.revision);
      response.headers.set('X-Revision-Scope', `branch:${branch.id}`);
    }
    return response;
  };
  const routes: Record<string, (request: Request) => Response> = {
    'POST /api/session/refresh': () => json({ access_token: 'a', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id: 1, name: 'A', role } }),
    'GET /api/v1/me/permissions': () =>
      ok({ role, store_id: 3, branch_id: branch.id, branch_scoped: branch.scoped, permissions: ['orders.view', 'orders.manage'], device_uuid: null }),
    'GET /api/v1/owner/branches': () => ok([{ id: 5, name: 'Chilonzor', working_hours: { timezone: 'Asia/Tashkent' } }]),
    'GET /api/v1/staff/orders': (request) => {
      const wanted = new URL(request.url).searchParams.get('status');
      const orders = state.orders.filter((order) => !wanted || wanted.split(',').includes(order.status)).map(full);
      return withRevision(ok(orders, { meta: { next_cursor: null, per_page: 100 } }));
    },
  };
  // GET /api/v1/staff/orders/{uuid}: one order (fix round 2)
  const router = new Proxy(routes, {
    get(target, key: string) {
      const match = /^GET \/api\/v1\/staff\/orders\/([^/]+)$/.exec(key);
      if (!match) return target[key];
      const uuid = match[1] ?? '';
      return () => {
        const code = state.detailStatus[uuid] ?? 200;
        if (code !== 200) return json({ success: false, message: 'no', errors: {} }, code);
        const order = state.details[uuid] ?? state.orders.find((candidate) => candidate.uuid === uuid);
        if (!order) return json({ success: false, message: 'Topilmadi', errors: {} }, 404);
        return withRevision(ok({ ...full(order), status_history: [] }));
      };
    },
  });
  const { calls } = mockFetch(router);
  const loadStatus = vi.fn(async () => status);
  const echo = new FakeEcho();
  const connect = vi.fn(async () => echo);
  const chime = fakeChime();
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const { unmount } = render(
    <I18nProvider language="uz">
      <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
        <RealtimeConfigContext.Provider value={config}>
          <StaffApiProvider api={getStaffApi(API)}>
            <QueryClientProvider client={queryClient}>
              <AuthProvider apiBaseUrl={API}>
                <BranchScopeProvider>
                  <LiveOrdersProvider connect={connect} chime={chime} loadStatus={loadStatus} pacing={pacing}>
                    <Probe />
                    {withOpenList ? <OpenList /> : null}
                  </LiveOrdersProvider>
                </BranchScopeProvider>
              </AuthProvider>
            </QueryClientProvider>
          </StaffApiProvider>
        </RealtimeConfigContext.Provider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
  const listCalls = (status?: string) =>
    calls.filter((call) => {
      const url = new URL(call.url);
      return url.pathname === '/api/v1/staff/orders' && (status === undefined || url.searchParams.get('status') === (status || null));
    }).length;
  const ordersCalls = () => listCalls();
  const orderCalls = (uuid?: string) =>
    calls.filter((call) => {
      const path = new URL(call.url).pathname;
      return uuid === undefined ? /^\/api\/v1\/staff\/orders\/[^/]+$/.test(path) : path === `/api/v1/staff/orders/${uuid}`;
    }).length;
  return { state, echo, connect, chime, ordersCalls, listCalls, orderCalls, unmount, loadStatus, queryClient };
}

const pause = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

describe('LiveOrdersProvider', () => {
  it('connects to the store channel for an owner on all branches, and polls slowly while it is live', async () => {
    const { echo, connect } = setup();
    await waitFor(() => expect(connect).toHaveBeenCalledWith(CONFIG));
    await waitFor(() => expect(echo.channels.map((channel) => channel.name)).toEqual(['store.3']));
    expect(screen.getByTestId('live')).toHaveTextContent(`connecting ${POLL_MS}`);
    echo.connectAll();
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`live ${LIVE_POLL_MS}`));
    echo.setConnection('unavailable');
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`offline ${POLL_MS}`));
  });

  it('listens on the branch channel of a branch-bound person', async () => {
    const { echo } = setup({ role: 'cook', branch: { id: 5, scoped: true } });
    await waitFor(() => expect(echo.channels.map((channel) => channel.name)).toEqual(['pos-branch.5']));
  });

  it('reads that order on an event (no list read) and chimes for a new order', async () => {
    const { state, echo, chime, ordersCalls, orderCalls } = setup();
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    echo.connectAll();
    await waitFor(() => expect(ordersCalls()).toBe(1));
    expect(chime.play).not.toHaveBeenCalled(); // what was there at first is not announced

    state.orders = [{ uuid: 'n1', status: 'new' }];
    echo.channel('store.3').emit('order.created', { order: { uuid: 'n1', status: 'new', store_id: 3, branch_id: 5 } });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 1$/));
    expect(orderCalls('n1')).toBe(1);
    expect(ordersCalls()).toBe(1);
    expect(chime.play).toHaveBeenCalledTimes(1);
    expect(document.title).toBe('(1) Rizqim Hamkor');
    // Next sets another page's title (metadata) after a navigation: the count comes back in front of it
    document.title = 'Menyu | Rizqim Hamkor';
    await waitFor(() => expect(document.title).toBe('(1) Menyu | Rizqim Hamkor'));

    // An accepted order leaves the count, without a chime
    state.orders = [{ uuid: 'n1', status: 'accepted' }];
    echo.channel('store.3').emit('order.status_changed', { order: { uuid: 'n1', status: 'accepted' } });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 0$/));
    expect(chime.play).toHaveBeenCalledTimes(1);
    expect(ordersCalls()).toBe(1);
  });

  it('gives the audio back and takes the count off the tab title when the shell goes away', async () => {
    const { state, echo, chime, ordersCalls, unmount } = setup();
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    await waitFor(() => expect(ordersCalls()).toBe(1));
    state.orders = [{ uuid: 'n3', status: 'new' }];
    echo.channel('store.3').emit('order.created', { order: { uuid: 'n3', status: 'new' } });
    await waitFor(() => expect(document.title).toBe('(1) Rizqim Hamkor'));
    unmount();
    expect(chime.close).toHaveBeenCalled();
    expect(document.title).toBe('Rizqim Hamkor');
  });

  it('does not chime while muted', async () => {
    window.localStorage.setItem('rz_order_sound', 'off');
    const { state, echo, chime, ordersCalls } = setup();
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    await waitFor(() => expect(ordersCalls()).toBe(1));
    state.orders = [{ uuid: 'n2', status: 'new' }];
    echo.channel('store.3').emit('order.created', { order: { uuid: 'n2', status: 'new' } });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 1$/));
    expect(chime.play).not.toHaveBeenCalled();
  });

  it('refetches everything after a reconnect', async () => {
    const { echo, ordersCalls } = setup();
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    echo.connectAll();
    await waitFor(() => expect(ordersCalls()).toBe(1));
    echo.setConnection('disconnected');
    echo.connectAll();
    await waitFor(() => expect(ordersCalls()).toBe(2));
  });

  it('stays off and polls when real-time is not configured', async () => {
    const { connect } = setup({ config: null });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`off ${POLL_MS}`));
    expect(connect).not.toHaveBeenCalled();
  });

  describe('wave M2', () => {
    it("connects with the backend's settings when the panel's server has no key", async () => {
      const { connect } = setup({ config: null, status: STATUS });
      await waitFor(() => expect(connect).toHaveBeenCalledWith({ key: 'server-key', host: 'api.test', port: 80, forceTLS: false, authEndpoint: `${API}/broadcasting/auth` }));
    });

    it('polls at the normal pace while the server says real-time is not live', async () => {
      const { echo } = setup({ status: { ...STATUS, live: false } });
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`offline ${POLL_MS}`));
    });

    it('reads GET /realtime again after a reconnect', async () => {
      const { echo, loadStatus } = setup({ status: STATUS });
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(loadStatus).toHaveBeenCalledTimes(1));
      echo.setConnection('disconnected');
      echo.connectAll();
      await waitFor(() => expect(loadStatus).toHaveBeenCalledTimes(2));
    });

    it('the next token reads that order, one already read fetches nothing, a gap reads the open list', async () => {
      const { state, echo, ordersCalls, orderCalls } = setup({ role: 'cook', branch: { id: 5, scoped: true }, status: STATUS });
      state.revision = '700';
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(ordersCalls()).toBeGreaterThanOrEqual(1));
      await waitFor(() => expect(revisions.heldToken('pos-branch.5')).toBe('700'));

      // The start settles first (the shell may read once more while the session and the branch scope arrive)
      await new Promise((resolve) => setTimeout(resolve, 500));
      const settled = ordersCalls();

      // already read: nothing
      echo.channel('pos-branch.5').emit('order.status_changed', { order: { uuid: 'a', status: 'accepted' }, rev: { 'pos-branch.5': '700' } });
      await new Promise((resolve) => setTimeout(resolve, 400));
      expect(ordersCalls()).toBe(settled);

      // the next one: that order only
      state.revision = '701';
      state.orders = [{ uuid: 'a', status: 'preparing' }];
      echo.channel('pos-branch.5').emit('order.status_changed', { order: { uuid: 'a', status: 'preparing' }, rev: { 'pos-branch.5': '701' } });
      await waitFor(() => expect(orderCalls('a')).toBe(1));
      expect(ordersCalls()).toBe(settled);

      // a gap: the open list again
      state.revision = '709';
      echo.channel('pos-branch.5').emit('order.status_changed', { order: { uuid: 'a', status: 'ready' }, rev: { 'pos-branch.5': '709' } });
      await waitFor(() => expect(ordersCalls()).toBe(settled + 1));
      expect(orderCalls('a')).toBe(1);
    });

    it('a heartbeat ahead of what the board read reads everything again', async () => {
      const { state, echo, ordersCalls } = setup({ role: 'cook', branch: { id: 5, scoped: true }, status: STATUS });
      state.revision = '40';
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(revisions.heldToken('pos-branch.5')).toBe('40'));
      const before = ordersCalls();

      echo.channel('pos-branch.5').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.5': '40' } });
      await new Promise((resolve) => setTimeout(resolve, 100));
      expect(ordersCalls()).toBe(before);

      echo.channel('pos-branch.5').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.5': '41' } });
      await waitFor(() => expect(ordersCalls()).toBe(before + 1));
    });

    it('silence: no heartbeat for silence_seconds is "offline" (fast polling) until one comes', async () => {
      const { echo } = setup({ status: { ...STATUS, silenceSeconds: 0.3 } });
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`live ${LIVE_POLL_MS}`));
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`offline ${POLL_MS}`), { timeout: 2000 });
      echo.channel('store.3').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'store.3': '1' } });
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(`live ${LIVE_POLL_MS}`));
    });

    it('the tab coming back reads everything again', async () => {
      const { echo, ordersCalls } = setup();
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(ordersCalls()).toBe(1));
      let visibility: DocumentVisibilityState = 'hidden';
      vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() => visibility);
      document.dispatchEvent(new Event('visibilitychange'));
      visibility = 'visible';
      document.dispatchEvent(new Event('visibilitychange'));
      await waitFor(() => expect(ordersCalls()).toBe(2));
    });
  });
  describe('fix round 2: pacing and relevance', () => {
    it('an order change shows within ~4 s even right after another one; list reads stay rare', () => {
      expect(ORDER_READ_PACING).toEqual({ windowMs: 3_000, spreadMs: 1_000 });
      expect(LIST_READ_PACING).toEqual({ windowMs: 30_000, spreadMs: 5_000 });
    });

    it('an event the board does not read for feeds the token: the next relevant one is no gap', async () => {
      const { state, echo, ordersCalls, orderCalls } = setup({ role: 'cook', branch: { id: 5, scoped: true }, status: STATUS });
      state.revision = '800';
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(revisions.heldToken('pos-branch.5')).toBe('800'));
      await pause(500);
      const settled = ordersCalls();
      const channel = echo.channel('pos-branch.5');
      channel.emit('fiscal.receipt_updated', { order: { uuid: 'a', status: 'delivered' }, rev: { 'pos-branch.5': '801' } });
      channel.emit('order.bill_changed', { order: { uuid: 'a', status: 'delivered' }, change: 'fiscal', rev: { 'pos-branch.5': '802' } });
      channel.emit('shift.changed', { rev: { 'pos-branch.5': '803' } });
      channel.emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.5': '803' } });
      await pause(400);
      expect(ordersCalls()).toBe(settled);
      expect(orderCalls()).toBe(0);
      state.orders = [{ uuid: 'a', status: 'ready' }];
      channel.emit('order.status_changed', { order: { uuid: 'a', status: 'ready' }, rev: { 'pos-branch.5': '804' } });
      await waitFor(() => expect(orderCalls('a')).toBe(1));
      await pause(400);
      expect(ordersCalls()).toBe(settled);
    });

    it('a payment reads the order, a printed bill does not', async () => {
      const { state, echo, orderCalls } = setup();
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      state.orders = [{ uuid: 'p1', status: 'ready' }];
      echo.channel('store.3').emit('order.bill_changed', { order: { uuid: 'p1', status: 'ready' }, change: 'printed' });
      await pause(400);
      expect(orderCalls('p1')).toBe(0);
      echo.channel('store.3').emit('order.bill_changed', { order: { uuid: 'p1', status: 'ready' }, change: 'payment' });
      await waitFor(() => expect(orderCalls('p1')).toBe(1));
    });

    it('a burst: one read at once, one at the end of the window, each distinct order once', async () => {
      const { state, echo, ordersCalls, orderCalls } = setup({ pacing: { order: { windowMs: 600, spreadMs: 0 }, list: FAST.list } });
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(ordersCalls()).toBe(1));
      state.orders = [
        { uuid: 'b1', status: 'new' },
        { uuid: 'b2', status: 'new' },
      ];
      const channel = echo.channel('store.3');
      channel.emit('order.created', { order: { uuid: 'b1', status: 'new' } });
      await waitFor(() => expect(orderCalls('b1')).toBe(1));
      for (let i = 0; i < 4; i++) channel.emit('order.items_changed', { order: { uuid: 'b1', status: 'new' } });
      channel.emit('order.created', { order: { uuid: 'b2', status: 'new' } });
      await pause(200);
      expect(orderCalls()).toBe(1);
      await waitFor(() => expect(orderCalls()).toBe(3));
      expect(orderCalls('b1')).toBe(2);
      expect(orderCalls('b2')).toBe(1);
      expect(ordersCalls()).toBe(1);
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 2$/));
    });

    it('more than five orders in one batch: one open-list read instead', async () => {
      const { echo, ordersCalls, orderCalls } = setup({ pacing: { order: { windowMs: 400, spreadMs: 0 }, list: FAST.list } });
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(ordersCalls()).toBe(1));
      const channel = echo.channel('store.3');
      channel.emit('order.created', { order: { uuid: 'm0', status: 'new' } });
      await waitFor(() => expect(orderCalls()).toBe(1));
      for (let i = 1; i <= 6; i++) channel.emit('order.created', { order: { uuid: `m${i}`, status: 'new' } });
      await waitFor(() => expect(ordersCalls()).toBe(2));
      expect(orderCalls()).toBe(1);
    });

    it('a 404 on the order read takes it off the board; another failure reads the open list', async () => {
      const { state, echo, ordersCalls, orderCalls } = setup();
      state.orders = [{ uuid: 'g1', status: 'new' }];
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 1$/));
      const before = ordersCalls();
      state.detailStatus.g1 = 404;
      echo.channel('store.3').emit('order.status_changed', { order: { uuid: 'g1', status: 'new' } });
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 0$/));
      expect(orderCalls('g1')).toBe(1);
      expect(ordersCalls()).toBe(before);

      state.detailStatus.g2 = 500;
      echo.channel('store.3').emit('order.status_changed', { order: { uuid: 'g2', status: 'accepted' } });
      await waitFor(() => expect(orderCalls('g2')).toBe(1));
      await waitFor(() => expect(ordersCalls()).toBe(before + 1));
    });

    it('a kitchen line is patched in from the event, without any read', async () => {
      const { state, echo, ordersCalls, orderCalls, queryClient } = setup({ withOpenList: true });
      state.orders = [{ uuid: 'k1', status: 'preparing', items: [{ id: 7, kitchen_status: 'pending', ready_at: null }] }];
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(screen.getByTestId('open')).toHaveTextContent('1'));
      await pause(300);
      const before = ordersCalls();
      echo.channel('store.3').emit('kitchen.item_status_changed', {
        order: { uuid: 'k1', status: 'preparing' },
        item: { id: 7, kitchen_status: 'ready', ready_at: '2026-10-20T10:05:00+05:00' },
        items_total: 1,
        items_ready: 1,
      });
      await waitFor(() => {
        const open = queryClient.getQueryData<{ orders: { items: { kitchen_status: string }[]; items_ready: number }[] }>(['staff-orders', 'list', { scope: 'active' }]);
        expect(open?.orders[0]?.items[0]?.kitchen_status).toBe('ready');
        expect(open?.orders[0]?.items_ready).toBe(1);
      });
      await pause(400);
      expect(ordersCalls()).toBe(before);
      expect(orderCalls()).toBe(0);
    });

    it('while the open list is on screen the badge is derived from it: a gap reads one list, not two', async () => {
      const { state, echo, listCalls } = setup({ withOpenList: true });
      state.orders = [
        { uuid: 'd1', status: 'new' },
        { uuid: 'd2', status: 'accepted' },
      ];
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(screen.getByTestId('open')).toHaveTextContent('2'));
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 1$/));
      await pause(300);
      const open = listCalls('');
      const badge = listCalls('new');
      state.orders = [
        { uuid: 'd1', status: 'new' },
        { uuid: 'd2', status: 'accepted' },
        { uuid: 'd3', status: 'new' },
      ];
      // a heartbeat of the store channel that moved since the last thing heard: a missed change
      echo.channel('store.3').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'store.3': '1' } });
      echo.channel('store.3').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'store.3': '5' } });
      await waitFor(() => expect(listCalls('')).toBe(open + 1));
      await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 2$/));
      await pause(300);
      expect(listCalls('new')).toBe(badge);
    });

    it('the gap reads are paced: a second gap inside the window waits for its end', async () => {
      const { state, echo, ordersCalls } = setup({ role: 'cook', branch: { id: 5, scoped: true }, status: STATUS, pacing: { order: FAST.order, list: { windowMs: 800, spreadMs: 0 } } });
      state.revision = '10';
      await waitFor(() => expect(echo.channels).toHaveLength(1));
      echo.connectAll();
      await waitFor(() => expect(revisions.heldToken('pos-branch.5')).toBe('10'));
      await pause(500);
      const settled = ordersCalls();
      state.revision = '20';
      echo.channel('pos-branch.5').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.5': '20' } });
      await waitFor(() => expect(ordersCalls()).toBe(settled + 1));
      state.revision = '30';
      echo.channel('pos-branch.5').emit('realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.5': '30' } });
      await pause(300);
      expect(ordersCalls()).toBe(settled + 1);
      await waitFor(() => expect(ordersCalls()).toBe(settled + 2), { timeout: 2000 });
    });
  });
});
