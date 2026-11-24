import { isAxiosError } from 'axios';
import { logApiError } from './apiError';
import { isLaunchRefused, noteLaunchRefused } from './launch';
import { isCurrentTelegramUser, LAUNCH_EXPIRED_ERROR, useSessionStatus } from './session';
import { authClient } from '../services/authClient';
import { useAuthStore } from '../store/authStore';
import type { ApiResponse, LoginPayload } from '../types';

// The one Telegram sign-in of the app (POST /auth/telegram with this launch's initData), shared by useTelegramAuth (the
// launch, signInAgain) and the 401 handler of services/api.ts (recoverToken):
//  - single flight: while one runs, every other caller gets the same attempt and its outcome, so the hook and the
//    interceptor never post twice in parallel, and one's failure never shows the error screen while the other's succeeds;
//  - it goes out on the plain client (services/authClient.ts): a refused sign-in never signs anyone out by itself, the
//    caller decides (a stored session of the same person can be kept);
//  - a 401 means the server refused this launch's initData (too old, or not Telegram's): it would refuse it again, so it
//    is never posted again in this page (lib/launch.ts) and the outcome says `launchExpired`;
//  - an answer for another Telegram account than the one that has the app open is never stored or used;
//  - success stores the session and clears whatever error an earlier attempt left on the sign-in status.

export type SignInOutcome =
  | { ok: true; token: string }
  | { ok: false; error: string; launchExpired: boolean };

const SIGN_IN_TIMEOUT_MS = 10_000;

let inFlight: Promise<SignInOutcome> | null = null;
let lastFailureAt = 0;

// A sign-in is on its way
export function isSignInRunning(): boolean {
  return inFlight !== null;
}

// When the last sign-in failed (epoch ms; 0: the last one worked, or none failed yet)
export function lastSignInFailureAt(): number {
  return lastFailureAt;
}

// Signs in with `initData` (this launch's, or the dev server's mock data), or joins the sign-in already running
export function signInWithTelegram(initData: string, startParam: string | null | undefined): Promise<SignInOutcome> {
  if (!inFlight) {
    inFlight = postSignIn(initData, startParam || null)
      .then((outcome) => {
        lastFailureAt = outcome.ok ? 0 : Date.now();
        return outcome;
      })
      .finally(() => {
        inFlight = null;
      });
  }
  return inFlight;
}

async function postSignIn(initData: string, startParam: string | null): Promise<SignInOutcome> {
  if (isLaunchRefused()) {
    // Refused once already: the same initData would be refused again (no request)
    return { ok: false, error: LAUNCH_EXPIRED_ERROR, launchExpired: true };
  }
  try {
    const response = await authClient.post<ApiResponse<LoginPayload>>('/auth/telegram', {
      initData,
      bot_username: import.meta.env.VITE_BOT_USERNAME || 'rizqimbot',
      start_param: startParam,
    }, { timeout: SIGN_IN_TIMEOUT_MS });

    const data = response.data?.data;
    if (!response.data?.success || !data?.token) {
      return { ok: false, error: 'Authentication failed: Invalid response format.', launchExpired: false };
    }
    if (!isCurrentTelegramUser(data.user)) {
      return { ok: false, error: 'Authentication failed: the account does not match this Telegram user.', launchExpired: false };
    }
    useAuthStore.getState().setAuth(data.token, data.user, data.store, data.sms_verification_required);
    useSessionStatus.setState({ error: null, reopen: false });
    console.log('Successfully Authenticated via Telegram!');
    return { ok: true, token: data.token };
  } catch (err) {
    // Status + message only: the raw axios error embeds the request body (initData) in err.config
    logApiError('Auth Error:', err);
    const axiosError = isAxiosError<{ message?: string }>(err) ? err : null;
    if (axiosError?.response?.status === 401) {
      noteLaunchRefused();
      return { ok: false, error: LAUNCH_EXPIRED_ERROR, launchExpired: true };
    }
    if (axiosError?.code === 'ECONNABORTED') {
      return { ok: false, error: 'Connection timeout. Please check your internet connection.', launchExpired: false };
    }
    return { ok: false, error: axiosError?.response?.data?.message || 'Failed to authenticate with backend.', launchExpired: false };
  }
}

// A new page load, as far as this module knows (tests)
export function resetSignIn(): void {
  inFlight = null;
  lastFailureAt = 0;
}
