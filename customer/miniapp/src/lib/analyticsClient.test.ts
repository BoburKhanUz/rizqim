import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAnalytics, startKindOf, type AnalyticsClient, type AnalyticsOptions } from './analyticsClient';

// The queue, batching, retries and gating of the Mini App's first-party analytics (docs/ANALYTICS_PLAN.md A.4,
// docs/API.md "Analitika hodisalari"). The server is faked by `fetch` answers; time is faked.

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
const ORDER_UUID = '9b2f7c1e-3d4a-4b5c-8d6e-7f8091a2b3c4';
const T0 = Date.UTC(2026, 10, 24, 9, 0, 0);

interface Sent {
  url: string;
  init: RequestInit;
  time: number;
  body: {
    session_id: string;
    platform: string;
    app_version?: string;
    start_param?: string;
    events: Array<{ uid: string; name: string; at: number; store_id?: number; product_id?: number; category_id?: number; props?: Record<string, unknown> }>;
  };
}

function memoryStorage(): Storage {
  const data = new Map<string, string>();
  return {
    get length() {
      return data.size;
    },
    clear: () => data.clear(),
    getItem: (key) => data.get(key) ?? null,
    key: (index) => [...data.keys()][index] ?? null,
    removeItem: (key) => void data.delete(key),
    setItem: (key, value) => void data.set(key, String(value)),
  };
}

let sent: Sent[];
let answers: Array<number | Error | { status: number; retryAfter?: string }>;
let storage: Storage;
let client: AnalyticsClient | null;

const fakeFetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
  sent.push({ url: String(url), init: init ?? {}, time: Date.now(), body: JSON.parse(String(init?.body)) });
  const answer = answers.length > 0 ? answers.shift()! : 202;
  if (answer instanceof Error) throw answer;
  const { status, retryAfter } = typeof answer === 'number' ? { status: answer, retryAfter: undefined } : answer;
  return new Response(JSON.stringify({ success: status < 300, data: { accepted: 0, dropped: 0 } }), {
    status,
    headers: retryAfter ? { 'Retry-After': retryAfter } : {},
  });
});

function make(options: Partial<AnalyticsOptions> = {}): AnalyticsClient {
  client = createAnalytics({
    enabled: true,
    endpoint: '/api/v1/analytics/events',
    fetch: fakeFetch as unknown as typeof fetch,
    storage: () => storage,
    platform: () => 'ios',
    appVersion: '3fa9c1e0b2d4',
    ...options,
  });
  return client;
}

// Lets the awaited fetch and its handling finish (the timers themselves are fake)
async function settle(ms = 0) {
  await vi.advanceTimersByTimeAsync(ms);
}

const signedIn = (c: AnalyticsClient, token = 'tok-1', userId = 1) => c.setAuth({ token, allowed: true, userId });
const signedOut = (c: AnalyticsClient) => c.setAuth({ token: null, allowed: null, userId: null });
const authOf = (s: Sent) => new Headers(s.init.headers).get('Authorization');

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(T0);
  sent = [];
  answers = [];
  storage = memoryStorage();
  client = null;
  fakeFetch.mockClear();
});

afterEach(() => {
  client?.dispose();
  vi.useRealTimers();
});

describe('batching and the envelope', () => {
  it('sends the queued events in one batch about every 10 seconds, with the token and the envelope fields', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('c_tg_ads_1');
    c.track('store_view', { store_id: 12 });
    c.track('add_to_cart', { store_id: 12, product_id: 345, props: { qty: 2 } });

    await settle(9_000);
    expect(sent).toHaveLength(0);
    await settle(1_500);
    expect(sent).toHaveLength(1);

    const [{ url, init, body }] = sent;
    expect(url).toBe('/api/v1/analytics/events');
    expect(init.method).toBe('POST');
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer tok-1');
    expect(new Headers(init.headers).get('Content-Type')).toBe('application/json');
    expect(body.session_id).toMatch(UUID);
    expect(body.session_id).not.toBe('00000000-0000-0000-0000-000000000000');
    expect(body.platform).toBe('ios');
    expect(body.app_version).toBe('3fa9c1e0b2d4');
    expect(body).not.toHaveProperty('start_param');
    expect(Object.keys(body).sort()).toEqual(['app_version', 'events', 'platform', 'session_id']);
    expect(body.events.map((e) => e.name)).toEqual(['app_open', 'store_view', 'add_to_cart']);
    expect(body.events[0]).toMatchObject({ name: 'app_open', props: { start_kind: 'campaign', campaign_code: 'tg_ads_1' } });
    expect(body.events[2]).toEqual({ uid: expect.stringMatching(UUID), name: 'add_to_cart', at: T0, store_id: 12, product_id: 345, props: { qty: 2 } });
    for (const event of body.events) {
      expect(Number.isInteger(event.at)).toBe(true);
      expect(event.uid).toMatch(UUID);
    }
    expect(new Set(body.events.map((e) => e.uid)).size).toBe(3);
    expect(c.pending()).toBe(0);
  });

  it('never sends the raw start_param: only its kind (and a campaign code) in the app_open props', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('ref_77', 'launch-1');
    await settle(10_500);
    c.track('store_view', { store_id: 1 });
    await settle(10_500);
    c.appOpen('invite_secret_staff_token', 'launch-2');
    c.track('order_placed', { props: { order_uuid: ORDER_UUID } });
    await settle(10_500);

    expect(sent.length).toBeGreaterThanOrEqual(3);
    expect(sent[0].body.events[0].props).toEqual({ start_kind: 'referral' });
    for (const request of sent) {
      expect(request.body).not.toHaveProperty('start_param');
      expect(String(request.init.body)).not.toContain('ref_77');
      expect(String(request.init.body)).not.toContain('secret_staff_token');
    }
    expect(sent.flatMap((s) => s.body.events).find((e) => e.props?.start_kind === 'invite')).toBeDefined();
  });

  it('keeps the campaign code of a c_ link in the app_open props (the catalogue allowlists it)', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('c_tg_ads_1', 'launch-1');
    await settle(10_500);
    expect(sent[0].body.events[0].props).toEqual({ start_kind: 'campaign', campaign_code: 'tg_ads_1' });
    expect(sent[0].body).not.toHaveProperty('start_param');
  });

  it('leaves out an app_version that is neither a release number nor a build hash, and an empty platform becomes unknown', async () => {
    const c = make({ appVersion: 'my phone +998901234567', platform: () => '' });
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    await settle(10_500);
    expect(sent[0].body).not.toHaveProperty('app_version');
    expect(sent[0].body.platform).toBe('unknown');

    for (const [version, kept] of [['1.4.2', true], ['1.4.2+57', true], ['abc1234', true], ['ABC1234', false], ['1234567', false], ['', false]] as const) {
      const other = createAnalytics({ enabled: true, endpoint: '/e', fetch: fakeFetch as unknown as typeof fetch, storage: () => memoryStorage(), platform: () => 'android', appVersion: version });
      other.setAuth({ token: 't', allowed: true, userId: 1 });
      other.track('store_view', { store_id: 1 });
      await other.flush();
      expect('app_version' in sent[sent.length - 1].body, version).toBe(kept);
      other.dispose();
    }
  });

  it('splits more than 50 events into batches of at most 50, one request at a time', async () => {
    const c = make();
    signedIn(c);
    for (let i = 1; i <= 120; i++) c.track('store_view', { store_id: i });

    // The first request is still in flight: no second one is started next to it
    let release: () => void = () => {};
    fakeFetch.mockImplementationOnce(async (url, init) => {
      sent.push({ url: String(url), init: init ?? {}, time: Date.now(), body: JSON.parse(String(init?.body)) });
      await new Promise<void>((resolve) => (release = resolve));
      return new Response('{}', { status: 202 });
    });
    await settle(10_500);
    expect(sent).toHaveLength(1);
    await c.flush();
    expect(sent).toHaveLength(1);
    release();
    await settle(60_000);

    expect(sent.map((s) => s.body.events.length)).toEqual([50, 50, 20]);
    expect(sent.flatMap((s) => s.body.events.map((e) => e.store_id))).toEqual(Array.from({ length: 120 }, (_, i) => i + 1));
    expect(c.pending()).toBe(0);
  });

  it('keeps every request body within 32 KB', async () => {
    const c = make();
    signedIn(c);
    for (let i = 0; i < 49; i++) {
      c.track('checkout_start', { store_id: 1_000_000 + i, props: { items: 99_999, value: 9_999_999_999.99, payment_method: 'paylov', order_type: 'delivery' } });
    }
    await settle(70_000);
    for (const request of sent) {
      expect(new TextEncoder().encode(String(request.init.body)).length).toBeLessThanOrEqual(32 * 1024);
      expect(request.body.events.length).toBeLessThanOrEqual(50);
    }
    expect(sent.reduce((sum, s) => sum + s.body.events.length, 0)).toBe(49);
  });

  it('sends an order_placed (and a payment redirect) at once, not 10 seconds later', async () => {
    const c = make();
    signedIn(c);
    c.track('checkout_start', { store_id: 1 });
    c.track('order_placed', { props: { order_uuid: ORDER_UUID } });
    await settle(100);
    expect(sent).toHaveLength(1);
    expect(sent[0].body.events.map((e) => e.name)).toEqual(['checkout_start', 'order_placed']);
  });

  it('bounds the queue: past the cap the oldest events are dropped', () => {
    const c = make({ maxQueue: 5 });
    for (let i = 1; i <= 8; i++) c.track('store_view', { store_id: i });
    expect(c.pending()).toBe(5);
  });

  it('does not send more than a few requests a minute, however often it is asked to', async () => {
    const c = make();
    signedIn(c);
    for (let second = 0; second < 180; second++) {
      c.track('store_view', { store_id: second + 1 });
      await c.flush();
      if (second % 2 === 0) window.dispatchEvent(new Event('pagehide'));
      await settle(1_000);
    }
    await settle(5 * 60_000);
    const times = sent.map((s) => s.time);
    for (const start of times) {
      expect(times.filter((t) => t >= start && t < start + 60_000).length).toBeLessThanOrEqual(10);
    }
    // ...and nothing is lost on the way
    expect(sent.reduce((sum, s) => sum + s.body.events.length, 0)).toBe(180);
  });
});

describe('answers and retries', () => {
  it('takes 202 as done, also when the server accepted nothing (switched off)', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    await settle(10_500);
    expect(c.pending()).toBe(0);
    await settle(60_000);
    expect(sent).toHaveLength(1);
  });

  it('retries 429, 5xx and network failures with the same uids, with backoff', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    c.track('product_view', { store_id: 1, product_id: 2 });
    answers = [503, new TypeError('Failed to fetch'), 202];

    await settle(10_500);
    expect(sent).toHaveLength(1);
    await settle(5_000);
    expect(sent).toHaveLength(1); // backing off
    await settle(120_000);
    expect(sent).toHaveLength(3);

    const uids = sent.map((s) => s.body.events.map((e) => e.uid).join(','));
    expect(new Set(uids).size).toBe(1);
    expect(new Set(sent.map((s) => s.body.session_id)).size).toBe(1);
    expect(sent.map((s) => s.body.events[0].at)).toEqual([T0, T0, T0]);
    expect(c.pending()).toBe(0);
  });

  it('waits as long as Retry-After says on a 429', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    answers = [{ status: 429, retryAfter: '45' }];
    await settle(10_500);
    await settle(40_000);
    expect(sent).toHaveLength(1);
    await settle(10_000);
    expect(sent).toHaveLength(2);
  });

  it('gives a batch up after 3 failed tries', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    answers = [500, 500, 500, 500, 500];
    await settle(15 * 60_000);
    expect(sent).toHaveLength(3);
    expect(c.pending()).toBe(0);
  });

  it.each([413, 422])('drops the batch on %i and goes on with the next one', async (status) => {
    const c = make();
    signedIn(c);
    for (let i = 1; i <= 60; i++) c.track('store_view', { store_id: i });
    answers = [status];
    await settle(10_500);
    await settle(30_000);
    expect(sent).toHaveLength(2);
    expect(sent[1].body.events.map((e) => e.store_id)).toEqual(Array.from({ length: 10 }, (_, i) => 51 + i));
    expect(c.pending()).toBe(0);
  });

  it('on 401 keeps the events and waits for a new token, then sends them with the same session and times', async () => {
    const c = make();
    signedIn(c, 'old-token');
    c.track('store_view', { store_id: 1 });
    answers = [401];
    await settle(10_500);
    expect(sent).toHaveLength(1);
    await settle(5 * 60_000);
    expect(sent).toHaveLength(1); // not with the token that was refused
    expect(c.pending()).toBe(1);

    vi.setSystemTime(T0 + 6 * 60_000);
    c.setAuth({ token: 'new-token', allowed: true, userId: 1 });
    await settle(1_000);
    expect(sent).toHaveLength(2);
    expect(new Headers(sent[1].init.headers).get('Authorization')).toBe('Bearer new-token');
    expect(sent[1].body.events[0].uid).toBe(sent[0].body.events[0].uid);
    expect(sent[1].body.events[0].at).toBe(T0);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
  });

  it('a 401 that comes back after the token was already renewed sends again with the new token', async () => {
    const c = make();
    signedIn(c, 'old-token');
    c.track('store_view', { store_id: 1 });
    const release = holdNext(401);
    await settle(10_500);
    expect(sent).toHaveLength(1);
    signedIn(c, 'new-token'); // the app renewed the token while the batch was on its way
    await settle(100);
    release();
    await settle(5_000);
    expect(sent).toHaveLength(2);
    expect(authOf(sent[1])).toBe('Bearer new-token');
    expect(c.pending()).toBe(0);
  });

  it('never throws into the app, whatever fetch or the storage do', async () => {
    const throwing = {
      getItem: () => {
        throw new DOMException('denied', 'SecurityError');
      },
      setItem: () => {
        throw new DOMException('full', 'QuotaExceededError');
      },
    } as unknown as Storage;
    const c = make({
      storage: () => throwing,
      fetch: (() => {
        throw new Error('boom');
      }) as unknown as typeof fetch,
    });
    signedIn(c);
    expect(() => c.appOpen('c_x_y')).not.toThrow();
    expect(() => c.track('store_view', { store_id: 1 })).not.toThrow();
    // @ts-expect-error a wrong call from untyped code is dropped, not thrown
    expect(() => c.track('no_such_event', {})).not.toThrow();
    await expect(c.flush()).resolves.toBeUndefined();
    await settle(60_000);
  });
});

describe('sign-in gating and staff', () => {
  it('holds the events from before sign-in and sends them after it, with their own time', async () => {
    const c = make();
    c.appOpen(null);
    c.track('store_view', { store_id: 3 });
    await settle(60_000);
    expect(sent).toHaveLength(0);
    expect(c.pending()).toBe(2);

    vi.setSystemTime(T0 + 61_000);
    signedIn(c);
    await settle(1_000);
    expect(sent).toHaveLength(1);
    expect(sent[0].body.events.map((e) => [e.name, e.at])).toEqual([['app_open', T0], ['store_view', T0]]);
    expect(sent[0].body.events[0].props).toEqual({ start_kind: 'none' });
  });

  it('sends nothing for staff, and forgets what was held', async () => {
    const c = make();
    c.appOpen(null);
    c.track('store_view', { store_id: 3 });
    c.setAuth({ token: 'staff-token', allowed: false, userId: 5 });
    c.track('store_view', { store_id: 4 });
    await settle(60_000);
    expect(sent).toHaveLength(0);
    expect(c.pending()).toBe(0);
  });

  it('holds events while the role is not known yet (a token but no user)', async () => {
    const c = make();
    c.setAuth({ token: 'tok', allowed: null, userId: null });
    c.track('store_view', { store_id: 3 });
    await settle(60_000);
    expect(sent).toHaveLength(0);
    expect(c.pending()).toBe(1);
  });
});

describe('the session', () => {
  it('keeps one session id in sessionStorage and stores nothing in localStorage', async () => {
    localStorage.clear();
    const c = make({ storage: () => sessionStorage });
    sessionStorage.clear();
    signedIn(c);
    c.appOpen(null);
    c.track('store_view', { store_id: 1 });
    await settle(10_500);
    const saved = JSON.parse(sessionStorage.getItem('rz_session') ?? 'null');
    expect(saved.id).toBe(sent[0].body.session_id);
    expect(localStorage.length).toBe(0);
    sessionStorage.clear();
  });

  it('keeps the session across a reload and does not open it twice', async () => {
    const first = make();
    signedIn(first);
    first.appOpen('c_abc');
    await settle(10_500);
    first.dispose();

    vi.setSystemTime(T0 + 60_000);
    const second = make();
    signedIn(second);
    second.appOpen('c_abc');
    second.track('store_view', { store_id: 1 });
    await settle(10_500);
    expect(sent).toHaveLength(2);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
    expect(sent[1].body.events.map((e) => e.name)).toEqual(['store_view']);
  });

  it('rotates the session after 30 minutes without events and opens the new one', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('c_abc');
    c.track('store_view', { store_id: 1 });
    await settle(10_500);

    vi.setSystemTime(T0 + 29 * 60_000);
    c.track('store_view', { store_id: 2 }); // within 30 min: the same session, and it is now active again
    await settle(10_500);
    vi.setSystemTime(T0 + 29 * 60_000 + 31 * 60_000);
    c.track('store_view', { store_id: 3 });
    await settle(10_500);

    expect(sent).toHaveLength(3);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
    expect(sent[2].body.session_id).not.toBe(sent[0].body.session_id);
    expect(sent[2].body.session_id).toMatch(UUID);
    expect(sent[2].body.events.map((e) => e.name)).toEqual(['app_open', 'store_view']);
    expect(sent[2].body.events[0].props).toEqual({ start_kind: 'none' });
    expect(sent[2].body).not.toHaveProperty('start_param');
  });

  it('sends events of two sessions in separate batches, each with its own session id', async () => {
    const c = make();
    c.track('store_view', { store_id: 1 });
    vi.setSystemTime(T0 + 31 * 60_000);
    c.track('store_view', { store_id: 2 });
    signedIn(c);
    await settle(60_000);
    expect(sent).toHaveLength(2);
    expect(sent[0].body.session_id).not.toBe(sent[1].body.session_id);
    expect(sent[0].body.events.map((e) => e.store_id)).toEqual([1]);
    expect(sent[1].body.events.map((e) => e.store_id)).toEqual([undefined, 2]); // app_open of the new session first
  });

  it('works without storage (blocked or throwing): a session in memory', async () => {
    const c = make({ storage: () => null });
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    c.track('store_view', { store_id: 2 });
    await settle(10_500);
    expect(sent).toHaveLength(1);
    expect(sent[0].body.session_id).toMatch(UUID);
  });
});

describe('page hide', () => {
  it('flushes with keepalive when the page is hidden or closed', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
    await settle(0);
    expect(sent).toHaveLength(1);
    expect(sent[0].init.keepalive).toBe(true);

    c.track('store_view', { store_id: 2 });
    await settle(3_000);
    window.dispatchEvent(new Event('pagehide'));
    await settle(0);
    expect(sent).toHaveLength(2);
    expect(sent[1].init.keepalive).toBe(true);
  });

  it('flushes on hide even right after another request (the page may be gone next)', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    await c.flush();
    expect(sent).toHaveLength(1);
    c.track('store_view', { store_id: 2 });
    await settle(200);
    window.dispatchEvent(new Event('pagehide'));
    await settle(0);
    expect(sent).toHaveLength(2);
    expect(sent[1].body.events.map((e) => e.store_id)).toEqual([2]);
  });

  it('does not flush when the page becomes visible', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    document.dispatchEvent(new Event('visibilitychange'));
    await settle(0);
    expect(sent).toHaveLength(0);
  });
});

describe('kill switch', () => {
  it('when disabled sends nothing, queues nothing and touches no storage', async () => {
    const getItem = vi.fn();
    const c = make({ enabled: false, storage: () => ({ getItem, setItem: getItem }) as unknown as Storage });
    c.setAuth({ token: 't', allowed: true, userId: 1 });
    c.appOpen('c_abc');
    c.track('store_view', { store_id: 1 });
    await c.flush();
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
    await settle(60_000);
    expect(fakeFetch).not.toHaveBeenCalled();
    expect(getItem).not.toHaveBeenCalled();
    expect(c.pending()).toBe(0);
  });
});

describe('only catalogue fields leave the device', () => {
  it('sends the length and the count of a search, never its text', async () => {
    const c = make();
    signedIn(c);
    c.track('search', { props: { results: 4, length: 'osh markazi'.length } });
    // Untyped code passing more is cut down to the allowlist
    c.track('search', { props: { results: 1, length: 3, query: 'osh', text: 'osh' } as never });
    c.track('add_to_cart', { store_id: 1, product_id: 2, props: { qty: 1 }, name: 'Lavash', phone: '+998901234567' } as never);
    await settle(10_500);
    const raw = String(sent[0].init.body);
    expect(raw).not.toContain('osh');
    expect(raw).not.toContain('Lavash');
    expect(raw).not.toContain('998901234567');
    expect(sent[0].body.events.map((e) => e.props)).toEqual([{ results: 4, length: 11 }, { results: 1, length: 3 }, { qty: 1 }]);
    for (const event of sent[0].body.events) {
      expect(Object.keys(event).every((key) => ['uid', 'name', 'at', 'store_id', 'product_id', 'category_id', 'props'].includes(key))).toBe(true);
    }
  });

  it('drops an event without its required id, or with a non-integer id, and a prop value off its rule', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: Number.NaN });
    c.track('store_view', { store_id: '12' as never });
    c.track('product_view', { store_id: 1 } as never);
    c.track('checkout_error', { store_id: 1, props: { code: 'teapot' as never } });
    c.track('order_placed', { props: { order_uuid: 'not-a-uuid' } });
    c.track('checkout_start', { store_id: 1, props: { items: 2, value: 50000, payment_method: 'naqd' as never, order_type: 'pickup' } });
    c.track('order_placed', { props: { order_uuid: ORDER_UUID } });
    await settle(10_500);
    expect(sent[0].body.events).toEqual([
      expect.objectContaining({ name: 'checkout_start', store_id: 1, props: { items: 2, value: 50000, order_type: 'pickup' } }),
      expect.objectContaining({ name: 'order_placed', props: { order_uuid: ORDER_UUID } }),
    ]);
  });

  it('rejects at compile time a prop or an id that is not in the catalogue', () => {
    const c = make({ enabled: false });
    // @ts-expect-error search carries no text
    c.track('search', { props: { results: 1, length: 3, query: 'osh' } });
    // @ts-expect-error store_view has no product
    c.track('store_view', { store_id: 1, product_id: 2 });
    // @ts-expect-error store_id is required
    c.track('store_view', {});
    // @ts-expect-error an unknown event
    c.track('page_view', {});
    // @ts-expect-error payment methods are the server's names
    c.track('checkout_start', { store_id: 1, props: { payment_method: 'naqd' } });
    const extra = { store_id: 1, props: { qty: 1, note: 'x' } };
    // @ts-expect-error an object with an extra prop is refused too, not only a literal
    c.track('add_to_cart', { ...extra, product_id: 2 });
    expect(c.pending()).toBe(0);
  });
});

describe('startKindOf', () => {
  it.each([
    [null, { start_kind: 'none' }],
    ['', { start_kind: 'none' }],
    ['c_tg_ads_1', { start_kind: 'campaign', campaign_code: 'tg_ads_1' }],
    ['C_Insta', { start_kind: 'campaign', campaign_code: 'insta' }],
    ['c_x', { start_kind: 'unknown' }],
    ['ref_5', { start_kind: 'referral' }],
    ['rizqim_12', { start_kind: 'referral' }],
    ['referrer_9', { start_kind: 'referral' }],
    ['invite_abc', { start_kind: 'invite' }],
    ['store_0b6f1c9e', { start_kind: 'store' }],
    ['s_12', { start_kind: 'store' }],
    ['hello', { start_kind: 'unknown' }],
  ] as const)('%s', (param, expected) => {
    expect(startKindOf(param)).toEqual(expected);
  });
});

// A fetch whose answer waits until the test releases it (a request still in flight)
function holdNext(status = 202): () => void {
  let release: () => void = () => {};
  const gate = new Promise<void>((resolve) => (release = resolve));
  fakeFetch.mockImplementationOnce(async (url, init) => {
    sent.push({ url: String(url), init: init ?? {}, time: Date.now(), body: JSON.parse(String(init?.body)) });
    await gate;
    return new Response('{}', { status });
  });
  return () => release();
}

const hide = () => {
  vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
  document.dispatchEvent(new Event('visibilitychange'));
};

describe('the account the events were recorded under', () => {
  it("never sends A's queued events with B's token after a sign-out and a sign-in in one tab", async () => {
    const c = make();
    signedIn(c, 'tok-a', 1);
    c.appOpen(null, 'launch-1');
    c.track('store_view', { store_id: 101 });
    signedOut(c);
    c.track('store_view', { store_id: 300 }); // nobody known: belongs to whoever signs in next
    signedIn(c, 'tok-b', 2);
    c.track('store_view', { store_id: 202 });
    await settle(60_000);

    const withB = sent.filter((s) => authOf(s) === 'Bearer tok-b');
    expect(withB.length).toBeGreaterThan(0);
    expect(withB.flatMap((s) => s.body.events.map((e) => e.store_id))).not.toContain(101);
    expect(withB.flatMap((s) => s.body.events.map((e) => e.store_id))).toEqual(expect.arrayContaining([300, 202]));
    expect(sent.every((s) => authOf(s) === 'Bearer tok-b')).toBe(true); // A's were never sent at all: A signed out first
    expect(c.pending()).toBe(0);
  });

  it("drops what a 401 kept for A when B signs in, and never sends it with B's token", async () => {
    const c = make();
    signedIn(c, 'tok-a', 1);
    c.track('store_view', { store_id: 101 });
    answers = [401];
    await settle(10_500);
    expect(c.pending()).toBe(1);
    signedIn(c, 'tok-b', 2);
    c.track('store_view', { store_id: 202 });
    await settle(60_000);
    const withB = sent.filter((s) => authOf(s) === 'Bearer tok-b');
    expect(withB.flatMap((s) => s.body.events.map((e) => e.store_id))).not.toContain(101);
    expect(withB.flatMap((s) => s.body.events.map((e) => e.store_id))).toContain(202);
  });

  it('starts a new session, with its own app_open, when the signed-in user changes', async () => {
    const c = make();
    signedIn(c, 'tok-a', 1);
    c.appOpen(null, 'launch-1');
    c.track('store_view', { store_id: 1 });
    await settle(10_500);
    signedOut(c);
    signedIn(c, 'tok-b', 2);
    c.track('store_view', { store_id: 2 });
    await settle(10_500);

    expect(sent).toHaveLength(2);
    expect(sent[1].body.session_id).not.toBe(sent[0].body.session_id);
    expect(sent[1].body.events.map((e) => e.name)).toEqual(['app_open', 'store_view']);
  });

  it('keeps the session when the same user signs in again (a renewed token)', async () => {
    const c = make();
    signedIn(c, 'tok-a', 1);
    c.appOpen(null, 'launch-1');
    await settle(10_500);
    signedIn(c, 'tok-a2', 1);
    c.track('store_view', { store_id: 2 });
    await settle(10_500);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
    expect(sent[1].body.events.map((e) => e.name)).toEqual(['store_view']);
  });

  it('a session restored after a reload that belonged to another user is not reused', async () => {
    const first = make();
    signedIn(first, 'tok-a', 1);
    first.appOpen(null, 'launch-1');
    await settle(10_500);
    first.dispose();

    const second = make();
    second.appOpen(null, 'launch-1');
    signedIn(second, 'tok-b', 2);
    second.track('store_view', { store_id: 2 });
    await settle(10_500);
    const ofB = sent.filter((s) => authOf(s) === 'Bearer tok-b');
    expect(ofB[0].body.session_id).not.toBe(sent[0].body.session_id);
    expect(ofB[0].body.events[0].name).toBe('app_open');
  });
});

describe('a page hide while a request is in flight', () => {
  it('sends the urgent events with keepalive, and on hide sends what is not in flight next to it', async () => {
    const c = make();
    signedIn(c);
    c.track('checkout_start', { store_id: 1 });
    const releaseFirst = holdNext();
    const releaseSecond = holdNext();
    c.track('order_placed', { props: { order_uuid: ORDER_UUID } });
    await settle(10);
    expect(sent).toHaveLength(1);
    expect(sent[0].init.keepalive).toBe(true); // urgent: may outlive the page

    c.track('payment_redirect', { store_id: 1 });
    await settle(10);
    expect(sent).toHaveLength(1); // one at a time while the page is shown
    hide();
    await settle(0);
    expect(sent).toHaveLength(2);
    expect(sent[1].init.keepalive).toBe(true);
    expect(sent[1].body.events.map((e) => e.name)).toEqual(['payment_redirect']);

    // never a third next to two in flight (nginx allows 2 per client)
    c.track('store_view', { store_id: 9 });
    window.dispatchEvent(new Event('pagehide'));
    await settle(0);
    expect(sent).toHaveLength(2);

    releaseFirst();
    releaseSecond();
    await settle(30_000);
    expect(c.pending()).toBe(0);
    const names = sent.flatMap((s) => s.body.events.map((e) => e.name));
    expect(names.filter((n) => n === 'order_placed')).toHaveLength(1);
    expect(names.filter((n) => n === 'payment_redirect')).toHaveLength(1);
  });

  it('keeps two keepalive bodies in flight within the browser limit of 64 KB together', async () => {
    const c = make();
    signedIn(c);
    for (let i = 0; i < 49; i++) {
      c.track('checkout_start', { store_id: 1_000_000 + i, props: { items: 99_999, value: 9_999_999_999.99, payment_method: 'paylov', order_type: 'delivery' } });
    }
    const release = holdNext();
    c.track('order_placed', { props: { order_uuid: ORDER_UUID } });
    await settle(10);
    for (let i = 0; i < 49; i++) {
      c.track('checkout_start', { store_id: 2_000_000 + i, props: { items: 99_999, value: 9_999_999_999.99, payment_method: 'paylov', order_type: 'delivery' } });
    }
    hide();
    await settle(0);
    expect(sent).toHaveLength(2);
    const bytes = sent.map((s) => new TextEncoder().encode(String(s.init.body)).length);
    expect(bytes[0] + bytes[1]).toBeLessThanOrEqual(64 * 1024);
    release();
    await settle(5 * 60_000);
    expect(c.pending()).toBe(0);
  });
});

describe('a relaunch into the session of the same tab', () => {
  it('opens a new session for a launch with new start data, with its own app_open and kind', async () => {
    const first = make();
    signedIn(first);
    first.appOpen('c_abc', 'query_id=1&auth_date=100&hash=aa');
    await settle(10_500);
    first.dispose();

    vi.setSystemTime(T0 + 5 * 60_000);
    const second = make();
    signedIn(second);
    second.appOpen('ref_5', 'query_id=2&auth_date=400&hash=bb');
    await settle(10_500);
    expect(sent).toHaveLength(2);
    expect(sent[1].body.session_id).not.toBe(sent[0].body.session_id);
    expect(sent[1].body.events[0]).toMatchObject({ name: 'app_open', props: { start_kind: 'referral' } });
  });

  it('opens a new session for a relaunch with the same start_param but new launch data', async () => {
    const first = make();
    signedIn(first);
    first.appOpen(null, 'auth_date=100&hash=aa');
    await settle(10_500);
    first.dispose();
    const second = make();
    signedIn(second);
    second.appOpen(null, 'auth_date=400&hash=bb');
    await settle(10_500);
    expect(sent[1].body.session_id).not.toBe(sent[0].body.session_id);
    expect(sent[1].body.events[0].name).toBe('app_open');
  });

  it('a plain reload with the same launch data stays in the session and is not counted twice', async () => {
    const first = make();
    signedIn(first);
    first.appOpen('c_abc', 'auth_date=100&hash=aa');
    await settle(10_500);
    first.dispose();
    vi.setSystemTime(T0 + 60_000);
    const second = make();
    signedIn(second);
    second.appOpen('c_abc', 'auth_date=100&hash=aa');
    second.track('store_view', { store_id: 1 });
    await settle(10_500);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
    expect(sent[1].body.events.map((e) => e.name)).toEqual(['store_view']);
  });

  it('stores only a short marker of the launch, never the start_param or the launch data', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('c_secret_campaign', 'user=%7B%22id%22%3A777%7D&auth_date=100&hash=deadbeef');
    await settle(10_500);
    const raw = storage.getItem('rz_session') ?? '';
    expect(raw).not.toBe('');
    for (const secret of ['secret_campaign', 'deadbeef', 'user=', '%7B', 'auth_date']) expect(raw).not.toContain(secret);
    expect(raw.length).toBeLessThan(160);
  });
});

describe('staff and the session record', () => {
  it('writes no session record before the user is known to be a customer', () => {
    const c = make();
    c.appOpen('c_abc', 'launch-1');
    c.track('store_view', { store_id: 1 });
    expect(storage.getItem('rz_session')).toBeNull();
    c.setAuth({ token: 'tok', allowed: null, userId: null });
    expect(storage.getItem('rz_session')).toBeNull();
    signedIn(c);
    expect(storage.getItem('rz_session')).not.toBeNull();
  });

  it('leaves nothing in sessionStorage for staff', async () => {
    const c = make();
    c.appOpen('c_abc', 'launch-1');
    c.track('store_view', { store_id: 1 });
    c.setAuth({ token: 'staff', allowed: false, userId: 9 });
    c.track('store_view', { store_id: 2 });
    await settle(60_000);
    expect(storage.length).toBe(0);
    expect(sent).toHaveLength(0);
  });

  it('removes the record a customer left in the tab when a staff account signs in', () => {
    const c = make();
    signedIn(c);
    c.appOpen(null, 'launch-1');
    expect(storage.getItem('rz_session')).not.toBeNull();
    c.setAuth({ token: 'staff', allowed: false, userId: 9 });
    expect(storage.getItem('rz_session')).toBeNull();
  });
});

describe('retries on a flaky network', () => {
  it('does not count network errors toward the 3 tries: the batch goes once the network is back', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    const offline = Array.from({ length: 8 }, () => new TypeError('Failed to fetch'));
    answers = [...offline, 202];
    await settle(60 * 60_000);
    expect(sent).toHaveLength(9);
    expect(c.pending()).toBe(0);
    expect(new Set(sent.map((s) => s.body.events[0].uid)).size).toBe(1);
    // the backoff is capped: never more than 5 minutes between two tries
    for (let i = 1; i < sent.length; i++) expect(sent[i].time - sent[i - 1].time).toBeLessThanOrEqual(5 * 60_000 + 1);
  });

  it('a failed keepalive flush on hide does not spend a try', async () => {
    const c = make();
    signedIn(c);
    c.track('store_view', { store_id: 1 });
    answers = [500, 500, 500, 202];
    await settle(10_500); // try 1
    await settle(25_000); // try 2 (20 s backoff)
    expect(sent).toHaveLength(2);
    window.dispatchEvent(new Event('pagehide')); // the page goes: a last chance, not a try
    await settle(0);
    expect(sent).toHaveLength(3);
    expect(sent[2].init.keepalive).toBe(true);
    await settle(10 * 60_000);
    expect(sent).toHaveLength(4);
    expect(c.pending()).toBe(0);
  });

  it('a hide flush waits as long as Retry-After said after a 429 or a 503', async () => {
    for (const status of [429, 503]) {
      sent = [];
      const c = make();
      signedIn(c);
      c.track('store_view', { store_id: 1 });
      answers = [{ status, retryAfter: '60' }];
      await settle(10_500);
      expect(sent).toHaveLength(1);
      await settle(20_000);
      window.dispatchEvent(new Event('pagehide'));
      await settle(0);
      expect(sent, String(status)).toHaveLength(1);
      await settle(45_000);
      expect(sent).toHaveLength(2);
      c.dispose();
    }
  });
});
