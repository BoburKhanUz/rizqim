import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createSilenceWatch, onPageReturn, type SilenceWatch } from '@rizqim/staff';
import { getOrderChannelNames, isStaffChannel, openOrderChannels, resolveRealtimeConfig } from '../lib/realtime';
import { loadRealtimeStatus } from '../lib/realtimeStatus';
import { revisions } from '../lib/revisions';
import { logApiError } from '../lib/apiError';
import { tg } from '../lib/telegram';
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
const NOTIFICATIONS_KEY = ['notifications'] as const;

// Telegram's own "the Mini App is in front again" (Bot API 8.0), besides the page's visibility
function onTelegramActivated(listener: () => void): () => void {
  const app = tg as unknown as { onEvent?: (event: string, cb: () => void) => void; offEvent?: (event: string, cb: () => void) => void } | undefined;
  if (typeof app?.onEvent !== 'function') return () => {};
  try {
    app.onEvent('activated', listener);
  } catch {
    return () => {};
  }
  return () => {
    try {
      app.offEvent?.('activated', listener);
    } catch {
      // an old client
    }
  };
}

// Keeps the order queries fresh from the server's events while the user is signed in:
//  - connects after sign-in, to the private channel of the user's role (an owner or manager, who can also be handed an
//    order as its courier, to their own courier channel as well), and disconnects on logout or unmount;
//  - the Reverb settings are the build's (VITE_REVERB_APP_KEY), else the server's (GET /realtime, wave M2);
//  - the staff channels also carry the kitchen's "line ready" events and the stop-list / batch changes
//    (menu.availability_changed): the former refetch the order screens (list and detail), the latter the stop-list and
//    batches screens;
//  - wave M2: every event carries the channel's change token (`rev`) and the reads name theirs (X-Revision,
//    lib/revisions.ts). The next token: the refetch below, as before; a token the screen already read: nothing is
//    fetched (the caches are only marked stale); a gap: everything is read again. A channel without single-scope reads
//    (the owner's store channel) refetches on every event, as before;
//  - any order event refetches the lists of open orders that are on screen. Not the finished ones: an order that
//    finished drops out of its open list, which then refetches the history of its screen (useActiveOrders), so an
//    event costs a request or two. An order that is already in a loaded history and finishes differently (delivered ->
//    refunded) is patched in place in those lists, without any request (applyFinishedOrderChange);
//  - "live" (useRealtimeStore.connected: the screens poll slowly) means the socket is up, the server says real-time
//    works (GET /realtime `live`) and, on a staff channel, a heartbeat or an event arrived within `silence_seconds`.
//    Otherwise the screens poll at their own pace. Going silent, a heartbeat ahead of what the screen read, a
//    reconnect, the page coming back (tab, phone screen, Telegram `activated`): everything is read again.
// Without any Reverb key, or when the connection cannot be made, nothing changes: the screens just poll.
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
    if (!channelKey) return;
    const channelNames = channelKey.split(',');
    const mainChannel = channelNames[0] ?? '';
    const customer = mainChannel.startsWith('user.');

    const { setConnected } = useRealtimeStore.getState();
    let disposed = false;
    let session: { close: () => void } | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let withHistory = false;
    let menuTimer: ReturnType<typeof setTimeout> | undefined;
    let silence: SilenceWatch | null = null;
    let socketUp = false;
    let serverLive = true;
    let silent = false;

    const publish = () => {
      if (!disposed) setConnected(socketUp && serverLive && !silent);
    };

    const refetch = (history: boolean) => {
      withHistory ||= history;
      if (timer !== undefined) return;
      timer = setTimeout(() => {
        timer = undefined;
        void refetchOrders(queryClient, ordersKeys.allActive);
        for (const key of STAFF_ORDER_KEYS) void refetchOrders(queryClient, key);
        if (customer) void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY });
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

    // Missed something (or cannot tell): every screen the channels feed reads again
    const rereadAll = () => {
      revisions.resetEvents();
      refetch(true);
      if (!customer) refetchMenu();
    };

    // The screens already read past this change: nothing to fetch (what is on screen was read after it); a list kept in
    // the cache but not on screen is marked stale, so it reads again when it is opened
    const markStale = () => {
      for (const key of [ordersKeys.allActive, ...STAFF_ORDER_KEYS]) {
        void queryClient.invalidateQueries({ queryKey: key, refetchType: 'none', predicate: (query) => !query.isActive() });
      }
    };

    const touch = (channel: string) => {
      if (channel === mainChannel) silence?.touch();
    };

    const refreshStatus = () => {
      void loadRealtimeStatus(true).then((status) => {
        if (disposed || !status) return;
        serverLive = status.live;
        publish();
      });
    };

    const start = async () => {
      const status = await loadRealtimeStatus();
      if (disposed) return;
      serverLive = status?.live ?? true;
      const config = resolveRealtimeConfig(status);
      if (!config) return;
      // Only a server that sends heartbeats (it answered GET /realtime) can be silent; the customer's channel has none
      if (status && isStaffChannel(mainChannel)) {
        silence = createSilenceWatch(status.silenceSeconds * 1000, (isSilent) => {
          silent = isSilent;
          publish();
          if (isSilent) rereadAll();
        });
      }

      const opened = await openOrderChannels(config, channelNames, () => useAuthStore.getState().token, {
        onEvent: (event) => {
          touch(event.channel);
          const verdict = revisions.onEvent(event.channel, event.rev);
          if (event.name === 'menu.availability_changed') {
            if (verdict === 'missed') rereadAll();
            else if (verdict !== 'same') refetchMenu();
            return;
          }
          if (event.order && event.name === 'order.status_changed') applyFinishedOrderChange(queryClient, event.order);
          if (verdict === 'same') markStale();
          else if (verdict === 'missed') rereadAll();
          else refetch(false);
        },
        onHeartbeat: (channel, rev) => {
          touch(channel);
          if (revisions.onHeartbeat(channel, rev) === 'reread') rereadAll();
        },
        onConnectionChange: (connected) => {
          socketUp = connected;
          if (connected) silence?.start();
          else silence?.stop();
          publish();
        },
        onResync: () => {
          rereadAll();
          refreshStatus();
        },
      });
      if (disposed) opened.close();
      else session = opened;
    };

    start().catch((err: unknown) => {
      // Polling carries on at its normal pace
      if (!disposed) logApiError('Real-time updates unavailable:', err);
    });

    // Back in front: whatever happened while the page slept is read again
    const stopReturn = onPageReturn(rereadAll);
    const stopActivated = onTelegramActivated(rereadAll);

    return () => {
      disposed = true;
      clearTimeout(timer);
      clearTimeout(menuTimer);
      silence?.stop();
      stopReturn();
      stopActivated();
      session?.close();
      revisions.clear();
      setConnected(false);
    };
  }, [channelKey, queryClient]);
}
