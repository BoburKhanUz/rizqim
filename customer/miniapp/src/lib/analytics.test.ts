import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAnalytics, startKindOf, type AnalyticsClient, type AnalyticsOptions } from './analytics';

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

const signedIn = (c: AnalyticsClient, token = 'tok-1') => c.setAuth({ token, allowed: true });

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
    expect(body.start_param).toBe('c_tg_ads_1');
    expect(Object.keys(body).sort()).toEqual(['app_version', 'events', 'platform', 'session_id', 'start_param']);
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

  it('sends start_param once: only with the batch that carries the app_open', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('ref_77');
    await settle(10_500);
    c.track('store_view', { store_id: 1 });
    await settle(10_500);

    expect(sent).toHaveLength(2);
    expect(sent[0].body.start_param).toBe('ref_77');
    expect(sent[0].body.events[0].props).toEqual({ start_kind: 'referral' });
    expect(sent[1].body).not.toHaveProperty('start_param');
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
  });

  it('never sends a start_param the server would refuse (over 512 characters)', async () => {
    const c = make();
    signedIn(c);
    c.appOpen('c_' + 'a'.repeat(600));
    await settle(10_500);
    expect(sent[0].body).not.toHaveProperty('start_param');
    expect(sent[0].body.events[0].props).toEqual({ start_kind: 'unknown' });
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
      other.setAuth({ token: 't', allowed: true });
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
    c.setAuth({ token: 'new-token', allowed: true });
    await settle(1_000);
    expect(sent).toHaveLength(2);
    expect(new Headers(sent[1].init.headers).get('Authorization')).toBe('Bearer new-token');
    expect(sent[1].body.events[0].uid).toBe(sent[0].body.events[0].uid);
    expect(sent[1].body.events[0].at).toBe(T0);
    expect(sent[1].body.session_id).toBe(sent[0].body.session_id);
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
    c.setAuth({ token: 'staff-token', allowed: false });
    c.track('store_view', { store_id: 4 });
    await settle(60_000);
    expect(sent).toHaveLength(0);
    expect(c.pending()).toBe(0);
  });

  it('holds events while the role is not known yet (a token but no user)', async () => {
    const c = make();
    c.setAuth({ token: 'tok', allowed: null });
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
    c.setAuth({ token: 't', allowed: true });
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
