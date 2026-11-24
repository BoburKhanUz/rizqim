import axios, { type AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from '../store/authStore';
import { getInitData, tg } from '../lib/telegram';
import { noteResponseRevision } from '@rizqim/staff';
import { revisions } from '../lib/revisions';
import { dropStoredSession, isForeignSession, sessionToken } from '../lib/session';
import type { ApiResponse, LoginPayload } from '../types';


const clientConfig = {
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Bypass-Tunnel-Reminder': 'true',
  },
};

const api = axios.create(clientConfig);

// Plain client used only to renew the token: it has no interceptors, so it can never recurse into the 401 handling below.
const authClient = axios.create(clientConfig);

type AuthAwareRequestConfig = InternalAxiosRequestConfig & {
  _authToken?: string | null; // token this request was sent with
  _authRetried?: boolean; // already replayed once after a re-authentication
};

// Request Interceptor to attach token
api.interceptors.request.use(
  (config) => {
    // Never a session stored for another Telegram user (lib/session.ts): it is dropped and the request goes without one
    const token = sessionToken();
    (config as AuthAwareRequestConfig)._authToken = token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Tokens expire (30 days) and can be pruned. On a 401 we log in again with the Telegram initData of this
// launch and replay the request once. Note the backend only accepts initData younger than 10 minutes,
// so this recovers the common case (opening the app with an old stored token), not a long-running session.
const REAUTH_COOLDOWN_MS = 30_000;
let reauthPromise: Promise<string | null> | null = null;
let lastReauthFailureAt = 0;

// Resolves with the new token, or null when re-authentication isn't possible (no initData, rejected, offline).
async function requestFreshToken(): Promise<string | null> {
  const initData = getInitData();
  if (!initData) return null;

  try {
    const res = await authClient.post<ApiResponse<LoginPayload>>('/auth/telegram', {
      initData,
      bot_username: import.meta.env.VITE_BOT_USERNAME || 'rizqimbot',
      start_param: tg.initDataUnsafe?.start_param || null,
    }, { timeout: 10000 });

    const data = res.data?.data;
    if (res.data?.success && data?.token) {
      useAuthStore.getState().setAuth(data.token, data.user, data.store, data.sms_verification_required);
      // The answer is for another Telegram account than the one that has the app open: not used
      if (isForeignSession()) {
        dropStoredSession();
        return null;
      }
      return data.token;
    }
  } catch {
    // handled by the caller: it falls back to logging out
  }
  return null;
}

// Parallel 401s share a single in-flight re-authentication.
function reauthenticate(): Promise<string | null> {
  if (!reauthPromise) {
    reauthPromise = requestFreshToken()
      .then((token) => {
        lastReauthFailureAt = token ? 0 : Date.now();
        return token;
      })
      .finally(() => {
        reauthPromise = null;
      });
  }
  return reauthPromise;
}

// A token to replay a request that was refused with a 401, or null when the session cannot be renewed. `sentWith` is the
// token that request carried. Shared by this client's interceptor below and by the fetch the staff screens' API layer
// (@rizqim/staff, services/staffApi.ts) is given, so there is one re-authentication, one cool-down, for both.
export function recoverToken(sentWith: string | null): Promise<string | null> {
  const currentToken = sessionToken();
  if (currentToken && currentToken !== sentWith) {
    // Another request already renewed the token while this one was in flight
    return Promise.resolve(currentToken);
  }
  if (Date.now() - lastReauthFailureAt < REAUTH_COOLDOWN_MS) {
    // A re-authentication just failed: don't hammer the (rate limited) login endpoint
    return Promise.resolve(null);
  }
  return reauthenticate();
}

// Wave M2: the change token of a single-scope read (GET /orders, GET /notifications of a customer). A poll the browser
// revalidated (304) arrives here as the stored 200 with its stored headers: the token it names is still current.
// The HTTP cache does the conditional request itself (the server sends ETag and `Cache-Control: private, no-cache`;
// e2e/conditional.spec.ts proves it on the wire), so this client keeps no ETag store of its own.
function noteRevision(response: AxiosResponse): void {
  if ((response.config.method ?? 'get').toLowerCase() !== 'get') return;
  const headers = response.headers as { get?: (name: string) => unknown } & Record<string, unknown>;
  const read = (name: string): string | null => {
    const value = typeof headers.get === 'function' ? headers.get(name) : headers[name];
    return typeof value === 'string' ? value : null;
  };
  noteResponseRevision(revisions, `GET ${api.getUri(response.config)}`, read);
}

// Response Interceptor for generic error handling
api.interceptors.response.use(
  (response) => {
    noteRevision(response);
    return response;
  },
  async (error: AxiosError) => {
    const config = error.config as AuthAwareRequestConfig | undefined;
    if (error.response?.status !== 401 || !config) {
      return Promise.reject(error);
    }

    // Never try to recover a failed login itself, nor a request that was already replayed once (no loops)
    if (config.url?.includes('/auth/telegram') || config._authRetried) {
      useAuthStore.getState().logout();
      return Promise.reject(error);
    }

    const token = await recoverToken(config._authToken ?? null);

    if (!token) {
      // Can't renew the session: fall back to the previous behaviour
      useAuthStore.getState().logout();
      return Promise.reject(error);
    }

    config._authRetried = true;
    config.headers.Authorization = `Bearer ${token}`;
    return api(config);
  }
);

export default api;
