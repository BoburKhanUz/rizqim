import { useEffect } from 'react';
import { isAxiosError } from 'axios';
import { sanitizeError } from '../lib/apiError';
import { isLaunchTooOld } from '../lib/launch';
import {
  clearForeignSession,
  currentTelegramId,
  isCurrentTelegramUser,
  isForeignSession,
  MOCK_TELEGRAM_ID_KEY,
  signInAgain,
  signOut,
  useSessionStatus,
} from '../lib/session';
import { getInitData } from '../lib/telegram';
import { signInWithTelegram } from '../lib/telegramSignIn';
import { useAuthStore } from '../store/authStore';
import api from '../services/api';
import type { ApiResponse, AuthPayload } from '../types';

// The token a request went out with (services/api.ts records it on the request config); `fallback` when not recorded
function tokenSentWith(response: unknown, fallback: string | null): string | null {
  const config = (response as { config?: { _authToken?: string | null } } | null)?.config;
  return config && '_authToken' in config ? config._authToken ?? null : fallback;
}

export const useTelegramAuth = () => {
  const { token, isAuthenticated, user: storedUser } = useAuthStore();
  // A session whose user is not the Telegram user who has the app open is never handed out, not even for the one render
  // before it is dropped (lib/session.ts)
  const foreign = isForeignSession({ token, isAuthenticated, user: storedUser });
  const isAuthenticating = useSessionStatus((status) => status.signingIn);
  const error = useSessionStatus((status) => status.error);
  // The launch data is too old to sign in with: the error screen asks to close and reopen the app (lib/launch.ts)
  const reopen = useSessionStatus((status) => status.reopen);
  // Bumped by signInAgain (lib/session.ts): sign in again with initData
  const attempt = useSessionStatus((status) => status.attempt);

  // The sign-in runs at launch, with the state the store was restored to, and again whenever a new one is asked for
  useEffect(() => {
    let stale = false; // a newer sign-in (or an unmount) took over: this one changes nothing any more
    const setStatus = (status: { signingIn?: boolean; error?: string | null; reopen?: boolean }) => {
      if (!stale) useSessionStatus.setState(status);
    };

    // A stored session of another user is dropped before anything is requested (the cart and addresses stay: they are
    // kept per Telegram user, lib/userStorage.ts), then the current user signs in below
    clearForeignSession();
    const launchState = useAuthStore.getState();
    const { setAuth } = launchState;

    const authenticateWithTelegram = async () => {
      // 1. Check if Telegram SDK is loaded
      if (!window.Telegram || !window.Telegram.WebApp) {
        setStatus({ error: 'Telegram SDK not loaded.', signingIn: false }); // otherwise the app stays on the loading spinner forever
        return;
      }

      // 2. Check if there is a start_param (deep link) which means we MUST process it
      const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param;

      // 3. Already authenticated (Persist check) - Instant 0-second UI launch!
      // Only bypass if there is NO start_param. What is left in the store belongs to the user who has the app open
      // (a foreign session was dropped above, lib/session.ts).
      const storedToken = launchState.isAuthenticated && launchState.token ? launchState.token : null;

      if (storedToken && !startParam) {
        setStatus({ signingIn: false, error: null });
        // Silently verify user session in the background and refresh local store settings
        api.get<ApiResponse<AuthPayload>>('/user').then((res) => {
          // Only for the token it was sent with: the 401 handler may have renewed the token meanwhile, or the app was
          // signed out; an answer about an earlier session is never written over the current one
          const sentWith = tokenSentWith(res, storedToken);
          if (!sentWith || useAuthStore.getState().token !== sentWith) return;
          if (res.data?.success && res.data?.data) {
            const freshData = res.data.data;
            if (!isCurrentTelegramUser(freshData.user)) {
              // The account is no longer linked to this Telegram user (its telegram_id changed or was removed): never
              // kept as the session; sign in again with initData
              signInAgain();
              return;
            }
            setAuth(sentWith, freshData.user, freshData.store, freshData.sms_verification_required);
            console.log('Background session refreshed with fresh store settings.');
          }
        }).catch((e) => {
          // Log a short summary only: the raw axios error embeds the Authorization header
          console.warn('Silent background session verification failed:', sanitizeError(e));
          if (isAxiosError(e) && e.response?.status === 401) {
            signOut();
          }
        });
        return; // Valid session
      }

      // 4. Authenticate with Backend
      let initData = getInitData();

      if (initData) {
        localStorage.removeItem(MOCK_TELEGRAM_ID_KEY);
        localStorage.removeItem('mock_telegram_username');
        localStorage.removeItem('mock_first_name');
      } else if (import.meta.env.DEV) {
        // Developer Mock Bypass: dev server only (stripped from production builds).
        // Only used when NO initData is present (not running inside Telegram). The mock account is the one the
        // storage of this page load was chosen for (lib/userStorage.ts creates it on the first load).
        console.log('Using mock credentials override...');
        const mockId = currentTelegramId() ?? String(Math.floor(10000000 + Math.random() * 90000000));
        const mockUsername = localStorage.getItem('mock_telegram_username');
        const mockFirstName = localStorage.getItem('mock_first_name') || 'Mijoz Test';
        const mockUser = {
          id: parseInt(mockId),
          first_name: mockFirstName,
          last_name: mockUsername ? '' : `#${mockId.substring(4)}`,
          username: mockUsername || `user_${mockId}`,
          language_code: 'uz'
        };
        initData = `mock_data=true&user=${encodeURIComponent(JSON.stringify(mockUser))}&auth_date=${Math.floor(Date.now() / 1000)}&hash=mock`;
      } else {
        // Production build outside Telegram: never fabricate a user or store a token.
        // Surface an error (rendered by AppContent's ErrorFallback) instead of an endless spinner.
        setStatus({ error: 'Iltimos, ilovani Telegram orqali oching', signingIn: false });
        return;
      }

      setStatus({ signingIn: true, error: null, reopen: false });
      // POST /api/v1/auth/telegram, shared with the 401 handler of services/api.ts: one sign-in at a time
      const outcome = await signInWithTelegram(initData, startParam || null);
      if (stale) return;

      if (outcome.ok) {
        setStatus({ signingIn: false, error: null, reopen: false });
        return;
      }
      // A deep link re-signs in although a valid session of this same person is stored: whatever the failure (offline, a
      // 5xx, or a 401 because the launch data is too old), that session stays and the app opens with it. Its token is
      // only dropped when the server refuses that token itself (the 401 handler of services/api.ts).
      const kept = storedToken !== null && useAuthStore.getState().token === storedToken;
      setStatus({ signingIn: false, error: outcome.error, reopen: !kept && (outcome.launchExpired || isLaunchTooOld()) });
    };

    authenticateWithTelegram();
    return () => {
      stale = true;
    };
  }, [attempt]);

  return {
    isAuthenticating,
    error,
    reopen,
    isAuthenticated: isAuthenticated && !foreign,
    token: foreign ? null : token,
    user: foreign ? null : storedUser,
  };
};
