// Where the browser opens the Reverb socket (Laravel Reverb, Pusher protocol; docs/API.md section 8). Read on the Next
// server at request time from REVERB_APP_KEY, REVERB_HOST, REVERB_PORT and REVERB_SCHEME (see .env.example) and handed to
// the page by GET /api/realtime. null (real-time off, pages only poll) without a key or a host: API_BASE_URL is the Next
// server's own way to the backend and usually not reachable from a browser, so the host is never guessed from it.

export interface RealtimeConfig {
  key: string;
  host: string;
  port: number;
  forceTLS: boolean;
}

// The channel subscription is signed through this app's proxy (the admin token stays in its httpOnly cookie)
export const AUTH_ENDPOINT = '/api/backend/broadcasting/auth';

type Env = Record<string, string | undefined>;

export function realtimeConfigFromEnv(env: Env): RealtimeConfig | null {
  const key = env.REVERB_APP_KEY?.trim();
  const host = env.REVERB_HOST?.trim();
  if (!key || !host) return null;
  const scheme = (env.REVERB_SCHEME?.trim() || 'https').toLowerCase();
  const port = Number(env.REVERB_PORT);
  return { key, host, port: Number.isInteger(port) && port > 0 ? port : scheme === 'https' ? 443 : 80, forceTLS: scheme === 'https' };
}

export function isRealtimeConfig(value: unknown): value is RealtimeConfig {
  if (typeof value !== 'object' || value === null) return false;
  const config = value as Record<string, unknown>;
  return typeof config.key === 'string' && typeof config.host === 'string' && typeof config.port === 'number' && typeof config.forceTLS === 'boolean';
}
