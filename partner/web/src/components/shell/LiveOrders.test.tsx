import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { StaffApiProvider } from '@rizqim/staff';
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
import { LiveOrdersProvider, LIVE_POLL_MS, POLL_MS, useLiveOrders } from './LiveOrders';

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

function setup({
  role = 'owner',
  branch = { id: null as number | null, scoped: false },
  config = CONFIG as RealtimeConfig | null,
  status = null as RealtimeStatus | null,
} = {}) {
  const state = { orders: [] as { uuid: string; status: string }[], revision: null as string | null };
  const { calls } = mockFetch({
    'POST /api/session/refresh': () => json({ access_token: 'a', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id: 1, name: 'A', role } }),
    'GET /api/v1/me/permissions': () =>
      ok({ role, store_id: 3, branch_id: branch.id, branch_scoped: branch.scoped, permissions: ['orders.view', 'orders.manage'], device_uuid: null }),
    'GET /api/v1/owner/branches': () => ok([{ id: 5, name: 'Chilonzor', working_hours: { timezone: 'Asia/Tashkent' } }]),
    'GET /api/v1/staff/orders': () => {
      const response = ok(state.orders, { meta: { next_cursor: null, per_page: 100 } });
      // Wave M2: a single-branch read names its change token (when the backend exposes the header)
      if (state.revision !== null && branch.id !== null) {
        response.headers.set('X-Revision', state.revision);
        response.headers.set('X-Revision-Scope', `branch:${branch.id}`);
      }
      return response;
    },
  });
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
                  <LiveOrdersProvider connect={connect} chime={chime} loadStatus={loadStatus}>
                    <Probe />
                  </LiveOrdersProvider>
                </BranchScopeProvider>
              </AuthProvider>
            </QueryClientProvider>
          </StaffApiProvider>
        </RealtimeConfigContext.Provider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
  const ordersCalls = () => calls.filter((call) => new URL(call.url).pathname === '/api/v1/staff/orders').length;
  return { state, echo, connect, chime, ordersCalls, unmount, loadStatus };
}

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

  it('refetches on an event and chimes for a new order', async () => {
    const { state, echo, chime, ordersCalls } = setup();
    await waitFor(() => expect(echo.channels).toHaveLength(1));
    echo.connectAll();
    await waitFor(() => expect(ordersCalls()).toBe(1));
    expect(chime.play).not.toHaveBeenCalled(); // what was there at first is not announced

    state.orders = [{ uuid: 'n1', status: 'new' }];
    echo.channel('store.3').emit('order.created', { order: { uuid: 'n1', status: 'new', store_id: 3, branch_id: 5 } });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent('1'));
    expect(ordersCalls()).toBe(2);
    expect(chime.play).toHaveBeenCalledTimes(1);
    expect(document.title).toBe('(1) Rizqim Hamkor');
    // Next sets another page's title (metadata) after a navigation: the count comes back in front of it
    document.title = 'Menyu | Rizqim Hamkor';
    await waitFor(() => expect(document.title).toBe('(1) Menyu | Rizqim Hamkor'));

    // An accepted order leaves the count, without a chime
    state.orders = [];
    echo.channel('store.3').emit('order.status_changed', { order: { uuid: 'n1', status: 'accepted' } });
    await waitFor(() => expect(screen.getByTestId('live')).toHaveTextContent(/ 0$/));
    expect(chime.play).toHaveBeenCalledTimes(1);
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

    it('the next token refetches, one already read fetches nothing, a gap reads everything', async () => {
      const { state, echo, ordersCalls } = setup({ role: 'cook', branch: { id: 5, scoped: true }, status: STATUS });
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

      // the next one: the usual refetch
      state.revision = '701';
      echo.channel('pos-branch.5').emit('order.status_changed', { order: { uuid: 'a', status: 'preparing' }, rev: { 'pos-branch.5': '701' } });
      await waitFor(() => expect(ordersCalls()).toBe(settled + 1));

      // a gap: everything again
      state.revision = '709';
      echo.channel('pos-branch.5').emit('order.status_changed', { order: { uuid: 'a', status: 'ready' }, rev: { 'pos-branch.5': '709' } });
      await waitFor(() => expect(ordersCalls()).toBe(settled + 2));
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
});
