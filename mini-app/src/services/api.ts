import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from '../store/authStore';
import { getInitData, tg } from '../lib/telegram';



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
    const token = useAuthStore.getState().token;
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
    const res = await authClient.post('/auth/telegram', {
      initData,
      bot_username: import.meta.env.VITE_BOT_USERNAME || 'rizqimbot',
      start_param: tg.initDataUnsafe?.start_param || null,
    }, { timeout: 10000 });

    const data = res.data?.data;
    if (res.data?.success && data?.token) {
      useAuthStore.getState().setAuth(data.token, data.user, data.store, data.sms_verification_required);
      return data.token as string;
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

// Response Interceptor for generic error handling
api.interceptors.response.use(
  (response) => response,
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

    const currentToken = useAuthStore.getState().token;
    let token: string | null;
    if (currentToken && currentToken !== config._authToken) {
      // Another request already renewed the token while this one was in flight
      token = currentToken;
    } else if (Date.now() - lastReauthFailureAt < REAUTH_COOLDOWN_MS) {
      // A re-authentication just failed: don't hammer the (rate limited) login endpoint
      token = null;
    } else {
      token = await reauthenticate();
    }

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
