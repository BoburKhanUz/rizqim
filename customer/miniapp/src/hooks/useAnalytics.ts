import { useEffect, useRef } from 'react';
import { setAnalyticsAuth, track } from '../lib/analytics';
import { isCustomerRole } from '../lib/roles';
import { getTelegramUser } from '../lib/telegram';
import { useDebouncedValue } from './useDebouncedValue';
import type { CartItem } from '../store/cartStore';
import type { User } from '../types';

// React glue of lib/analytics.ts: who may be tracked, and events that must fire once per real action, not once per
// render or per (StrictMode) effect run.

/** A plain customer: staff of any role, and any account tied to a store, are never tracked (docs/ANALYTICS_PLAN.md 4.1). */
export function isTrackedUser(user: Pick<User, 'role' | 'store_id'>): boolean {
  return isCustomerRole(user.role) && (user.store_id === null || user.store_id === undefined);
}

type AuthUser = Pick<User, 'id' | 'telegram_id' | 'role' | 'store_id'>;

/**
 * Hands the sign-in to analytics: events wait until it has finished, are only ever sent with their own account's token,
 * and staff send nothing. A stored account that is not this launch's Telegram user (a sign-in that failed left the
 * previous person's session on the device) is never tracked either: nothing is sent and what was held is dropped.
 */
export function useAnalyticsAuth(isAuthenticating: boolean, token: string | null, user: AuthUser | null): void {
  const id = user?.id;
  const telegramId = user?.telegram_id;
  const role = user?.role;
  const storeId = user?.store_id;
  useEffect(() => {
    // While signing in, the stored user may be someone else's (a new launch): nothing is decided until it is done
    if (isAuthenticating || role === undefined || id === undefined) {
      setAnalyticsAuth({ token: null, allowed: null, userId: null });
      return;
    }
    const launchUser = getTelegramUser();
    const sameTelegramUser = !launchUser || String(launchUser.id) === String(telegramId);
    const allowed = sameTelegramUser && isTrackedUser({ role, store_id: storeId ?? null });
    setAnalyticsAuth({ token, allowed, userId: id });
  }, [isAuthenticating, token, id, telegramId, role, storeId]);
}

/**
 * Runs `fire` when `key` becomes a value it was not already fired for (null: nothing to fire). A re-render, a refetch of
 * the same thing and StrictMode's second effect run do not fire again; a new key (another store) does.
 */
export function useTrackOnce(key: string | null, fire: () => void): void {
  const fired = useRef<string | null>(null);
  const latest = useRef(fire);
  useEffect(() => {
    latest.current = fire;
  });
  useEffect(() => {
    if (key === null || fired.current === key) return;
    fired.current = key;
    latest.current();
  }, [key]);
}

const SEARCH_SETTLE_MS = 1_000;

/**
 * `search` once the query stayed put for a second and its results are on the screen: only how many results there are and
 * how long the query is, never the text. The same settled query is not sent twice in a row.
 */
export function useTrackSearch(query: string, results: number, ready: boolean, storeId?: number): void {
  const settled = useDebouncedValue(query.trim(), SEARCH_SETTLE_MS);
  const current = query.trim();
  const last = useRef('');
  const counts = useRef(results);
  useEffect(() => {
    counts.current = results;
  });
  useEffect(() => {
    if (settled === '') {
      last.current = '';
      return;
    }
    if (!ready || settled !== current || settled === last.current) return;
    last.current = settled;
    const length = Math.min([...settled].length, 256);
    track('search', storeId ? { store_id: storeId, props: { results: counts.current, length } } : { props: { results: counts.current, length } });
  }, [settled, current, ready, storeId]);
}

/** A line went into the cart (a dish card's "+", the product sheet, "replace the cart"). */
export function trackAddToCart(item: Pick<CartItem, 'storeId' | 'productId' | 'quantity'>): void {
  track('add_to_cart', { store_id: Number(item.storeId), product_id: item.productId, props: { qty: item.quantity || 1 } });
}
