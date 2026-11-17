// Wave M2: is real-time working right now, and how to connect (docs/API.md §8, GET /api/v1/realtime).
//
//  - GET /api/v1/realtime (public, no token) says whether the server's real-time path is healthy (`live`), the poll
//    intervals, the heartbeat silence limit and the Reverb connection (`reverb`, null when real-time is off). The apps
//    read it on start and after every reconnect, never on every poll.
//  - A staff channel (pos-branch, branch, store) hears `realtime.heartbeat` every 30 s: no event and no heartbeat for
//    `silence_seconds` means the socket looks up but nothing arrives (a stuck queue, a dead Reverb behind a proxy):
//    the screen polls fast until a heartbeat comes again.
//  - After a drop every device would reconnect at once (pusher-js retries within a second): the first reconnect is
//    spread over 0-10 s.

export interface ReverbSettings {
  key: string;
  host: string | null;
  port: number | null;
  scheme: string | null;
}

export interface RealtimeStatus {
  live: boolean;
  heartbeatSeconds: number;
  silenceSeconds: number;
  pollLiveSeconds: number;
  pollFallbackSeconds: number;
  reverb: ReverbSettings | null;
}

export const REALTIME_STATUS_PATH = '/v1/realtime';

const positive = (value: unknown, fallback: number): number =>
  typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : fallback;

// The `data` of GET /realtime (or the whole body when it is not wrapped); null when it is not that shape
export function parseRealtimeStatus(body: unknown): RealtimeStatus | null {
  if (typeof body !== 'object' || body === null) return null;
  const wrapped = (body as { data?: unknown }).data;
  const data = (typeof wrapped === 'object' && wrapped !== null ? wrapped : body) as Record<string, unknown>;
  if (typeof data.live !== 'boolean') return null;
  const poll = (typeof data.poll_seconds === 'object' && data.poll_seconds !== null ? data.poll_seconds : {}) as Record<string, unknown>;
  const reverbRaw = data.reverb as Record<string, unknown> | null | undefined;
  const key = typeof reverbRaw?.key === 'string' ? reverbRaw.key.trim() : '';
  const port = Number(reverbRaw?.port);
  return {
    live: data.live,
    heartbeatSeconds: positive(data.heartbeat_seconds, 30),
    silenceSeconds: positive(data.silence_seconds, 90),
    pollLiveSeconds: positive(poll.live, 60),
    pollFallbackSeconds: positive(poll.fallback, 15),
    reverb:
      key !== ''
        ? {
            key,
            host: typeof reverbRaw?.host === 'string' && reverbRaw.host.trim() !== '' ? reverbRaw.host.trim() : null,
            port: Number.isInteger(port) && port > 0 ? port : null,
            scheme: typeof reverbRaw?.scheme === 'string' && reverbRaw.scheme.trim() !== '' ? reverbRaw.scheme.trim().toLowerCase() : null,
          }
        : null,
  };
}

// GET {apiRoot}/v1/realtime; null when it cannot be read (an older backend, offline): the app then keeps its own
// build-time settings and polls as before. Never throws.
export async function fetchRealtimeStatus(apiRoot: string, send: (request: Request) => Promise<Response> = (request) => globalThis.fetch(request)): Promise<RealtimeStatus | null> {
  try {
    const url = `${apiRoot.replace(/\/+$/, '').replace(/\/v1$/, '')}${REALTIME_STATUS_PATH}`;
    const response = await send(new Request(absoluteUrl(url), { headers: { Accept: 'application/json' }, cache: 'no-store' }));
    if (!response.ok) return null;
    return parseRealtimeStatus(await response.json());
  } catch {
    return null;
  }
}

function absoluteUrl(url: string): string {
  if (/^https?:\/\//i.test(url) || typeof location === 'undefined' || !location.origin || location.origin === 'null') return url;
  return new URL(url, location.origin).toString();
}

export interface ReverbConnection {
  key: string;
  host: string;
  port: number;
  forceTLS: boolean;
}

// Where the socket goes, from the server's settings: a null host is the API's own host (its /app/ path), a null scheme
// or port the API's own. `apiUrl` is the API the app calls (absolute, or relative to `pageUrl`).
export function reverbConnectionOf(reverb: ReverbSettings, apiUrl: string, pageUrl?: string): ReverbConnection | null {
  let api: URL;
  try {
    api = new URL(apiUrl, pageUrl ?? (typeof location !== 'undefined' && location.origin && location.origin !== 'null' ? location.href : undefined));
  } catch {
    if (!reverb.host) return null;
    api = new URL(`https://${reverb.host}`);
  }
  const scheme = reverb.scheme ?? api.protocol.replace(':', '');
  const forceTLS = scheme === 'https' || scheme === 'wss';
  const host = reverb.host ?? api.hostname;
  if (!host) return null;
  const apiPort = Number(api.port);
  const port = reverb.port ?? (reverb.host === null && apiPort > 0 ? apiPort : forceTLS ? 443 : 80);
  return { key: reverb.key, host, port, forceTLS };
}

// --- Silence ---------------------------------------------------------------------------------------------------------

export interface SilenceWatch {
  // Something arrived on the channel (an event or a heartbeat)
  touch: () => void;
  // The channel is subscribed: the clock starts
  start: () => void;
  // The channel is gone (socket down, closed): no clock, and not "silent" either (the socket state says enough)
  stop: () => void;
  silent: () => boolean;
}

// Calls onChange(true) when `silenceMs` pass without touch() between start() and stop(), onChange(false) at the next
// touch. silenceMs <= 0 disables it (a server without heartbeats). A function is read at every (re)arming, so the limit
// can arrive later (GET /realtime answered after the socket opened) without a new watch.
export function createSilenceWatch(silenceMs: number | (() => number), onChange: (silent: boolean) => void): SilenceWatch {
  const limit = typeof silenceMs === 'function' ? silenceMs : () => silenceMs;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let running = false;
  let silent = false;

  const set = (value: boolean) => {
    if (silent === value) return;
    silent = value;
    onChange(value);
  };
  const arm = () => {
    clearTimeout(timer);
    const ms = limit();
    if (!running || !(ms > 0)) return;
    timer = setTimeout(() => set(true), ms);
  };

  return {
    touch: () => {
      if (!running) return;
      set(false);
      arm();
    },
    start: () => {
      running = true;
      arm();
    },
    stop: () => {
      running = false;
      clearTimeout(timer);
      set(false);
    },
    silent: () => silent,
  };
}

// --- Reconnect spread -------------------------------------------------------------------------------------------------

export const RECONNECT_SPREAD_MS = 10_000;

// The slice of a pusher-js client this needs (laravel-echo's connector.pusher)
export interface PusherLike {
  connect: () => void;
  disconnect: () => void;
  connection: {
    state?: string;
    bind: (event: 'state_change', callback: (states: { previous: string; current: string }) => void) => void;
    unbind?: (event: 'state_change', callback: (states: { previous: string; current: string }) => void) => void;
  };
}

// When a connected socket drops, pusher-js would try again within a second on every device at once (a Reverb restart
// brings every till and phone back together). This takes over that first attempt: the socket is closed and opened again
// after a random 0..spreadMs; if that attempt fails pusher-js goes on with its own back-off. Returns the dispose.
export function spreadFirstReconnect(pusher: PusherLike | null | undefined, spreadMs = RECONNECT_SPREAD_MS, random: () => number = Math.random): () => void {
  if (!pusher || typeof pusher.connection?.bind !== 'function') return () => {};
  let timer: ReturnType<typeof setTimeout> | undefined;
  let waiting = false;
  let disposed = false;

  const onState = ({ previous, current }: { previous: string; current: string }) => {
    if (disposed || waiting) return;
    if (previous === 'connected' && (current === 'connecting' || current === 'unavailable')) {
      waiting = true;
      pusher.disconnect();
      timer = setTimeout(() => {
        waiting = false;
        if (!disposed) pusher.connect();
      }, Math.floor(Math.max(0, Math.min(1, random())) * spreadMs));
    }
  };
  pusher.connection.bind('state_change', onState);
  return () => {
    disposed = true;
    clearTimeout(timer);
    pusher.connection.unbind?.('state_change', onState);
  };
}

// --- Coming back ------------------------------------------------------------------------------------------------------

// Calls `onReturn` when the page becomes visible again after having been hidden (a tab switch, a phone screen turned
// back on, Telegram reopened). Returns the unsubscribe function.
export function onPageReturn(onReturn: () => void): () => void {
  if (typeof document === 'undefined') return () => {};
  let hidden = document.visibilityState === 'hidden';
  const listener = () => {
    if (document.visibilityState === 'hidden') {
      hidden = true;
      return;
    }
    if (hidden) {
      hidden = false;
      onReturn();
    }
  };
  document.addEventListener('visibilitychange', listener);
  return () => document.removeEventListener('visibilitychange', listener);
}
