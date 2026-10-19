import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getOrderChannelName, getRealtimeConfig, openOrderChannel, type OrderEvent } from '../lib/realtime';
import { logApiError } from '../lib/apiError';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { ORDERS_KEY, ordersKeys } from './useOrders';

// Events of one burst (a status change usually comes with an assignment) cause one refetch.
const REFETCH_DEBOUNCE_MS = 300;

// Keeps the order queries fresh from the server's events while the user is signed in:
//  - connects after sign-in, to the one private channel of the user's role, and disconnects on logout or unmount;
//  - any order event refetches the order queries that are on screen (a new order only the lists of open orders);
//  - while the socket is up the screens poll slowly (useRealtimeStore.connected); when it drops they poll as before,
//    and after a reconnect everything is refetched once, since events may have been missed.
// Without VITE_REVERB_APP_KEY, or when the connection cannot be made, nothing changes: the screens just poll.
export function useRealtimeOrders(): void {
  const queryClient = useQueryClient();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const role = useAuthStore((state) => state.user?.role);
  const userId = useAuthStore((state) => state.user?.id);
  const storeId = useAuthStore((state) => state.user?.store_id ?? null);
  const branchId = useAuthStore((state) => state.user?.branch_id ?? null);

  const channelName =
    isAuthenticated && role && userId !== undefined
      ? getOrderChannelName({ id: userId, role, store_id: storeId, branch_id: branchId })
      : null;

  useEffect(() => {
    const config = getRealtimeConfig();
    if (!config || !channelName) return;

    const { setConnected } = useRealtimeStore.getState();
    let disposed = false;
    let session: { close: () => void } | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let wantsEverything = false;

    const refetch = (everything: boolean) => {
      wantsEverything ||= everything;
      clearTimeout(timer);
      timer = setTimeout(() => {
        const key = wantsEverything ? ORDERS_KEY : ordersKeys.allActive;
        wantsEverything = false;
        void queryClient.invalidateQueries({ queryKey: key });
      }, REFETCH_DEBOUNCE_MS);
    };

    openOrderChannel(config, channelName, () => useAuthStore.getState().token, {
      onEvent: (event: OrderEvent) => refetch(event.name !== 'order.created'),
      onConnectionChange: (connected) => {
        if (!disposed) setConnected(connected);
      },
      onResync: () => refetch(true),
    })
      .then((opened) => {
        if (disposed) opened.close();
        else session = opened;
      })
      .catch((err: unknown) => {
        // Polling carries on at its normal pace
        if (!disposed) logApiError('Real-time updates unavailable:', err);
      });

    return () => {
      disposed = true;
      clearTimeout(timer);
      session?.close();
      setConnected(false);
    };
  }, [channelName, queryClient]);
}
