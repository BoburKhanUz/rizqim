import { useEffect } from 'react';
import { isAxiosError } from 'axios';
import { logApiError, sanitizeError } from '../lib/apiError';
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
import { useAuthStore } from '../store/authStore';
import api from '../services/api';
import type { ApiResponse, AuthPayload, LoginPayload } from '../types';

export const useTelegramAuth = () => {
  const { token, isAuthenticated, user: storedUser } = useAuthStore();
  // A session whose user is not the Telegram user who has the app open is never handed out, not even for the one render
  // before it is dropped (lib/session.ts)
  const foreign = isForeignSession({ token, isAuthenticated, user: storedUser });
  const isAuthenticating = useSessionStatus((status) => status.signingIn);
  const error = useSessionStatus((status) => status.error);
  // Bumped by signInAgain (lib/session.ts): sign in again with initData
  const attempt = useSessionStatus((status) => status.attempt);

  // The sign-in runs at launch, with the state the store was restored to, and again whenever a new one is asked for
  useEffect(() => {
    let stale = false; // a newer sign-in (or an unmount) took over: this one changes nothing any more
    const setStatus = (status: { signingIn?: boolean; error?: string | null }) => {
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
      const isSessionValid = launchState.isAuthenticated && launchState.token;

      if (isSessionValid && !startParam) {
        setStatus({ signingIn: false, error: null });
        // Silently verify user session in the background and refresh local store settings
        api.get<ApiResponse<AuthPayload>>('/user').then((res) => {
          if (res.data?.success && res.data?.data) {
            const freshData = res.data.data;
            if (!isCurrentTelegramUser(freshData.user)) {
              // The account is no longer linked to this Telegram user (its telegram_id changed or was removed): never
              // kept as the session; sign in again with initData
              signInAgain();
              return;
            }
            // The 401 handler may have renewed the token meanwhile (or logged out): never write the captured, stale one back
            const currentToken = useAuthStore.getState().token;
            if (currentToken) {
              setAuth(currentToken, freshData.user, freshData.store, freshData.sms_verification_required);
            }
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

      // 3. Authenticate with Backend
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

      try {
        setStatus({ signingIn: true, error: null });

        // POST /api/v1/auth/telegram
        const botUsername = import.meta.env.VITE_BOT_USERNAME || 'rizqimbot';
        const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param || null;

        const response = await api.post<ApiResponse<LoginPayload>>('/auth/telegram', {
          initData: initData,
          bot_username: botUsername,
          start_param: startParam,
        }, { timeout: 10000 }); // 10 seconds timeout
        if (stale) return;

        if (response.data?.success && response.data?.data) {
          const authData = response.data.data;
          if (!isCurrentTelegramUser(authData.user)) {
            // The answer is for another Telegram account than the one that has the app open: never stored or used
            setStatus({ error: 'Authentication failed: the account does not match this Telegram user.' });
            return;
          }
          setAuth(authData.token, authData.user, authData.store, authData.sms_verification_required);
          console.log('Successfully Authenticated via Telegram!');
        } else {
          setStatus({ error: 'Authentication failed: Invalid response format.' });
        }
      } catch (err) {
        const axiosError = isAxiosError<{ message?: string }>(err) ? err : null;
        // Status + message only: the raw axios error embeds the request body (initData) in err.config
        logApiError('Auth Error:', err);
        if (axiosError?.code === 'ECONNABORTED') {
          setStatus({ error: 'Connection timeout. Please check your internet connection.' });
        } else {
          setStatus({ error: axiosError?.response?.data?.message || 'Failed to authenticate with backend.' });
        }
      } finally {
        setStatus({ signingIn: false });
      }
    };

    authenticateWithTelegram();
    return () => {
      stale = true;
    };
  }, [attempt]);

  return {
    isAuthenticating,
    error,
    isAuthenticated: isAuthenticated && !foreign,
    token: foreign ? null : token,
    user: foreign ? null : storedUser,
  };
};
