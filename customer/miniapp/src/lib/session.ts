import { create } from 'zustand';
import { useAuthStore } from '../store/authStore';
import { isLaunchTooOld } from './launch';
import { queryClient } from './queryClient';
import { revisions } from './revisions';
import { launchTelegramId, MOCK_TELEGRAM_ID_KEY } from './userStorage';

export { MOCK_TELEGRAM_ID_KEY };

// Whose session this is. The per-user stores are kept under the key of the Telegram user who has the app open
// (lib/userStorage.ts), so another account's data is never even loaded. On top of that, in memory: a session is only
// used, shown or sent while its user is that Telegram user, a sign-in answer for another account is never taken, and
// a session whose user stopped matching (the backend unlinked its telegram_id) is replaced by a new sign-in.

type SessionState = {
  token: string | null;
  isAuthenticated: boolean;
  user: { telegram_id?: string | number | null } | null;
};

// The Telegram account that has the app open: the user of this launch's initData, else, on the dev server only, the
// mock account the storage was chosen for. null: nothing identifies the user (a production build outside Telegram).
export function currentTelegramId(): string | null {
  const id = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
  if (id !== undefined && id !== null && String(id) !== '') return String(id);
  return import.meta.env.DEV ? launchTelegramId : null;
}

// The user (of a stored session, or of a sign-in answer) is the Telegram user who has the app open
export function isCurrentTelegramUser(user: SessionState['user'] | undefined): boolean {
  const current = currentTelegramId();
  if (current === null) return false;
  const owner = user?.telegram_id;
  return owner !== undefined && owner !== null && String(owner) === current;
}

// The device holds a session, or a part of one
function holdsSession(state: SessionState): boolean {
  return Boolean(state.token || state.user || state.isAuthenticated);
}

// The stored session must not be used: it holds something, and its user is not the Telegram user who has the app open
// (another telegram_id, none at all, or nobody is known to have the app open).
export function isForeignSession(state: SessionState = useAuthStore.getState()): boolean {
  return holdsSession(state) && !isCurrentTelegramUser(state.user);
}

// --- Signing in and out --------------------------------------------------------------------------------------------

export const SIGNED_OUT_ERROR = 'Session expired: please sign in again.';
// The launch's Telegram data is too old to sign in with (lib/launch.ts)
export const LAUNCH_EXPIRED_ERROR = 'The Telegram launch data has expired: close the app and open it again.';

interface SessionStatus {
  // A sign-in is running (the app shows its loader)
  signingIn: boolean;
  // Why the app is signed out (the app shows the sign-in error screen); null: no error
  error: string | null;
  // No sign-in of this page can succeed (the launch data is too old, lib/launch.ts): the error screen asks to close the
  // app and open it again instead of offering a reload, which keeps the same launch data
  reopen: boolean;
  // Bumped to ask useTelegramAuth for a new sign-in with initData
  attempt: number;
}

const INITIAL_STATUS: SessionStatus = { signingIn: true, error: null, reopen: false, attempt: 0 };

// The state of the sign-in, shared by useTelegramAuth (which runs it and shows it) and everything that ends a session
export const useSessionStatus = create<SessionStatus>()(() => ({ ...INITIAL_STATUS }));

// Back to "not signed in yet" (tests)
export function resetSessionStatus(): void {
  useSessionStatus.setState({ ...INITIAL_STATUS });
}

// Forgets the session in memory and on the device, and everything that was read with it: the in-memory query cache
// and the change tokens. The cart and the saved addresses stay: they are this Telegram user's (lib/userStorage.ts).
export function clearSession(): void {
  useAuthStore.getState().logout();
  queryClient.clear();
  revisions.clear();
}

// Every sign-out goes through here: the session and its caches are dropped, and the app shows the sign-in error screen
// (never screens that are silently signed out): with a reload retry, or, when this launch's data is too old to sign in
// again, with "close the app and open it again".
export function signOut(reason: string = SIGNED_OUT_ERROR): void {
  clearSession();
  useSessionStatus.setState({ signingIn: false, error: reason, reopen: isLaunchTooOld() });
}

// The stored session stopped matching the Telegram user (say, the backend unlinked its telegram_id and a refresh
// brought that in): it is dropped, with its caches, and the user signs in again with initData (useTelegramAuth); if
// that fails, the sign-in error screen. A sign-in that is already running is not started twice (and the 401 handler
// joins the same one, lib/telegramSignIn.ts).
export function signInAgain(): void {
  const status = useSessionStatus.getState();
  clearSession();
  if (status.signingIn) return;
  useSessionStatus.setState({ signingIn: true, error: null, reopen: false, attempt: status.attempt + 1 });
}

// Drops a stored session that does not belong to the Telegram user who has the app open (the cart and addresses stay)
export function clearForeignSession(): boolean {
  if (!isForeignSession()) return false;
  clearSession();
  return true;
}

// The token a request may carry: the stored one only while it belongs to the user who has the app open. Otherwise
// nothing is sent and a new sign-in is asked for.
export function sessionToken(): string | null {
  const state = useAuthStore.getState();
  if (!state.token) return null;
  if (isForeignSession(state)) {
    signInAgain();
    return null;
  }
  return state.token;
}

// Whatever writes the store (a background GET /user, the profile's refresh): a user that stops matching the Telegram
// user is never kept as the session.
useAuthStore.subscribe((state) => {
  if (state.token && isForeignSession(state)) signInAgain();
});
