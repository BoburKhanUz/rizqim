import type { InfiniteData, Query, QueryClient } from '@tanstack/react-query';
import type { StaffOrder, StaffOrderDetail, StaffOrdersList, StaffOrdersPage, StaffOrdersQuery } from '../api/board';
import { isFinishedStatus } from '../orders/status';

// Wave M2 fix round 2: the owner board applies an order event to what it already holds instead of reading its lists
// again (docs/scale/RESULTS_M2.md: a board list read costs ~67 ms PHP, 31 statements and ~96 KB; at lunch every write
// of a branch made the board read two of them).
//
//  - applyBoardOrder: one order as GET /staff/orders/{uuid} answered it is patched into every cached board list
//    (['staff-orders', 'list', query]: the open orders, the `status=new` badge), today's finished pages
//    (['staff-orders', 'pages', query]) and the drawer (['staff-orders', 'detail', uuid]). An order that no longer
//    matches a list's filter (another status, branch, date, type) leaves it; one that now matches is put in at its
//    place (newest first, as the API sorts); one that is in no list is added where it belongs, unless it would sort
//    after what the list has loaded (it is on a later page). A list whose filter this code cannot evaluate, or that is
//    being read right now (the answer may predate the change), is read again instead: when in doubt, re-read.
//  - removeBoardOrder: the order is gone for this person (404/403 on its read: another branch, no access any more).
//  - applyKitchenEvent: kitchen.item_status_changed carries the line, its kitchen state and the order's progress
//    (items_total, items_ready): patched in, no read. The order's status comes with its own event.

const LIST_PREFIX = 'staff-orders';

export interface BoardCacheOptions {
  // The store's time zone, for `date`/`from`/`to` (days in the store's time zone, on created_at)
  timeZone?: string;
}

const DEFAULT_TIME_ZONE = 'Asia/Tashkent';
const KNOWN_FILTERS = new Set(['branch_id', 'scope', 'status', 'type', 'channel', 'date', 'from', 'to', 'q', 'per_page']);

type ListData = StaffOrdersList;
type PagesData = InfiniteData<StaffOrdersPage, string | null>;

// Does `order` belong in a list read with `query`? null: this code cannot tell (an unknown filter)
export function matchesBoardQuery(order: StaffOrder, query: StaffOrdersQuery, { timeZone = DEFAULT_TIME_ZONE }: BoardCacheOptions = {}): boolean | null {
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '' && !KNOWN_FILTERS.has(key)) return null;
  }
  if (query.branch_id !== undefined && order.branch_id !== query.branch_id) return false;
  if (query.scope === 'active' && isFinishedStatus(order.status)) return false;
  if (query.scope === 'history' && !isFinishedStatus(order.status)) return false;
  if (query.status) {
    const wanted = query.status.split(',').map((status) => status.trim()).filter((status) => status !== '');
    if (wanted.length > 0 && !wanted.includes(order.status)) return false;
  }
  if (query.type && order.type !== query.type) return false;
  if (query.channel && order.channel !== query.channel) return false;
  if (query.q) {
    if (!order.short_code || !order.short_code.toUpperCase().startsWith(query.q.toUpperCase())) return false;
  }
  const from = query.date ?? query.from;
  const to = query.date ?? query.to;
  if (from || to) {
    const day = dayOf(order.created_at, timeZone);
    if (day === null) return null;
    if (from && day < from) return false;
    if (to && day > to) return false;
  }
  return true;
}

// Y-m-d of an ISO time in `timeZone`; null when it cannot be read
function dayOf(iso: string | null, timeZone: string): string | null {
  if (!iso) return null;
  const time = new Date(iso);
  if (Number.isNaN(time.getTime())) return null;
  try {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(time);
  } catch {
    return null;
  }
}

const createdMs = (order: StaffOrder): number => {
  const ms = order.created_at ? new Date(order.created_at).getTime() : Number.NaN;
  return Number.isNaN(ms) ? 0 : ms;
};

// The list form of an order (the detail's status history stays in the drawer)
function asListOrder(order: StaffOrder | StaffOrderDetail): StaffOrder {
  if (!('status_history' in order)) return order;
  const copy: Partial<StaffOrderDetail> = { ...order };
  delete copy.status_history;
  return copy as StaffOrder;
}

// Puts `order` into a newest-first list at its place; null when it sorts after everything and more pages exist
function insertSorted(orders: StaffOrder[], order: StaffOrder, moreAfter: boolean): StaffOrder[] | null {
  const ms = createdMs(order);
  const index = orders.findIndex((other) => createdMs(other) < ms);
  if (index === -1) return moreAfter ? null : [...orders, order];
  return [...orders.slice(0, index), order, ...orders.slice(index)];
}

type Outcome = { data: unknown; changed: boolean } | 'reread';

function patchList(data: ListData, order: StaffOrder, matches: boolean | null): Outcome {
  const index = data.orders.findIndex((other) => other.uuid === order.uuid);
  if (matches === null) return 'reread';
  if (index >= 0) {
    const orders = matches ? data.orders.map((other, i) => (i === index ? order : other)) : data.orders.filter((_, i) => i !== index);
    return { data: { ...data, orders }, changed: true };
  }
  if (!matches) return { data, changed: false };
  const orders = insertSorted(data.orders, order, data.truncated);
  return orders ? { data: { ...data, orders }, changed: true } : { data, changed: false };
}

function patchPages(data: PagesData, order: StaffOrder, matches: boolean | null): Outcome {
  if (matches === null) return 'reread';
  const pageIndex = data.pages.findIndex((page) => page.orders.some((other) => other.uuid === order.uuid));
  if (pageIndex >= 0) {
    const pages = data.pages.map((page, i) =>
      i !== pageIndex
        ? page
        : { ...page, orders: matches ? page.orders.map((other) => (other.uuid === order.uuid ? order : other)) : page.orders.filter((other) => other.uuid !== order.uuid) },
    );
    return { data: { ...data, pages }, changed: true };
  }
  if (!matches || data.pages.length === 0) return { data, changed: false };
  const ms = createdMs(order);
  // The first page holding something older than the order; else the last page, if nothing more is to come
  let target = data.pages.findIndex((page) => page.orders.some((other) => createdMs(other) < ms));
  const last = data.pages.length - 1;
  if (target === -1) {
    if (data.pages[last]?.nextCursor) return { data, changed: false };
    target = last;
  }
  const pages = data.pages.map((page, i) => (i === target ? { ...page, orders: insertSorted(page.orders, order, false) ?? page.orders } : page));
  return { data: { ...data, pages }, changed: true };
}

function boardQueries(queryClient: QueryClient, kind: 'list' | 'pages'): Query[] {
  return queryClient.getQueryCache().findAll({ queryKey: [LIST_PREFIX, kind] });
}

const queryOf = (query: Query): StaffOrdersQuery => (query.queryKey[2] ?? {}) as StaffOrdersQuery;

// The answer of a list read under way may predate the change: it is read again (it would overwrite the patch)
function rereadIfFetching(queryClient: QueryClient, query: Query): void {
  if (query.state.fetchStatus === 'fetching') void queryClient.invalidateQueries({ queryKey: query.queryKey, exact: true }, { cancelRefetch: true });
}

export interface ApplyResult {
  // Lists and pages the order was patched into (or taken out of)
  patched: number;
  // Lists read again instead (a filter this code cannot evaluate)
  reread: number;
}

export function applyBoardOrder(queryClient: QueryClient, order: StaffOrder | StaffOrderDetail, options: BoardCacheOptions = {}): ApplyResult {
  const listOrder = asListOrder(order);
  const result: ApplyResult = { patched: 0, reread: 0 };
  for (const kind of ['list', 'pages'] as const) {
    for (const query of boardQueries(queryClient, kind)) {
      const data = query.state.data;
      if (data === undefined) continue;
      const matches = matchesBoardQuery(listOrder, queryOf(query), options);
      const outcome = kind === 'list' ? patchList(data as ListData, listOrder, matches) : patchPages(data as PagesData, listOrder, matches);
      if (outcome === 'reread') {
        result.reread += 1;
        void queryClient.invalidateQueries({ queryKey: query.queryKey, exact: true });
        continue;
      }
      if (!outcome.changed) continue;
      result.patched += 1;
      queryClient.setQueryData(query.queryKey, outcome.data);
      rereadIfFetching(queryClient, query);
    }
  }
  if ('status_history' in order) queryClient.setQueryData([LIST_PREFIX, 'detail', order.uuid], order);
  return result;
}

// The order is no longer visible to this person (404/403 on its read): out of every list; an open drawer reads it
// again and says so itself
export function removeBoardOrder(queryClient: QueryClient, uuid: string): number {
  let removed = 0;
  for (const query of boardQueries(queryClient, 'list')) {
    const data = query.state.data as ListData | undefined;
    if (!data?.orders.some((order) => order.uuid === uuid)) continue;
    queryClient.setQueryData(query.queryKey, { ...data, orders: data.orders.filter((order) => order.uuid !== uuid) });
    removed += 1;
  }
  for (const query of boardQueries(queryClient, 'pages')) {
    const data = query.state.data as PagesData | undefined;
    if (!data?.pages.some((page) => page.orders.some((order) => order.uuid === uuid))) continue;
    queryClient.setQueryData(query.queryKey, { ...data, pages: data.pages.map((page) => ({ ...page, orders: page.orders.filter((order) => order.uuid !== uuid) })) });
    removed += 1;
  }
  void queryClient.invalidateQueries({ queryKey: [LIST_PREFIX, 'detail', uuid], exact: true });
  return removed;
}

export interface KitchenEventPayload {
  uuid: string;
  item: { id: number; kitchen_status: string; ready_at: string | null };
  itemsTotal: number | null;
  itemsReady: number | null;
}

// kitchen.item_status_changed as the contract sends it (docs/API.md §8); null when it is not that shape
export function parseKitchenEvent(payload: unknown): KitchenEventPayload | null {
  if (typeof payload !== 'object' || payload === null) return null;
  const { order, item, items_total: total, items_ready: ready } = payload as Record<string, unknown>;
  const uuid = typeof order === 'object' && order !== null ? (order as { uuid?: unknown }).uuid : undefined;
  if (typeof uuid !== 'string' || typeof item !== 'object' || item === null) return null;
  const { id, kitchen_status: status, ready_at: readyAt } = item as Record<string, unknown>;
  if (typeof id !== 'number' || typeof status !== 'string') return null;
  return {
    uuid,
    item: { id, kitchen_status: status, ready_at: typeof readyAt === 'string' ? readyAt : null },
    itemsTotal: typeof total === 'number' ? total : null,
    itemsReady: typeof ready === 'number' ? ready : null,
  };
}

function patchKitchen<T extends StaffOrder>(order: T, event: KitchenEventPayload): T {
  return {
    ...order,
    items: order.items.map((item) => (item.id === event.item.id ? { ...item, kitchen_status: event.item.kitchen_status, ready_at: event.item.ready_at } : item)),
    ...(event.itemsTotal === null ? {} : { items_total: event.itemsTotal }),
    ...(event.itemsReady === null ? {} : { items_ready: event.itemsReady }),
  };
}

// Applies kitchen.item_status_changed to every cached copy of the order; the number of copies patched (0: the order
// is not on the board, nothing to show)
export function applyKitchenEvent(queryClient: QueryClient, payload: unknown): number {
  const event = parseKitchenEvent(payload);
  if (!event) return 0;
  let patched = 0;
  for (const query of boardQueries(queryClient, 'list')) {
    const data = query.state.data as ListData | undefined;
    if (!data?.orders.some((order) => order.uuid === event.uuid)) continue;
    queryClient.setQueryData(query.queryKey, { ...data, orders: data.orders.map((order) => (order.uuid === event.uuid ? patchKitchen(order, event) : order)) });
    patched += 1;
  }
  for (const query of boardQueries(queryClient, 'pages')) {
    const data = query.state.data as PagesData | undefined;
    if (!data?.pages.some((page) => page.orders.some((order) => order.uuid === event.uuid))) continue;
    queryClient.setQueryData(query.queryKey, {
      ...data,
      pages: data.pages.map((page) => ({ ...page, orders: page.orders.map((order) => (order.uuid === event.uuid ? patchKitchen(order, event) : order)) })),
    });
    patched += 1;
  }
  const detailKey = [LIST_PREFIX, 'detail', event.uuid];
  const detail = queryClient.getQueryData<StaffOrderDetail>(detailKey);
  if (detail) {
    queryClient.setQueryData(detailKey, patchKitchen(detail, event));
    patched += 1;
  }
  return patched;
}
