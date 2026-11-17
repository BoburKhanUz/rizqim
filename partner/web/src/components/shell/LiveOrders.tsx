'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import {
  boardKeys,
  createSilenceWatch,
  fetchRealtimeStatus,
  onPageReturn,
  reverbConnectionOf,
  revOf,
  spreadFirstReconnect,
  staffKeys,
  useStaffOrders,
  type PusherLike,
  type RealtimeStatus as ServerRealtimeStatus,
  type StaffOrdersQuery,
} from '@rizqim/staff';
import { ApiConfigContext, useRealtimeConfig } from '@/lib/apiConfig';
import { revisions } from '@/lib/revisions';
import { hasAny } from '@/lib/permissions';
import { boardChannelNames, createEcho, openBoardChannels, type BoardEvent, type EchoLike, type RealtimeConfig } from '@/lib/realtime';
import { authFetch } from '@/lib/session/client';
import { createChime, SOUND_STORAGE_KEY, type Chime } from '@/lib/sound';
import { readStored, writeStored } from '@/lib/storage';
import { useAccess } from '../AuthProvider';
import { useBranchScope } from './BranchScope';

// The live side of the panel, for everyone who sees orders (orders.view), on every page:
//  - the Reverb connection for the chosen branch scope; an event refetches the board's lists and that order;
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
// Events come in bursts (status, then kitchen lines): one refetch for a burst
const EVENT_DEBOUNCE_MS = 250;

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
}: {
  children: ReactNode;
  connect?: ConnectEcho;
  loadStatus?: LoadRealtimeStatus;
  chime?: Chime;
}) {
  const access = useAccess();
  const ownConfig = useRealtimeConfig();
  const apiBaseUrl = useContext(ApiConfigContext)?.apiBaseUrl ?? '';
  const queryClient = useQueryClient();
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
  const pending = useRef<{ timer: ReturnType<typeof setTimeout> | undefined; uuids: Set<string> }>({ timer: undefined, uuids: new Set() });

  const refetchBoard = useCallback(
    (uuids: Iterable<string>) => {
      void queryClient.invalidateQueries({ queryKey: boardKeys.lists });
      void queryClient.invalidateQueries({ queryKey: [...boardKeys.all, 'pages'] });
      void queryClient.invalidateQueries({ queryKey: staffKeys.orders });
      for (const uuid of uuids) void queryClient.invalidateQueries({ queryKey: boardKeys.detail(uuid) });
    },
    [queryClient],
  );

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

  useEffect(() => {
    if (!config || channelKey === '') return;
    let closed = false;
    let session: { close: () => void } | null = null;
    let stopSpread = () => {};
    const current = pending.current;
    const setStatus = (status: RealtimeStatus) => {
      if (!closed) setConnection({ key: channelKey, status });
    };
    const silence = createSilenceWatch(() => silenceMs.current, (isSilent) => {
      if (closed) return;
      setSilent(isSilent);
      if (isSilent) rereadAll();
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
            const verdict = revisions.onEvent(event.channel, revOf(event.payload, event.channel));
            if (verdict === 'same') markStale();
            else if (verdict === 'missed') rereadAll();
            else {
              if (event.uuid) current.uuids.add(event.uuid);
              clearTimeout(current.timer);
              current.timer = setTimeout(() => {
                const uuids = [...current.uuids];
                current.uuids.clear();
                refetchBoard(uuids);
              }, EVENT_DEBOUNCE_MS);
            }
            listeners.current.forEach((listener) => listener(event));
          },
          onHeartbeat: (channel, payload) => {
            silence.touch();
            if (revisions.onHeartbeat(channel, revOf(payload, channel)) === 'reread') rereadAll();
          },
          onConnectionChange: (connected) => {
            if (connected) silence.start();
            else silence.stop();
            setStatus(connected ? 'live' : 'offline');
          },
          // Events may have been missed while the channel was down: everything again, and the server's state anew
          onResync: () => {
            rereadAll();
            refreshStatus();
          },
        });
      })
      .catch(() => setStatus('offline'));
    return () => {
      closed = true;
      clearTimeout(current.timer);
      silence.stop();
      stopSpread();
      session?.close();
      setSilent(false);
    };
  }, [config, channelKey, connect, queryClient, refetchBoard, rereadAll, markStale, refreshStatus]);

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
  const newOrders = useStaffOrders(query, { enabled, refetchInterval: pollInterval, refetchIntervalInBackground: true, staleTime: 5_000 });
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
