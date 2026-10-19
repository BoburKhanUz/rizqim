import { useCallback, useEffect, useMemo } from 'react';
import { useInfiniteQuery, useQuery, useQueryClient, type QueryClient, type QueryKey } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { POLL_INTERVAL_MS, getPollInterval, pollingQueryOptions } from '../lib/polling';
import type { ActiveOrdersMeta, ApiResponse, HistoryOrdersMeta, Order } from '../types';

export type { Order, OrderItem, PaymentStatus } from '../types';

// GET /orders is read in two scopes (the unscoped, unbounded list is not used any more):
//  - active: the orders still in progress, newest first, at most 200 (`truncated` says there were more);
//  - history: the finished ones, a page at a time (keyset pagination: `next_cursor` is opaque).
// Both are cached per screen ("scope" below), under one prefix so that a mutation can refetch them all.

export const ORDERS_KEY = ['orders'] as const;

export type OrdersScope = 'customer' | 'manager' | 'courier' | 'admin';

export const ordersKeys = {
  allActive: ['orders', 'active'] as const,
  allHistory: ['orders', 'history'] as const,
  active: (scope: OrdersScope) => ['orders', 'active', scope] as const,
  history: (scope: OrdersScope) => ['orders', 'history', scope] as const,
  // The finished orders of a whole period (analytics, reports): up to DEFAULT_MAX_AUTO_PAGES pages, so they are kept
  // apart from the history lists and refreshed on their own slower schedule, never on events.
  period: (scope: OrdersScope) => ['orders', 'period', scope] as const,
};

export const HISTORY_PAGE_SIZE = 20;
const HISTORY_MAX_PAGE_SIZE = 50; // what the backend caps per_page at

export const NO_ORDERS: Order[] = [];

export interface ActiveOrders {
  orders: Order[];
  truncated: boolean;
}

export interface OrdersPage {
  orders: Order[];
  nextCursor: string | null;
}

export async function fetchActiveOrders(): Promise<ActiveOrders> {
  const { data } = await api.get<ApiResponse<Order[], ActiveOrdersMeta>>('/orders', { params: { scope: 'active' } });
  return { orders: data.data ?? [], truncated: data.meta?.truncated === true };
}

export async function fetchOrdersPage(cursor: string | null, pageSize: number = HISTORY_PAGE_SIZE): Promise<OrdersPage> {
  const params: Record<string, string | number> = { scope: 'history', per_page: pageSize };
  if (cursor) params.cursor = cursor;
  const { data } = await api.get<ApiResponse<Order[], HistoryOrdersMeta>>('/orders', { params });
  return { orders: data.data ?? [], nextCursor: data.meta?.next_cursor ?? null };
}

// Refetches the order queries under `queryKey` that are on screen, without cancelling a refetch already under way:
// in a busy store, cancelling it on every change would restart it again and again (asking a paged list for all its
// pages each time), and it might never finish. Such a refetch may have read the orders from before the change, so the
// queries are refetched once more after it; the calls made in the meantime all share that one refetch.
export async function refetchOrders(queryClient: QueryClient, queryKey: QueryKey): Promise<void> {
  const busy = queryClient.isFetching({ queryKey }) > 0;
  await queryClient.invalidateQueries({ queryKey }, { cancelRefetch: false });
  if (busy) await queryClient.invalidateQueries({ queryKey }, { cancelRefetch: false });
}

// Whether something that was in `before` is gone from `after`, i.e. an order left the open ones (it finished).
function someOrderLeft(before: Order[], after: Order[]): boolean {
  const remaining = new Set(after.map((order) => order.uuid));
  return before.some((order) => !remaining.has(order.uuid));
}

// The open orders of a screen, polled so changes show up without a reload (slowly while the real-time socket is up).
// When an order drops out of the list it has finished: the history of that screen is refetched so it shows up there.
// Only the poll is slowed down by the socket: the data still counts as stale after the screen's own interval, so a
// screen opened again (or the app brought back) refetches it even when no event said so.
export function useActiveOrders(scope: OrdersScope, baseIntervalMs: number, enabled = true) {
  const queryClient = useQueryClient();
  const realtimeConnected = useRealtimeStore((state) => state.connected);

  return useQuery({
    queryKey: ordersKeys.active(scope),
    queryFn: async () => {
      const previous = queryClient.getQueryData<ActiveOrders>(ordersKeys.active(scope));
      const next = await fetchActiveOrders();
      if (previous && someOrderLeft(previous.orders, next.orders)) {
        void refetchOrders(queryClient, ordersKeys.history(scope));
      }
      return next;
    },
    enabled,
    ...pollingQueryOptions(getPollInterval(baseIntervalMs, realtimeConnected)),
    staleTime: baseIntervalMs,
  });
}

// The signed-in customer's open orders.
export const useOrders = () => {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  return useActiveOrders('customer', POLL_INTERVAL_MS.customerOrders, isAuthenticated);
};

// The open orders for a staff screen (what GET /orders returns depends on the role). Each screen has its own cache
// entry so its mutations can invalidate just that one.
export const useStaffOrders = (scope: Exclude<OrdersScope, 'customer'>, intervalMs: number) => useActiveOrders(scope, intervalMs);

const DAY_MS = 24 * 60 * 60 * 1000;
// Orders are paged by creation time, but a screen about a period cares about when they finished: an order created the
// day before the period can end inside it.
const SINCE_MARGIN_MS = 2 * DAY_MS;
const DEFAULT_MAX_AUTO_PAGES = 15;
// How often the finished orders of a period are refetched (all their pages), and how old they may be when the user
// comes back to the app before they are.
export const PERIOD_REFRESH_MS = 5 * 60_000;

export interface OrderHistoryOptions {
  enabled?: boolean;
  pageSize?: number;
  // Keep loading older pages by itself until the orders are older than this (epoch ms), up to `maxAutoPages`.
  since?: number;
  maxAutoPages?: number;
  // The orders of a whole period (see ordersKeys.period): refreshed every PERIOD_REFRESH_MS instead of whenever an
  // order of the screen finishes.
  period?: boolean;
}

// The finished orders of a screen, newest first, a page at a time. The cursor lives in the infinite query.
export function useOrderHistory(scope: OrdersScope, options: OrderHistoryOptions = {}) {
  const { enabled = true, pageSize = HISTORY_PAGE_SIZE, since, maxAutoPages = DEFAULT_MAX_AUTO_PAGES, period = false } = options;
  const size = Math.min(Math.max(1, pageSize), HISTORY_MAX_PAGE_SIZE);

  const query = useInfiniteQuery({
    queryKey: [...(period ? ordersKeys.period(scope) : ordersKeys.history(scope)), size],
    queryFn: ({ pageParam }) => fetchOrdersPage(pageParam, size),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    enabled,
    staleTime: period ? PERIOD_REFRESH_MS : 30_000,
    refetchInterval: period ? PERIOD_REFRESH_MS : false,
    retry: 1,
  });

  const { data, hasNextPage, isFetching, isFetchingNextPage, isError, fetchNextPage } = query;
  // Never cancels a refetch of the loaded pages (by default fetchNextPage does, leaving them stale yet marked fresh):
  // asked while one runs, it waits for it instead.
  const loadMore = useCallback(() => fetchNextPage({ cancelRefetch: false }), [fetchNextPage]);
  const pages = data?.pages;
  const orders = useMemo(() => pages?.flatMap((page) => page.orders) ?? NO_ORDERS, [pages]);
  const pagesLoaded = pages?.length ?? 0;

  const oldest = orders.at(-1);
  const reachedSince = since === undefined || (oldest !== undefined && new Date(oldest.created_at).getTime() < since - SINCE_MARGIN_MS);
  const hitCap = pagesLoaded >= maxAutoPages;

  useEffect(() => {
    if (since === undefined || !hasNextPage || isFetching || isError || pagesLoaded === 0) return;
    if (reachedSince || hitCap) return;
    void loadMore();
  }, [since, hasNextPage, isFetching, isError, pagesLoaded, reachedSince, hitCap, loadMore]);

  return {
    orders,
    isLoading: query.isLoading,
    isError,
    error: query.error,
    isFetchNextPageError: query.isFetchNextPageError,
    hasNextPage,
    isFetching,
    isFetchingNextPage,
    fetchNextPage: loadMore,
    dataUpdatedAt: query.dataUpdatedAt,
    // `since` was not reached, within `maxAutoPages` pages or before loading stopped on an error: the screen is
    // showing only the newest part of its period
    capped: since !== undefined && hasNextPage && !reachedSince && (hitCap || isError),
  };
}

// Open orders and finished ones in one list (finished versions win: an order that finished between two requests is
// in both).
export function mergeOrders(active: Order[], history: Order[]): Order[] {
  const byId = new Map<number, Order>();
  for (const order of active) byId.set(order.id, order);
  for (const order of history) byId.set(order.id, order);
  return [...byId.values()];
}
