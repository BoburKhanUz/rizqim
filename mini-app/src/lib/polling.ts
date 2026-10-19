// Polling policy shared by every screen that keeps itself fresh with react-query's `refetchInterval`.
//
//  - nothing is fetched while the document is hidden (refetchIntervalInBackground: false) and the data is
//    refreshed as soon as the user comes back, or the connection returns, if it is older than one interval;
//  - after failed polls the interval doubles (up to MAX_BACKOFF_MS) and returns to normal on the first success,
//    so an outage or an overloaded server is not hammered every few seconds.
//
// While the realtime socket is connected (lib/realtime.ts) the order screens only poll as a safety net, every
// REALTIME_POLL_INTERVAL_MS; when it is not they fall back to their own intervals below.

// Base intervals of the polled screens, in milliseconds.
export const POLL_INTERVAL_MS = {
  customerOrders: 10_000,
  managerOrders: 8_000,
  courierOrders: 15_000,
  adminOrders: 15_000, // analytics and reports share the query
  notifications: 15_000,
  ownerFinance: 10_000,
} as const;

export const REALTIME_POLL_INTERVAL_MS = 60_000;

export const MAX_BACKOFF_MS = 120_000;

// The base interval of a polled screen: its own, or the slow safety-net one while events arrive over the socket.
export function getPollInterval(baseMs: number, realtimeConnected: boolean): number {
  return realtimeConnected ? Math.max(baseMs, REALTIME_POLL_INTERVAL_MS) : baseMs;
}

// base * 2^failures, never above `maxMs` (and never below `baseMs`, whatever the arguments).
export function getBackoffInterval(baseMs: number, failures: number, maxMs: number = MAX_BACKOFF_MS): number {
  if (failures <= 0) return baseMs;
  const exponent = Math.min(failures, 30); // keep 2 ** n finite
  return Math.max(baseMs, Math.min(baseMs * 2 ** exponent, maxMs));
}

// The only parts of react-query's Query that matter here (any Query satisfies it).
export interface PollableQuery {
  state: { dataUpdateCount: number; errorUpdateCount: number };
}

interface FailureStreak {
  dataUpdateCount: number;
  errorUpdateCount: number;
  failures: number;
}

const streaks = new WeakMap<object, FailureStreak>();

// How many fetches of `query` failed in a row since its last successful update. react-query resets
// `fetchFailureCount` when every fetch starts and `errorUpdateCount` never goes down, so the streak is
// kept here and advanced each time the interval is recomputed (react-query does that on every state change).
export function getConsecutiveFailures(query: PollableQuery): number {
  const { dataUpdateCount, errorUpdateCount } = query.state;
  const previous = streaks.get(query);
  if (!previous) {
    streaks.set(query, { dataUpdateCount, errorUpdateCount, failures: 0 });
    return 0;
  }
  if (dataUpdateCount !== previous.dataUpdateCount) {
    previous.failures = 0; // a success happened since the last look
    previous.dataUpdateCount = dataUpdateCount;
    previous.errorUpdateCount = errorUpdateCount;
  } else if (errorUpdateCount > previous.errorUpdateCount) {
    previous.failures += errorUpdateCount - previous.errorUpdateCount;
    previous.errorUpdateCount = errorUpdateCount;
  }
  return previous.failures;
}

// Options to spread into useQuery for a polled query.
export function pollingQueryOptions(baseMs: number, maxMs: number = MAX_BACKOFF_MS) {
  return {
    refetchInterval: (query: PollableQuery) => getBackoffInterval(baseMs, getConsecutiveFailures(query), maxMs),
    refetchIntervalInBackground: false,
    // Data older than one interval is refreshed when the user returns to the app or the network comes back.
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
    staleTime: baseMs,
    // The next poll is the retry: don't multiply every failed poll by react-query's default 3 retries.
    retry: 1,
  } as const;
}
