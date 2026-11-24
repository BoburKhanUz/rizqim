import type { AnalyticsAuth, AnalyticsClient, TrackFn } from './analyticsClient';

// First-party Mini App analytics: the app's entry point (wave P, step 3; docs/ANALYTICS_PLAN.md 4.1 and A.4, the
// contract and the event list are docs/API.md "Analitika hodisalari"). The client itself, its queue, batching and
// rules, is lib/analyticsClient.ts; this stub is all the entry bundle carries:
//
// - Off unless the build says VITE_ANALYTICS_ENABLED=true: then nothing is loaded, sent, queued or stored at all.
// - On: the client is downloaded on the first call (its own chunk). What the app tracks meanwhile (the launch, the
//   first screen, the sign-in) waits in a small buffer and is handed over in order, each call with its own time.
// - Never throws into the app and never waits on anything; if the chunk cannot be loaded, analytics is off for the page.

export type {
  AnalyticsAuth,
  AnalyticsCatalogue,
  AnalyticsEventName,
  AnalyticsOrderType,
  AnalyticsPaymentMethod,
  CheckoutErrorCode,
  StartKind,
  TrackFn,
} from './analyticsClient';

const MAX_BUFFERED = 200;

interface BufferedCall {
  at: number;
  run: (client: AnalyticsClient) => void;
  keep: boolean; // the launch and the sign-in: never dropped from a full buffer
}

export function createAnalyticsLoader(enabled: boolean, load: () => Promise<AnalyticsClient>, maxBuffered = MAX_BUFFERED) {
  let client: AnalyticsClient | null = null;
  let buffer: BufferedCall[] | null = enabled ? [] : null;
  let loading = false;

  const start = () => {
    if (loading) return;
    loading = true;
    load().then(
      (loaded) => {
        const calls = buffer ?? [];
        buffer = null;
        client = loaded;
        for (const call of calls) {
          try {
            loaded.at(call.at, () => call.run(loaded));
          } catch {
            // never into the app
          }
        }
      },
      () => {
        buffer = null; // analytics is off for this page
      },
    );
  };

  const call = (run: (client: AnalyticsClient) => void, keep: boolean) => {
    if (!enabled) return;
    try {
      if (client) {
        run(client);
        return;
      }
      if (buffer === null) return;
      if (keep || buffer.length < maxBuffered) buffer.push({ at: Date.now(), run, keep });
      start();
    } catch {
      // never into the app
    }
  };

  const track: TrackFn = (name, payload) => call((c) => c.track(name, payload), false);
  return {
    track,
    /** The launch: Telegram's start_param (only its kind is sent) and launch data (only a short hash is kept). */
    trackAppOpen: (startParam: string | null | undefined, launchData?: string | null) => call((c) => c.appOpen(startParam, launchData), true),
    setAnalyticsAuth: (auth: AnalyticsAuth) => call((c) => c.setAuth(auth), true),
  };
}

// VITE_ANALYTICS_ENABLED=true turns it on (off by default: the privacy policy has to say so first,
// docs/ANALYTICS_PLAN.md section 9); the server keeps its own switch (ANALYTICS_ENABLED) as well.
const analytics = createAnalyticsLoader(import.meta.env.VITE_ANALYTICS_ENABLED === 'true', () =>
  import('./analyticsClient').then((module) => module.createAppAnalytics()),
);

export const track: TrackFn = analytics.track;
export const trackAppOpen = analytics.trackAppOpen;
export const setAnalyticsAuth = analytics.setAnalyticsAuth;
