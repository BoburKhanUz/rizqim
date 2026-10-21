import { useActiveOrders, useOrderHistory } from '@rizqim/staff';
import { getPollInterval, pollingQueryOptions } from '../lib/polling';
import { useRealtimeStore } from '../store/realtimeStore';

export { NO_ORDERS, useOrderHistory } from '@rizqim/staff';

export type StaffOrdersScope = 'manager' | 'courier';

// The open orders for a staff screen (what GET /orders returns depends on the role). The request, the cache key (one entry
// per screen, so its mutations can refetch just that one) and "an order that left the list refetches the history" are
// @rizqim/staff's, shared with the partner web. The Mini App adds its polling: while the screen is visible, slowly while
// the real-time socket is up. Only the poll is slowed down by the socket: the data still counts as stale after the
// screen's own interval, so a screen opened again (or the app brought back) refetches it even when no event said so.
export function useStaffOrders(scope: StaffOrdersScope, intervalMs: number) {
  const realtimeConnected = useRealtimeStore((state) => state.connected);
  return useActiveOrders(scope, {
    ...pollingQueryOptions(getPollInterval(intervalMs, realtimeConnected)),
    staleTime: intervalMs,
  });
}

// The finished orders of a staff screen, a page at a time (see useOrderHistory in @rizqim/staff)
export const useStaffOrderHistory = useOrderHistory;
