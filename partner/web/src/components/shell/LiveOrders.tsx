'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { boardKeys, staffKeys, useStaffOrders, type StaffOrdersQuery } from '@rizqim/staff';
import { useRealtimeConfig } from '@/lib/apiConfig';
import { hasAny } from '@/lib/permissions';
import { boardChannelNames, createEcho, openBoardChannels, type BoardEvent, type EchoLike, type RealtimeConfig } from '@/lib/realtime';
import { authFetch } from '@/lib/session/client';
import { createChime, SOUND_STORAGE_KEY, type Chime } from '@/lib/sound';
import { readStored, writeStored } from '@/lib/storage';
import { useAccess } from '../AuthProvider';
import { useBranchScope } from './BranchScope';

// The live side of the panel, for everyone who sees orders (orders.view), on every page:
//  - the Reverb connection for the chosen branch scope; an event refetches the board's lists and that order;
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

export function newOrdersQuery(branchId: number | null): StaffOrdersQuery {
  return { scope: 'active', status: 'new', ...(branchId === null ? {} : { branch_id: branchId }) };
}

export function LiveOrdersProvider({ children, connect = defaultConnect, chime: givenChime }: { children: ReactNode; connect?: ConnectEcho; chime?: Chime }) {
  const access = useAccess();
  const config = useRealtimeConfig();
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

  useEffect(() => {
    if (!config || channelKey === '') return;
    let closed = false;
    let session: { close: () => void } | null = null;
    const current = pending.current;
    const setStatus = (status: RealtimeStatus) => {
      if (!closed) setConnection({ key: channelKey, status });
    };
    setStatus('connecting');
    connect(config)
      .then((echo) => {
        if (closed) {
          echo.disconnect();
          return;
        }
        session = openBoardChannels(echo, channelKey.split(' '), {
          onEvent: (event) => {
            if (event.uuid) current.uuids.add(event.uuid);
            clearTimeout(current.timer);
            current.timer = setTimeout(() => {
              const uuids = [...current.uuids];
              current.uuids.clear();
              refetchBoard(uuids);
            }, EVENT_DEBOUNCE_MS);
            listeners.current.forEach((listener) => listener(event));
          },
          onConnectionChange: (connected) => setStatus(connected ? 'live' : 'offline'),
          // Events may have been missed while the channel was down: everything again
          onResync: () => void queryClient.invalidateQueries({ queryKey: boardKeys.all }),
        });
      })
      .catch(() => setStatus('offline'));
    return () => {
      closed = true;
      clearTimeout(current.timer);
      session?.close();
    };
  }, [config, channelKey, connect, queryClient, refetchBoard]);

  const realtime: RealtimeStatus = !config || channelKey === '' ? 'off' : connection.key === channelKey ? connection.status : 'connecting';
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
  useEffect(() => {
    if (soundReady || !enabled) return;
    const unlock = () => {
      void chime.unlock().then((ready) => {
        if (ready) setSoundReady(true);
      });
    };
    window.addEventListener('pointerdown', unlock);
    window.addEventListener('keydown', unlock);
    return () => {
      window.removeEventListener('pointerdown', unlock);
      window.removeEventListener('keydown', unlock);
    };
  }, [chime, soundReady, enabled]);

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

  useEffect(() => {
    const base = document.title.replace(/^\(\d+\)\s/, '');
    document.title = newCount > 0 ? `(${newCount}) ${base}` : base;
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
