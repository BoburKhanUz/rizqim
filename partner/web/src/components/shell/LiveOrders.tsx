'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { hashKey, useQueryClient } from '@tanstack/react-query';
import {
  applyBoardOrder,
  applyKitchenEvent,
  boardKeys,
  createRefreshThrottle,
  createSilenceWatch,
  fetchStaffOrder,
  getApiErrorStatus,
  removeBoardOrder,
  fetchRealtimeStatus,
  onPageReturn,
  reverbConnectionOf,
  revOf,
  spreadFirstReconnect,
  useStaffApi,
  useStaffOrders,
  type PusherLike,
  type RefreshPacing,
  type StaffOrdersList,
  type RealtimeStatus as ServerRealtimeStatus,
  type StaffOrdersQuery,
} from '@rizqim/staff';
import { ApiConfigContext, useRealtimeConfig } from '@/lib/apiConfig';
import { revisions } from '@/lib/revisions';
import { hasAny } from '@/lib/permissions';
import { boardChannelNames, createEcho, isBoardRelevant, KITCHEN_ITEM_EVENT, openBoardChannels, type BoardEvent, type EchoLike, type RealtimeConfig } from '@/lib/realtime';
import { authFetch } from '@/lib/session/client';
import { createChime, SOUND_STORAGE_KEY, type Chime } from '@/lib/sound';
import { readStored, writeStored } from '@/lib/storage';
import { useAccess } from '../AuthProvider';
import { useBranchScope } from './BranchScope';

// The live side of the panel, for everyone who sees orders (orders.view), on every page:
//  - the Reverb connection for the chosen branch scope. Fix round 2 (docs/scale/RESULTS_M2.md: at lunch every write of
//    a branch made the board read its two lists, 67 ms PHP and ~96 KB each, in a burst with every other screen):
//    the board hears every event (each one feeds the change tokens) but reads only for the ones that change what it
//    shows (lib/realtime.ts isBoardRelevant, with the table). Such an event reads THAT order once
//    (GET /staff/orders/{uuid}) and patches it into the lists, the badge, today's finished pages and the drawer
//    (@rizqim/staff applyBoardOrder); never a whole list on an event. Order reads are paced: window 15 s, spread 0-5 s
//    (an event after quiet reads within 5 s; a busy branch at most every ~20 s), each distinct uuid of a batch once,
//    more than 5 uuids one open-list read instead. A kitchen line event is applied from its payload, no read. A gap,
//    a heartbeat ahead or a failed order read: one open-list read, at most every 30 s, spread 0-5 s. A 404/403 on the
//    order read takes the order off the board. Reconnect, silence, the tab coming back and the person's own actions
//    (useBoardAction) read everything at once, unpaced;
//  - wave M2: GET /api/v1/realtime on start and after every reconnect: its Reverb settings when the panel's server has no
//    REVERB_APP_KEY, and whether the server's real-time path is live. Each event carries the channel's change token
//    (`rev`) and the board's single-branch reads name theirs (X-Revision, lib/revisions.ts): the next token refetches as
//    before, one the board already read fetches nothing, a gap reads everything again; a channel without such reads
//    (the store channel) refetches on every event. The staff channels hear `realtime.heartbeat` every 30 s: a heartbeat
//    ahead of the board reads everything again, and no heartbeat or event for `silence_seconds` counts as down
//    (fast polling) until one comes. The first reconnect after a drop is spread over 0-10 s; the tab coming back reads
//    everything again;
//  - the orders waiting for acceptance (polled; faster while real-time is off): their count in the sidebar badge and
//    the tab title, and a chime for each new one (mutable, remembered per browser; allowed after the first click).

export type RealtimeStatus = 'off' | 'connecting' | 'live' | 'offline';

// Polling of the board while real-time is off or down, and the slow safety net while it is up
export const POLL_MS = 15_000;
export const LIVE_POLL_MS = 60_000;
// Fix round 2: pacing of the reads events ask for (see above). The safety polls are not paced.
export const ORDER_READ_PACING: RefreshPacing = { windowMs: 15_000, spreadMs: 5_000 };
export const LIST_READ_PACING: RefreshPacing = { windowMs: 30_000, spreadMs: 5_000 };
// More distinct orders than this in one batch: one open-list read instead
export const MAX_ORDER_READS = 5;
// The pending uuids are bounded: beyond this the batch is an open-list read anyway
const MAX_PENDING_UUIDS = 50;

export interface BoardPacing {
  order: RefreshPacing;
  list: RefreshPacing;
  random?: () => number;
}
const DEFAULT_PACING: BoardPacing = { order: ORDER_READ_PACING, list: LIST_READ_PACING };
const DEFAULT_TIME_ZONE = 'Asia/Tashkent';

export interface LiveOrdersValue {
  realtime: RealtimeStatus;
  // How often the board's queries poll now (ms)
  pollInterval: number;
  // Orders of the branch scope waiting for acceptance
  newCount: number;
  muted: boolean;
  toggleMuted: () => void;
  // The browser has not allowed sound yet (no click or key press on the page so far)
  needsGesture: boolean;
  // Every board event, after the refetch is scheduled (the board can mark the card)
  subscribe: (listener: (event: BoardEvent) => void) => () => void;
}

const LiveOrdersContext = createContext<LiveOrdersValue | null>(null);

export type ConnectEcho = (config: RealtimeConfig) => Promise<EchoLike>;
const defaultConnect: ConnectEcho = (config) => createEcho(config, authFetch);

// GET /api/v1/realtime (wave M2); null when it cannot be read (an older backend): the old behaviour then applies
export type LoadRealtimeStatus = (apiBaseUrl: string) => Promise<ServerRealtimeStatus | null>;
const defaultLoadStatus: LoadRealtimeStatus = (apiBaseUrl) => fetchRealtimeStatus(apiBaseUrl);

// The panel's Reverb settings (REVERB_APP_KEY on its server), else the backend's (GET /realtime `reverb`)
export function effectiveRealtimeConfig(own: RealtimeConfig | null, status: ServerRealtimeStatus | null, apiBaseUrl: string): RealtimeConfig | null {
  if (own) return own;
  if (!status?.reverb || !apiBaseUrl) return null;
  const connection = reverbConnectionOf(status.reverb, apiBaseUrl);
  return connection ? { ...connection, authEndpoint: `${apiBaseUrl.replace(/\/+$/, '')}/broadcasting/auth` } : null;
}

export function newOrdersQuery(branchId: number | null): StaffOrdersQuery {
  return { scope: 'active', status: 'new', ...(branchId === null ? {} : { branch_id: branchId }) };
}

export function LiveOrdersProvider({
  children,
  connect = defaultConnect,
  loadStatus = defaultLoadStatus,
  chime: givenChime,
  pacing = DEFAULT_PACING,
}: {
  children: ReactNode;
  connect?: ConnectEcho;
  loadStatus?: LoadRealtimeStatus;
  chime?: Chime;
  // The tests' pacing (short windows, no spread)
  pacing?: BoardPacing;
}) {
  const access = useAccess();
  const ownConfig = useRealtimeConfig();
  const apiBaseUrl = useContext(ApiConfigContext)?.apiBaseUrl ?? '';
  const queryClient = useQueryClient();
  const api = useStaffApi();
  const pathname = usePathname();
  const { branchId, branches } = useBranchScope();
  const enabled = access !== null && hasAny(access.permissions, ['orders.view']);

  // --- Real-time -----------------------------------------------------------------------------------------------
  const channelKey = useMemo(() => {
    if (!access || !enabled) return '';
    const names = boardChannelNames(
      { role: access.role, storeId: access.storeId, branchId: access.branchId, branchScoped: access.branchScoped },
      branchId,
      branches.map((branch) => branch.id),
    );
    return names.join(' ');
  }, [access, enabled, branchId, branches]);

  const [connection, setConnection] = useState<{ key: string; status: RealtimeStatus }>({ key: '', status: 'off' });
  // GET /realtime: undefined while it is being read the first time, null when it could not be
  const [serverStatus, setServerStatus] = useState<ServerRealtimeStatus | null | undefined>(undefined);
  const [silent, setSilent] = useState(false);
  const refreshStatus = useCallback(() => {
    void loadStatus(apiBaseUrl).then((status) => setServerStatus((previous) => status ?? previous ?? null));
  }, [apiBaseUrl, loadStatus]);
  useEffect(() => {
    if (enabled) refreshStatus();
  }, [enabled, refreshStatus]);
  const statusKnown = serverStatus !== undefined || ownConfig !== null;
  // By value: a status read again after a reconnect must not open the socket anew when its settings did not change
  const configKey = JSON.stringify(effectiveRealtimeConfig(ownConfig, serverStatus ?? null, apiBaseUrl));
  const config = useMemo(() => JSON.parse(configKey) as RealtimeConfig | null, [configKey]);
  // Read when the watch arms: the status may arrive after the socket opened (no heartbeats known: no silence)
  const silenceMs = useRef(0);
  const silenceLimit = serverStatus ? serverStatus.silenceSeconds * 1000 : 0;
  useEffect(() => {
    silenceMs.current = silenceLimit;
  }, [silenceLimit]);
  const serverLive = serverStatus?.live ?? true;
  const listeners = useRef(new Set<(event: BoardEvent) => void>());
  const timeZone = useRef(DEFAULT_TIME_ZONE);
  const storeTimeZone = access?.timezone ?? DEFAULT_TIME_ZONE;
  useEffect(() => {
    timeZone.current = storeTimeZone;
  }, [storeTimeZone]);
  // While the complete open list of the branch scope is on screen (board, dashboard), the badge is derived from it
  const badgeDerived = useRef(false);
  const badgeHash = hashKey(boardKeys.list(newOrdersQuery(branchId)));

  // One open-list read (a gap, a heartbeat ahead, a failed order read, a batch of many orders): the lists on screen
  // read again, the badge too unless it is derived from the open list; what is not on screen is marked stale
  const readOpenLists = useCallback(() => {
    void queryClient.invalidateQueries({
      queryKey: boardKeys.lists,
      predicate: (query) => !(badgeDerived.current && query.queryHash === badgeHash),
    });
    void queryClient.invalidateQueries({ queryKey: [...boardKeys.all, 'pages'], refetchType: 'none' });
  }, [queryClient, badgeHash]);

  // Missed something (or cannot tell): everything the board shows is read again
  const rereadAll = useCallback(() => {
    revisions.resetEvents();
    void queryClient.invalidateQueries({ queryKey: boardKeys.all });
  }, [queryClient]);

  // The board already read past this change: nothing to fetch now. What is on screen was read after it; a list or an
  // order kept in the cache but not on screen is marked stale, so it reads again when it is opened.
  const markStale = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: boardKeys.all, refetchType: 'none', predicate: (query) => !query.isActive() });
  }, [queryClient]);

  // Reads one order and patches it in; false when the read failed for another reason than "not yours any more"
  const readOrder = useCallback(
    async (uuid: string): Promise<boolean> => {
      try {
        const order = await queryClient.fetchQuery({ queryKey: boardKeys.detail(uuid), queryFn: () => fetchStaffOrder(api, uuid), staleTime: 0, retry: false });
        applyBoardOrder(queryClient, order, { timeZone: timeZone.current });
        return true;
      } catch (error) {
        const status = getApiErrorStatus(error);
        if (status === 404 || status === 403) {
          removeBoardOrder(queryClient, uuid);
          return true;
        }
        return false;
      }
    },
    [queryClient, api],
  );

  useEffect(() => {
    if (!config || channelKey === '') return;
    let closed = false;
    let session: { close: () => void } | null = null;
    let stopSpread = () => {};
    const pendingUuids = new Set<string>();
    const listThrottle = createRefreshThrottle(readOpenLists, { ...pacing.list, random: pacing.random });
    const orderThrottle = createRefreshThrottle(
      () => {
        const uuids = [...pendingUuids];
        pendingUuids.clear();
        if (uuids.length === 0) return;
        if (uuids.length > MAX_ORDER_READS) {
          readOpenLists();
          return;
        }
        void Promise.all(uuids.map(readOrder)).then((results) => {
          if (!closed && results.includes(false)) listThrottle.request();
        });
      },
      { ...pacing.order, random: pacing.random },
    );
    // Everything is read now: nothing paced is still owed
    const rereadEverything = () => {
      pendingUuids.clear();
      orderThrottle.cancel();
      listThrottle.cancel();
      rereadAll();
    };
    const setStatus = (status: RealtimeStatus) => {
      if (!closed) setConnection({ key: channelKey, status });
    };
    const silence = createSilenceWatch(() => silenceMs.current, (isSilent) => {
      if (closed) return;
      setSilent(isSilent);
      if (isSilent) rereadEverything();
    });
    setStatus('connecting');
    connect(config)
      .then((echo) => {
        if (closed) {
          echo.disconnect();
          return;
        }
        stopSpread = spreadFirstReconnect(echo.connector.pusher as PusherLike | undefined);
        session = openBoardChannels(echo, channelKey.split(' '), {
          onEvent: (event) => {
            silence.touch();
            // Every event feeds the tokens, also the ones the board does not read for
            const verdict = revisions.onEvent(event.channel, revOf(event.payload, event.channel));
            const relevant = isBoardRelevant(event.name, event.payload);
            if (verdict === 'missed') listThrottle.request();
            else if (event.name === KITCHEN_ITEM_EVENT) applyKitchenEvent(queryClient, event.payload);
            else if (relevant && verdict === 'same') markStale();
            else if (relevant) {
              if (event.uuid === null) listThrottle.request();
              else {
                if (pendingUuids.size < MAX_PENDING_UUIDS) pendingUuids.add(event.uuid);
                orderThrottle.request();
              }
            }
            if (relevant || event.name === KITCHEN_ITEM_EVENT) listeners.current.forEach((listener) => listener(event));
          },
          onHeartbeat: (channel, payload) => {
            silence.touch();
            if (revisions.onHeartbeat(channel, revOf(payload, channel)) === 'reread') listThrottle.request();
          },
          onConnectionChange: (connected) => {
            if (connected) silence.start();
            else silence.stop();
            setStatus(connected ? 'live' : 'offline');
          },
          // Events may have been missed while the channel was down: everything again, and the server's state anew
          onResync: () => {
            rereadEverything();
            refreshStatus();
          },
        });
      })
      .catch(() => setStatus('offline'));
    return () => {
      closed = true;
      orderThrottle.cancel();
      listThrottle.cancel();
      pendingUuids.clear();
      silence.stop();
      stopSpread();
      session?.close();
      setSilent(false);
    };
  }, [config, channelKey, connect, queryClient, rereadAll, markStale, refreshStatus, readOpenLists, readOrder, pacing]);

  // The tab coming back (another tab, a laptop waking up): whatever happened meanwhile is read again
  useEffect(() => {
    if (!enabled) return;
    return onPageReturn(rereadAll);
  }, [enabled, rereadAll]);

  const socketStatus: RealtimeStatus =
    channelKey === '' ? 'off' : !config ? (statusKnown ? 'off' : 'connecting') : connection.key === channelKey ? connection.status : 'connecting';
  // Live only while the socket is up, the server says real-time works and the channels are not silent
  const realtime: RealtimeStatus = socketStatus === 'live' && (!serverLive || silent) ? 'offline' : socketStatus;
  const pollInterval = realtime === 'live' ? LIVE_POLL_MS : POLL_MS;

  // --- New orders: count, badge, title, chime -----------------------------------------------------------------
  const query = newOrdersQuery(branchId);
  // Fix round 2: while the complete open list of the same branch scope is on screen (the board or the dashboard: it
  // polls and is patched by the events itself), the badge is derived from it and has no poll of its own: one list per
  // safety poll instead of two
  const [openOnScreen, setOpenOnScreen] = useState(false);
  useEffect(() => {
    const cache = queryClient.getQueryCache();
    const openKey = boardKeys.list({ scope: 'active', ...(branchId === null ? {} : { branch_id: branchId }) });
    const openHash = hashKey(openKey);
    const badgeKey = boardKeys.list(newOrdersQuery(branchId));
    const sync = () => {
      const open = cache.find<StaffOrdersList>({ queryKey: openKey, exact: true });
      const data = open?.state.data;
      const complete = open !== undefined && open.getObserversCount() > 0 && data !== undefined && !data.truncated;
      badgeDerived.current = complete;
      setOpenOnScreen(complete);
      if (!complete) return;
      const derived = data.orders.filter((order) => order.status === 'new');
      const held = queryClient.getQueryData<StaffOrdersList>(badgeKey);
      const same = held !== undefined && !held.truncated && held.orders.length === derived.length && held.orders.every((order, i) => order === derived[i]);
      if (!same) queryClient.setQueryData<StaffOrdersList>(badgeKey, { orders: derived, truncated: false });
    };
    sync();
    const stop = cache.subscribe((event) => {
      if (event.query.queryHash === openHash) sync();
    });
    return () => {
      stop();
      badgeDerived.current = false;
    };
  }, [queryClient, branchId]);
  const newOrders = useStaffOrders(query, {
    enabled,
    refetchInterval: openOnScreen ? false : pollInterval,
    refetchIntervalInBackground: true,
    staleTime: 5_000,
  });
  const newCount = enabled ? (newOrders.data?.orders.length ?? 0) : 0;

  const [muted, setMuted] = useState(() => readStored(SOUND_STORAGE_KEY) === 'off');
  const toggleMuted = useCallback(() => {
    setMuted((previous) => {
      writeStored(SOUND_STORAGE_KEY, previous ? 'on' : 'off');
      return !previous;
    });
  }, []);

  const [chime] = useState(() => givenChime ?? createChime());
  const [soundReady, setSoundReady] = useState(() => chime.ready);
  // The AudioContext goes with the shell (signing out, leaving the panel): browsers allow only a few at a time
  useEffect(() => () => chime.close(), [chime]);
  useEffect(() => {
    if (!enabled) return;
    // Every gesture checks: the context may have been closed and must be opened again (a remount). A click, not a
    // pointerdown: unlocking hides the "sound needs a click" hint, and on a pointerdown the layout would shift under the
    // finger before the click lands, so the first tap on a phone missed its button
    const unlock = () => {
      if (chime.ready) return;
      void chime.unlock().then((ready) => {
        if (ready) setSoundReady(true);
      });
    };
    window.addEventListener('click', unlock);
    window.addEventListener('keydown', unlock);
    return () => {
      window.removeEventListener('click', unlock);
      window.removeEventListener('keydown', unlock);
    };
  }, [chime, enabled]);

  // The uuids already announced, per branch scope (switching branches announces nothing)
  const seen = useRef<{ scope: string; uuids: Set<string> } | null>(null);
  const scopeKey = String(branchId);
  const orders = newOrders.data?.orders;
  useEffect(() => {
    if (!orders) return;
    const uuids = new Set(orders.map((order) => order.uuid));
    const previous = seen.current;
    seen.current = { scope: scopeKey, uuids };
    if (!previous || previous.scope !== scopeKey) return;
    const arrived = orders.some((order) => !previous.uuids.has(order.uuid));
    if (arrived && !muted) chime.play();
  }, [orders, scopeKey, muted, chime]);

  // "(n) " before the tab title. Next sets the title of each page itself (metadata), possibly after this effect ran: the
  // observer puts the count back whenever the title changes. Leaving the shell takes the count off.
  useEffect(() => {
    const strip = (title: string) => title.replace(/^\(\d+\)\s/, '');
    const apply = () => {
      const wanted = newCount > 0 ? `(${newCount}) ${strip(document.title)}` : strip(document.title);
      if (document.title !== wanted) document.title = wanted;
    };
    apply();
    const observer = typeof MutationObserver === 'undefined' ? null : new MutationObserver(apply);
    observer?.observe(document.head, { subtree: true, childList: true, characterData: true });
    return () => {
      observer?.disconnect();
      document.title = strip(document.title);
    };
  }, [newCount, pathname]);

  const subscribe = useCallback((listener: (event: BoardEvent) => void) => {
    listeners.current.add(listener);
    return () => {
      listeners.current.delete(listener);
    };
  }, []);

  const value = useMemo<LiveOrdersValue>(
    () => ({ realtime, pollInterval, newCount, muted, toggleMuted, needsGesture: enabled && !muted && !soundReady, subscribe }),
    [realtime, pollInterval, newCount, muted, toggleMuted, enabled, soundReady, subscribe],
  );
  return <LiveOrdersContext.Provider value={value}>{children}</LiveOrdersContext.Provider>;
}

const OUTSIDE: LiveOrdersValue = {
  realtime: 'off',
  pollInterval: POLL_MS,
  newCount: 0,
  muted: true,
  toggleMuted: () => {},
  needsGesture: false,
  subscribe: () => () => {},
};

// The live state of the panel. Outside the shell (a test of one page): off, polling.
export function useLiveOrders(): LiveOrdersValue {
  return useContext(LiveOrdersContext) ?? OUTSIDE;
}
