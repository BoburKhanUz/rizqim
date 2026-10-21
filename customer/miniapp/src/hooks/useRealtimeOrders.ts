import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getOrderChannelNames, getRealtimeConfig, openOrderChannels } from '../lib/realtime';
import { logApiError } from '../lib/apiError';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { boardKeys, staffKeys } from '@rizqim/staff';
import { applyFinishedOrderChange, ordersKeys, refetchOrders } from './useOrders';

// Events of one burst (a status change usually comes with an assignment) cause one refetch: the first one waits this
// long for the others. Not reset by each event, so a steady stream of them still refetches every REFETCH_DELAY_MS.
const REFETCH_DELAY_MS = 300;

// The caches the staff screens keep besides the order lists: the board's lists and order details (GET /staff/orders*), and
// what the stop-list and the batches show.
const STAFF_ORDER_KEYS = [boardKeys.all] as const;
const STAFF_MENU_KEYS = [staffKeys.allStopList, staffKeys.allBatches, ['menu-availability']] as const;

// Keeps the order queries fresh from the server's events while the user is signed in:
//  - connects after sign-in, to the private channel of the user's role (an owner or manager, who can also be handed an
//    order as its courier, to their own courier channel as well), and disconnects on logout or unmount;
//  - the staff channels also carry the kitchen's "line ready" events and the stop-list / batch changes
//    (menu.availability_changed): the former refetch the order screens (list and detail), the latter the stop-list and
//    batches screens;
//  - any order event refetches the lists of open orders that are on screen. Not the finished ones: an order that
//    finished drops out of its open list, which then refetches the history of its screen (useActiveOrders), so an
//    event costs a request or two. An order that is already in a loaded history and finishes differently (delivered ->
//    refunded) is patched in place in those lists, without any request (applyFinishedOrderChange);
//  - while the socket is up the screens poll slowly (useRealtimeStore.connected); when it drops they poll as before,
//    and after a reconnect the histories on screen are refetched as well, since events may have been missed.
// Without VITE_REVERB_APP_KEY, or when the connection cannot be made, nothing changes: the screens just poll.
export function useRealtimeOrders(): void {
  const queryClient = useQueryClient();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const role = useAuthStore((state) => state.user?.role);
  const userId = useAuthStore((state) => state.user?.id);
  const storeId = useAuthStore((state) => state.user?.store_id ?? null);
  const branchId = useAuthStore((state) => state.user?.branch_id ?? null);

  // One string, so the effect depends on the channels and not on a new array each render
  const channelKey =
    isAuthenticated && role && userId !== undefined
      ? getOrderChannelNames({ id: userId, role, store_id: storeId, branch_id: branchId }).join(',')
      : '';

  useEffect(() => {
    const config = getRealtimeConfig();
    if (!config || !channelKey) return;

    const { setConnected } = useRealtimeStore.getState();
    let disposed = false;
    let session: { close: () => void } | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let withHistory = false;
    let menuTimer: ReturnType<typeof setTimeout> | undefined;

    const refetch = (history: boolean) => {
      withHistory ||= history;
      if (timer !== undefined) return;
      timer = setTimeout(() => {
        timer = undefined;
        void refetchOrders(queryClient, ordersKeys.allActive);
        for (const key of STAFF_ORDER_KEYS) void refetchOrders(queryClient, key);
        if (withHistory) void refetchOrders(queryClient, ordersKeys.allHistory);
        withHistory = false;
      }, REFETCH_DELAY_MS);
    };

    const refetchMenu = () => {
      if (menuTimer !== undefined) return;
      menuTimer = setTimeout(() => {
        menuTimer = undefined;
        for (const key of STAFF_MENU_KEYS) void queryClient.invalidateQueries({ queryKey: key });
      }, REFETCH_DELAY_MS);
    };

    openOrderChannels(config, channelKey.split(','), () => useAuthStore.getState().token, {
      onEvent: (event) => {
        if (event.name === 'menu.availability_changed') {
          refetchMenu();
          return;
        }
        if (event.order && event.name === 'order.status_changed') applyFinishedOrderChange(queryClient, event.order);
        refetch(false);
      },
      onConnectionChange: (connected) => {
        if (!disposed) setConnected(connected);
      },
      onResync: () => {
        refetch(true);
        refetchMenu();
      },
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
      clearTimeout(menuTimer);
      session?.close();
      setConnected(false);
    };
  }, [channelKey, queryClient]);
}
