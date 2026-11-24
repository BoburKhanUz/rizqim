import axios, { type AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { getInitData, tg } from '../lib/telegram';
import { noteResponseRevision } from '@rizqim/staff';
import { revisions } from '../lib/revisions';
import { sessionToken, signOut } from '../lib/session';
import { isSignInRunning, lastSignInFailureAt, signInWithTelegram } from '../lib/telegramSignIn';
import { clientConfig } from './authClient';


const api = axios.create(clientConfig);

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

// Tokens expire (30 days) and can be pruned. On a 401 we log in again with the Telegram initData of this launch and
// replay the request once. The sign-in is the one of lib/telegramSignIn.ts, shared with useTelegramAuth: while one runs
// (the hook's, or another request's) a 401 joins it instead of posting a second one. The backend only accepts initData
// younger than 10 minutes, so this recovers the common case (opening the app with an old stored token), not a
// long-running session: once the server refused this launch's data it is never posted again, and the sign-out asks to
// close and reopen the app (lib/launch.ts).
const REAUTH_COOLDOWN_MS = 30_000;

// Resolves with the new token, or null when re-authentication isn't possible (no initData, rejected, offline).
async function reauthenticate(): Promise<string | null> {
  const initData = getInitData();
  if (!initData && !isSignInRunning()) return null;
  const outcome = await signInWithTelegram(initData, tg.initDataUnsafe?.start_param || null);
  return outcome.ok ? outcome.token : null;
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
  if (!isSignInRunning() && Date.now() - lastSignInFailureAt() < REAUTH_COOLDOWN_MS) {
    // A sign-in just failed: don't hammer the (rate limited) login endpoint
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
      signOut();
      return Promise.reject(error);
    }

    const token = await recoverToken(config._authToken ?? null);

    if (!token) {
      // Can't renew the session: the sign-in error screen (lib/session.ts signOut)
      signOut();
      return Promise.reject(error);
    }

    config._authRetried = true;
    config.headers.Authorization = `Bearer ${token}`;
    return api(config);
  }
);

export default api;
