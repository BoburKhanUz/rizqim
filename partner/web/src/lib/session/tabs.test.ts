import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch } from '@/test/helpers';

// Two tabs of one browser: two copies of the session module (each tab has its own memory) that share the refresh cookie,
// localStorage and the 'rizqim-session' BroadcastChannel. The fake below is the session route and the backend behind it:
// a refresh rotates the cookie and, like the backend, leaves exactly one live access token for the browser's device.
type Tab = typeof import('./client');

const user = { id: 7, name: 'Aziz', role: 'owner' };

function fakeBrowserServer() {
  let issued = 0;
  const live = new Set<string>();
  let gate: Promise<void> | null = null;
  const { calls } = mockFetch({
    'POST /api/session/refresh': () => {
      issued++;
      live.clear();
      live.add(`t${issued}`);
      return json({ access_token: `t${issued}`, expires_at: new Date(Date.now() + 15 * 60_000).toISOString(), user });
    },
    'POST /api/session/logout': () => json({ ok: true }),
    'GET /api/v1/orders': async (request) => {
      const token = request.headers.get('Authorization')?.replace('Bearer ', '') ?? '';
      // A request that is "in flight" while something else happens: its answer is decided once the gate opens
      if (gate) await gate;
      return live.has(token) ? json({ data: [] }) : json({ message: 'Unauthenticated.' }, 401);
    },
  });
  return {
    calls,
    refreshes: () => calls.filter((call) => call.url.endsWith('/api/session/refresh')).length,
    hold: () => {
      let open!: () => void;
      gate = new Promise<void>((resolve) => (open = resolve));
      return () => {
        gate = null;
        open();
      };
    },
  };
}

// navigator.locks as a browser has it on HTTPS/localhost: one holder of a name at a time, across the tabs
function installWebLocks() {
  const queues = new Map<string, Promise<unknown>>();
  const locks = {
    request: (name: string, run: () => Promise<unknown>) => {
      const previous = queues.get(name) ?? Promise.resolve();
      const next = previous.catch(() => undefined).then(run);
      queues.set(name, next);
      return next;
    },
  };
  Object.defineProperty(navigator, 'locks', { value: locks, configurable: true });
}

const tabs: Tab[] = [];

async function openTab(): Promise<Tab> {
  vi.resetModules();
  const tab = await import('./client');
  tabs.push(tab);
  return tab;
}

const orders = (tab: Tab) => tab.authFetch(new Request('http://api.test/api/v1/orders'));

beforeEach(() => localStorage.clear());

afterEach(() => {
  // Tabs of a finished test stop answering the others
  tabs.splice(0).forEach((tab) => tab.setSession(null));
  localStorage.clear();
  Reflect.deleteProperty(navigator, 'locks');
});

describe.each([
  ['with Web Locks (HTTPS, localhost)', true],
  ['without Web Locks (plain HTTP)', false],
])('two tabs %s', (_name, withLocks) => {
  beforeEach(() => {
    if (withLocks) installWebLocks();
  });

  it('a tab opened next to a signed-in one takes its session instead of spending the refresh token again', async () => {
    const server = fakeBrowserServer();
    const a = await openTab();
    const b = await openTab();

    await expect(a.refreshSession()).resolves.toMatchObject({ status: 'ok', session: { accessToken: 't1' } });
    await expect(b.refreshSession()).resolves.toMatchObject({ status: 'ok', session: { accessToken: 't1' } });

    expect(server.refreshes()).toBe(1);
    expect((await orders(a)).status).toBe(200);
    expect((await orders(b)).status).toBe(200);
  });

  it('a refresh in one tab reaches the other: no ping-pong of refreshes and 401s', async () => {
    const server = fakeBrowserServer();
    const a = await openTab();
    const b = await openTab();
    await a.refreshSession();
    await b.refreshSession();

    // B's token runs out and B renews it: A adopts the new session (its own token is no longer live)
    await b.refreshSession(b.getSession()?.accessToken ?? null);
    await vi.waitFor(() => expect(a.getSession()?.accessToken).toBe('t2'));

    for (let poll = 0; poll < 3; poll++) {
      expect((await orders(a)).status).toBe(200);
      expect((await orders(b)).status).toBe(200);
    }
    expect(server.refreshes()).toBe(2);
  });

  it('a request sent with the old token while the other tab refreshed is replayed with the shared session', async () => {
    const server = fakeBrowserServer();
    const a = await openTab();
    const b = await openTab();
    await a.refreshSession();
    await b.refreshSession();

    const release = server.hold();
    const pending = orders(a); // goes out with t1
    await vi.waitFor(() => expect(server.calls.filter((call) => call.url.endsWith('/orders'))).toHaveLength(1));
    await b.refreshSession(b.getSession()?.accessToken ?? null); // t2: the backend ends t1
    await vi.waitFor(() => expect(a.getSession()?.accessToken).toBe('t2'));
    release();

    const response = await pending;
    expect(response.status).toBe(200);
    const sent = server.calls.filter((call) => call.url.endsWith('/orders')).map((call) => call.headers.get('Authorization'));
    expect(sent).toEqual(['Bearer t1', 'Bearer t2']);
    expect(server.refreshes()).toBe(2);
  });

  it('a tab that finds its token refused right after another tab refreshed does not refresh again', async () => {
    const server = fakeBrowserServer();
    const a = await openTab();
    const b = await openTab();
    await a.refreshSession();
    await b.refreshSession();

    await b.refreshSession('t1');
    // A asks at once, before or after B's announcement reached it: either way it ends up with B's session
    await expect(a.refreshSession('t1')).resolves.toMatchObject({ status: 'ok', session: { accessToken: 't2' } });
    expect(server.refreshes()).toBe(2);
  });

  it('signing out in one tab signs every tab out', async () => {
    const server = fakeBrowserServer();
    const a = await openTab();
    const b = await openTab();
    await a.refreshSession();
    await b.refreshSession();

    await a.logout();
    await vi.waitFor(() => expect(b.getSession()).toBeNull());
    expect(server.calls.filter((call) => call.url.endsWith('/api/session/logout'))).toHaveLength(1);
  });
});

describe('signing out of an idle tab', () => {
  it('does not send an expired access token: the server trades the cookie for a fresh one and ends the session', async () => {
    const { calls } = mockFetch({ 'POST /api/session/logout': () => json({ ok: true }) });
    const tab = await openTab();
    tab.setSession({ accessToken: 'expired', expiresAt: Date.now() - 60_000, user });

    await tab.logout();

    expect(calls).toHaveLength(1);
    expect(calls[0]?.headers.get('Authorization')).toBeNull();
    expect(tab.getSession()).toBeNull();
  });
});
