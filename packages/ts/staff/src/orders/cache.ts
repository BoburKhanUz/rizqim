import type { InfiniteData, QueryClient, QueryKey } from '@tanstack/react-query';
import { staffKeys } from '../hooks/keys';
import { isFinishedStatus } from './status';

// Cache logic of the order lists, shared by every screen that lists orders (and by the real-time handlers that keep them
// fresh). It reads only the few fields it needs, so any order shape fits.

export interface OrderRef {
  uuid: string;
  status: string;
  updated_at?: string | null;
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
export function someOrderLeft(before: readonly OrderRef[], after: readonly OrderRef[]): boolean {
  const remaining = new Set(after.map((order) => order.uuid));
  return before.some((order) => !remaining.has(order.uuid));
}

// Open orders and finished ones in one list (finished versions win: an order that finished between two requests is
// in both).
export function mergeOrders<T extends { id: number }>(active: readonly T[], history: readonly T[]): T[] {
  const byId = new Map<number, T>();
  for (const order of active) byId.set(order.id, order);
  for (const order of history) byId.set(order.id, order);
  return [...byId.values()];
}

// What an order event says about the order: its id and state.
export interface FinishedOrderChange {
  uuid: string;
  status: string;
  updated_at?: string | null;
}

interface HistoryPage {
  orders: OrderRef[];
}

// An order that is already in a history list can still change (delivered -> refunded). The event says so, and the lists
// show it at once instead of after their next staleness refetch: the cached copy takes the new status in place. No
// request at all, so a burst of events cannot become a refetch of every loaded page (which is what refetching a paged
// history costs). Only an order that is finished before and after is touched: one that has just finished is the open
// list's business (it drops out of it and the history is refetched, useActiveOrders). `updatedAt` of each query is kept,
// so patching never makes older pages look fresher than they are. Returns whether any list had the order.
export function applyFinishedOrderChange(queryClient: QueryClient, change: FinishedOrderChange): boolean {
  if (!isFinishedStatus(change.status)) return false;

  const changes = (order: OrderRef) => order.uuid === change.uuid && isFinishedStatus(order.status) && order.status !== change.status;

  let patched = false;
  for (const query of queryClient.getQueryCache().findAll({ queryKey: staffKeys.allHistory })) {
    const data = query.state.data as InfiniteData<HistoryPage> | undefined;
    if (!data?.pages.some((page) => page.orders.some(changes))) continue;

    patched = true;
    queryClient.setQueryData<InfiniteData<HistoryPage>>(
      query.queryKey,
      {
        ...data,
        pages: data.pages.map((page) => ({
          ...page,
          orders: page.orders.map((order) =>
            changes(order) ? { ...order, status: change.status, updated_at: change.updated_at ?? order.updated_at } : order,
          ),
        })),
      },
      { updatedAt: query.state.dataUpdatedAt },
    );
  }
  return patched;
}
