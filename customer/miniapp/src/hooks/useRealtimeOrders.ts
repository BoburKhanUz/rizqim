import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createRefreshThrottle, createSilenceWatch, onPageReturn, type RefreshPacing, type SilenceWatch } from '@rizqim/staff';
import { getOrderChannelNames, isStaffChannel, openOrderChannels, resolveRealtimeConfig } from '../lib/realtime';
import { loadRealtimeStatus } from '../lib/realtimeStatus';
import { revisions } from '../lib/revisions';
import { logApiError } from '../lib/apiError';
import { isForeignSession, sessionToken } from '../lib/session';
import { tg } from '../lib/telegram';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { boardKeys, staffKeys } from '@rizqim/staff';
import { applyFinishedOrderChange, ordersKeys, refetchOrders } from './useOrders';

// Wave M2 fix round 2 (docs/scale/RESULTS_M2.md: with real-time on, every write of a busy branch made every screen of
// it re-read within ~300 ms): the reads events ask for are paced, a leading + trailing throttle (@rizqim/staff
// createRefreshThrottle). An event after quiet reads after a random 0..spread; events during the window after a read
// collapse into ONE read at its end (plus a new spread). Staff phones (store, branch, courier channels; the manager
// accepts new orders here): window 3 s, spread 0-1 s, so an order change shows within ~4 s; a customer (user.N): one refetch of the orders and the notifications per burst, window 5 s, spread
// 0-1 s. A gap or a heartbeat ahead is a full re-read through the same throttle; a reconnect, the silence watch and
// the page coming back read everything at once. The safety polls of the screens are their own and are never paced.
export const STAFF_PACING: RefreshPacing = { windowMs: 3_000, spreadMs: 1_000 };
export const CUSTOMER_PACING: RefreshPacing = { windowMs: 5_000, spreadMs: 1_000 };
// The spread's random source (the tests set a fixed one)
export const realtimePacing: { random: () => number } = { random: Math.random };

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
//    Otherwise the screens poll at their own pace. Going silent, a reconnect, the page coming back (tab, phone screen,
//    Telegram `activated`): everything is read again at once; a gap or a heartbeat ahead of what the screen read:
//    everything again, paced (fix round 2, above). Every other event of the channels only feeds the token.
// Without any Reverb key, or when the connection cannot be made, nothing changes: the screens just poll.
export function useRealtimeOrders(): void {
  const queryClient = useQueryClient();
  // A session stored for another Telegram user never opens that user's channels (lib/session.ts)
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated && !isForeignSession(state));
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
    // What the next paced read covers besides the open orders
    let withHistory = false;
    let withMenu = false;
    let silence: SilenceWatch | null = null;
    let socketUp = false;
    let serverLive = true;
    let silent = false;
    const pacing = customer ? CUSTOMER_PACING : STAFF_PACING;

    const publish = () => {
      if (!disposed) setConnected(socketUp && serverLive && !silent);
    };

    const runOrders = () => {
      void refetchOrders(queryClient, ordersKeys.allActive);
      for (const key of STAFF_ORDER_KEYS) void refetchOrders(queryClient, key);
      if (customer) void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY });
      if (withHistory) void refetchOrders(queryClient, ordersKeys.allHistory);
      withHistory = false;
      if (withMenu) runMenu();
    };
    const runMenu = () => {
      withMenu = false;
      for (const key of STAFF_MENU_KEYS) void queryClient.invalidateQueries({ queryKey: key });
    };
    const orderThrottle = createRefreshThrottle(runOrders, { ...pacing, random: () => realtimePacing.random() });
    const menuThrottle = createRefreshThrottle(runMenu, { ...pacing, random: () => realtimePacing.random() });

    const refetch = (history: boolean) => {
      withHistory ||= history;
      orderThrottle.request();
    };

    const refetchMenu = () => {
      menuThrottle.request();
    };

    // Missed something (a gap, a heartbeat ahead): every screen the channels feed reads again, paced
    const rereadPaced = () => {
      revisions.resetEvents();
      withHistory = true;
      if (!customer) withMenu = true;
      orderThrottle.request();
    };

    // A reconnect, the silence watch, the page coming back: everything at once, never paced
    const rereadAll = () => {
      revisions.resetEvents();
      withHistory = true;
      if (!customer) {
        withMenu = true;
        menuThrottle.cancel();
      }
      orderThrottle.flush();
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

      const opened = await openOrderChannels(config, channelNames, sessionToken, {
        onEvent: (event) => {
          touch(event.channel);
          const verdict = revisions.onEvent(event.channel, event.rev);
          if (event.name === 'menu.availability_changed') {
            if (verdict === 'missed') rereadPaced();
            else if (verdict !== 'same') refetchMenu();
            return;
          }
          if (event.order && event.name === 'order.status_changed') applyFinishedOrderChange(queryClient, event.order);
          if (verdict === 'same') markStale();
          else if (verdict === 'missed') rereadPaced();
          else refetch(false);
        },
        // Fix round 2: an event the screens do not read for only feeds the token (a gap it shows is still a gap)
        onOtherEvent: (channel, _name, rev) => {
          touch(channel);
          if (revisions.onEvent(channel, rev) === 'missed') rereadPaced();
        },
        onHeartbeat: (channel, rev) => {
          touch(channel);
          if (revisions.onHeartbeat(channel, rev) === 'reread') rereadPaced();
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
      orderThrottle.cancel();
      menuThrottle.cancel();
      silence?.stop();
      stopReturn();
      stopActivated();
      session?.close();
      revisions.clear();
      setConnected(false);
    };
  }, [channelKey, queryClient]);
}
