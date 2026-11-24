import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import { queryClient } from './queryClient';
import { revisions } from './revisions';

// Whose session the device holds. The Mini App's localStorage belongs to the device (the Telegram app), not to the
// Telegram account: two accounts on one phone share it. A stored session (token + user) may only be used, shown or
// sent while the Telegram user it was issued for has the app open.

// The dev server's stand-in for a Telegram account outside Telegram (see useTelegramAuth)
export const MOCK_TELEGRAM_ID_KEY = 'mock_telegram_id';

type SessionState = {
  token: string | null;
  isAuthenticated: boolean;
  user: { telegram_id?: string | number | null } | null;
};

function readMockTelegramId(): string | null {
  try {
    return localStorage.getItem(MOCK_TELEGRAM_ID_KEY);
  } catch {
    return null;
  }
}

// The Telegram account that has the app open: the user of this launch's initData (set by Telegram, read from
// window.Telegram, which telegram-web-app.js / @twa-dev/sdk fill in), else, on the dev server only, the mock account.
// null: nothing identifies the user (opened outside Telegram).
export function currentTelegramId(): string | null {
  const id = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
  if (id !== undefined && id !== null && String(id) !== '') return String(id);
  return import.meta.env.DEV ? readMockTelegramId() : null;
}

// The device holds a session, or a part of one
function holdsSession(state: SessionState): boolean {
  return Boolean(state.token || state.user || state.isAuthenticated);
}

// The stored session was not issued to the user who has the app open now, so it must not be used.
//  - A Telegram user (or the dev server's mock account) is known: the session is theirs only if its user has that
//    telegram_id. A session without a user cannot be tied to anyone and is foreign too.
//  - Nobody is known (no initData user): a production build treats the session as foreign (it cannot prove whose it
//    is, and it signs nobody in outside Telegram anyway); the dev server keeps it, as it always did, until a mock
//    account is chosen.
export function isForeignSession(state: SessionState = useAuthStore.getState()): boolean {
  if (!holdsSession(state)) return false;
  const current = currentTelegramId();
  if (current === null) return !import.meta.env.DEV;
  const owner = state.user?.telegram_id;
  return owner === undefined || owner === null || String(owner) !== current;
}

// Forgets the stored session and everything kept on the device for its user: the auth store, the cart (lines, the note
// to the store, the open checkout attempt with its body), the saved addresses, the in-memory query cache and the
// change tokens. The language and the order-sound switch are the device's settings and stay.
export function dropStoredSession(): void {
  useAuthStore.getState().logout();
  useCartStore.getState().clearCart();
  useAddressStore.setState({ addresses: [], selectedAddressId: null });
  queryClient.clear();
  revisions.clear();
}

// Drops the stored session when it belongs to another user. Returns whether it did.
export function dropForeignSession(): boolean {
  if (!isForeignSession()) return false;
  dropStoredSession();
  return true;
}

// The token a request may carry: the stored one only while it belongs to the user who has the app open. A foreign one
// is dropped on the spot (with its user's data) and nothing is sent.
export function sessionToken(): string | null {
  const { token } = useAuthStore.getState();
  if (!token) return null;
  if (dropForeignSession()) return null;
  return token;
}
