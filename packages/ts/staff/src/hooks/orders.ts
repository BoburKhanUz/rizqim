import { useCallback, useEffect, useMemo } from 'react';
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type InfiniteData,
  type QueryClient,
  type UseMutationOptions,
  type UseQueryOptions,
} from '@tanstack/react-query';
import {
  assignCourier,
  changeOrderStatus,
  completeOrder,
  fetchActiveOrders,
  fetchCouriers,
  fetchOrdersPage,
  HISTORY_MAX_PAGE_SIZE,
  HISTORY_PAGE_SIZE,
  performOrderAction,
  type ActiveOrders,
  type AssignableCourier,
  type OrderActionRequest,
  type OrderChange,
  type OrdersPage,
} from '../api/orders';
import { refetchOrders, someOrderLeft } from '../orders/cache';
import type { Order } from '../types';
import { useStaffOrder } from './board';
import { useStaffApi } from './context';
import { staffKeys } from './keys';

// What a caller may add to a query: polling, `enabled`, placeholder data ... but not what the hook decides.
export type QueryExtras<TData> = Omit<UseQueryOptions<TData>, 'queryKey' | 'queryFn'>;
export type MutationExtras<TData, TVariables> = Omit<UseMutationOptions<TData, Error, TVariables>, 'mutationFn'>;

export const NO_ORDERS: Order[] = [];

export interface ActiveOrdersOptions extends QueryExtras<ActiveOrders> {
  // Reads the list some other way (the Mini App's customer screens still use their own client)
  fetchActive?: () => Promise<ActiveOrders>;
}

// The open orders of a screen (`view`). Polling and the like come in through the options. When an order drops out of the
// list it has finished: the history of that screen is refetched so it shows up there.
export function useActiveOrders(view: string, options: ActiveOrdersOptions = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  const { fetchActive, ...queryOptions } = options;

  return useQuery({
    ...queryOptions,
    queryKey: staffKeys.active(view),
    queryFn: async () => {
      const previous = queryClient.getQueryData<ActiveOrders>(staffKeys.active(view));
      const next = await (fetchActive ? fetchActive() : fetchActiveOrders(api));
      if (previous && someOrderLeft(previous.orders, next.orders)) {
        void refetchOrders(queryClient, staffKeys.history(view));
      }
      return next;
    },
  });
}

const DAY_MS = 24 * 60 * 60 * 1000;
// Orders are paged by creation time, but a screen about a period cares about when they finished: an order created the
// day before the period can end inside it.
const SINCE_MARGIN_MS = 2 * DAY_MS;
const DEFAULT_MAX_AUTO_PAGES = 15;

export interface OrderHistoryOptions {
  enabled?: boolean;
  pageSize?: number;
  // Keep loading older pages by itself until the orders are older than this (epoch ms), up to `maxAutoPages`.
  since?: number;
  maxAutoPages?: number;
  // Reads a page some other way (see ActiveOrdersOptions.fetchActive)
  fetchPage?: (cursor: string | null, pageSize: number) => Promise<OrdersPage>;
}

// The finished orders of a screen, newest first, a page at a time. The cursor lives in the infinite query.
export function useOrderHistory(view: string, options: OrderHistoryOptions = {}) {
  const api = useStaffApi();
  const { enabled = true, pageSize = HISTORY_PAGE_SIZE, since, maxAutoPages = DEFAULT_MAX_AUTO_PAGES, fetchPage } = options;
  const size = Math.min(Math.max(1, pageSize), HISTORY_MAX_PAGE_SIZE);

  const query = useInfiniteQuery({
    queryKey: [...staffKeys.history(view), size],
    queryFn: ({ pageParam }) => (fetchPage ? fetchPage(pageParam, size) : fetchOrdersPage(api, pageParam, size)),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    enabled,
    staleTime: 30_000,
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
  const reachedSince = since === undefined || (oldest?.created_at != null && new Date(oldest.created_at).getTime() < since - SINCE_MARGIN_MS);
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
    // Loads the pages again (the retry button of an error)
    refetch: query.refetch,
    isRefetching: query.isRefetching,
    dataUpdatedAt: query.dataUpdatedAt,
  };
}

// An order of the lists already in the cache (open or finished), by uuid.
export function findCachedOrder(queryClient: QueryClient, uuid: string): Order | undefined {
  for (const [, data] of queryClient.getQueriesData<ActiveOrders>({ queryKey: staffKeys.allActive })) {
    const found = data?.orders.find((order) => order.uuid === uuid);
    if (found) return found;
  }
  for (const [, data] of queryClient.getQueriesData<InfiniteData<OrdersPage>>({ queryKey: staffKeys.allHistory })) {
    for (const page of data?.pages ?? []) {
      const found = page.orders.find((order) => order.uuid === uuid);
      if (found) return found;
    }
  }
  return undefined;
}

export interface OrderOptions extends ActiveOrdersOptions {
  // Also load the order itself from GET /staff/orders/{uuid} (`detail`: items with modifiers and weights, kitchen state,
  // notes, timestamps, status history). On by default; a screen of someone without orders.view (a courier: 403) turns
  // it off and keeps the list's order only.
  detail?: boolean;
}

// One order for a detail view (P2-21: from the detail endpoint). `order` is the order as the open list of `view` (or a
// list already in the cache) has it, as before; `detail` is GET /staff/orders/{uuid}, the full order, refetched with
// `refetch` and by a realtime event (boardKeys.detail). A failed detail leaves `order` as it is (`detailError`).
export function useOrder(uuid: string | undefined, view: string, options: OrderOptions = {}) {
  const { detail: withDetail = true, ...listOptions } = options;
  const queryClient = useQueryClient();
  const active = useActiveOrders(view, listOptions);
  const detailQuery = useStaffOrder(withDetail ? uuid : undefined, { enabled: listOptions.enabled !== false, retry: false });
  const order = uuid === undefined ? undefined : (active.data?.orders.find((o) => o.uuid === uuid) ?? findCachedOrder(queryClient, uuid));
  const { refetch: refetchList } = active;
  const { refetch: refetchDetail } = detailQuery;
  const detailOn = withDetail && uuid !== undefined;
  const refetch = useCallback<typeof refetchList>(
    (...args) => {
      if (detailOn) void refetchDetail();
      return refetchList(...args);
    },
    [detailOn, refetchDetail, refetchList],
  );
  return {
    order,
    detail: detailQuery.data,
    detailError: detailQuery.error,
    isLoading: active.isLoading || (detailOn && detailQuery.isLoading && order === undefined),
    isError: active.isError && detailQuery.data === undefined,
    refetch,
  };
}

// The couriers an order can be assigned to.
export function useCouriers(branchId?: number, options: QueryExtras<AssignableCourier[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: [...staffKeys.couriers, branchId ?? null], queryFn: () => fetchCouriers(api, branchId) });
}

// Mutations. An order moves between the open list and the archive, so every order list is refetched afterwards; the
// caller's own onSuccess / onError / onMutate run as well.
function useOrderMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<Order>, options: MutationExtras<Order, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.orders });
      return options.onSuccess?.(...args);
    },
  });
}

// POST /orders/{uuid}/status, or assign-courier for the move to courier_assigned
export function useUpdateOrderStatus(options: MutationExtras<Order, OrderChange> = {}) {
  const api = useStaffApi();
  return useOrderMutation((change: OrderChange) => changeOrderStatus(api, change), options);
}

export function useAssignCourier(options: MutationExtras<Order, { uuid: string; courierId: number }> = {}) {
  const api = useStaffApi();
  return useOrderMutation(({ uuid, courierId }: { uuid: string; courierId: number }) => assignCourier(api, uuid, courierId), options);
}

// POST /orders/{uuid}/complete: the courier marks a delivery handed over.
export function useCompleteOrder(options: MutationExtras<Order, string> = {}) {
  const api = useStaffApi();
  return useOrderMutation((uuid: string) => completeOrder(api, uuid), options);
}

// Any of the actions orders/status.ts offers, on the endpoint it names.
export function useOrderAction(options: MutationExtras<Order, OrderActionRequest> = {}) {
  const api = useStaffApi();
  return useOrderMutation((request: OrderActionRequest) => performOrderAction(api, request), options);
}
