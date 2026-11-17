import { QueryClient, type InfiniteData } from '@tanstack/react-query';
import { describe, expect, it } from 'vitest';
import type { StaffOrder, StaffOrderDetail, StaffOrdersList, StaffOrdersPage, StaffOrdersQuery } from '../api/board';
import { boardKeys } from '../hooks/board';
import { makeStaffOrder } from '../test/board';
import { applyBoardOrder, applyKitchenEvent, matchesBoardQuery, removeBoardOrder } from './boardCache';

const OPEN: StaffOrdersQuery = { scope: 'active', branch_id: 1 };
const BADGE: StaffOrdersQuery = { scope: 'active', status: 'new', branch_id: 1 };
const TODAY = '2026-10-20';
const FINISHED: StaffOrdersQuery = { scope: 'history', date: TODAY, per_page: 30, branch_id: 1 };

const at = (hour: number) => `${TODAY}T${String(hour).padStart(2, '0')}:00:00+05:00`;
const detailOf = (order: StaffOrder): StaffOrderDetail => ({ ...order, status_history: [{ from_status: null, status: order.status, created_at: order.created_at }] });

function client() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}
const list = (queryClient: QueryClient, query: StaffOrdersQuery) => queryClient.getQueryData<StaffOrdersList>(boardKeys.list(query))?.orders.map((order) => order.uuid);
const pages = (queryClient: QueryClient) =>
  queryClient.getQueryData<InfiniteData<StaffOrdersPage>>(boardKeys.pages(FINISHED))?.pages.map((page) => page.orders.map((order) => order.uuid));
const setList = (queryClient: QueryClient, query: StaffOrdersQuery, orders: StaffOrder[], truncated = false) =>
  queryClient.setQueryData(boardKeys.list(query), { orders, truncated });
const setPages = (queryClient: QueryClient, groups: StaffOrder[][], nextCursor: string | null = null) =>
  queryClient.setQueryData(boardKeys.pages(FINISHED), {
    pages: groups.map((orders, i) => ({ orders, nextCursor: i === groups.length - 1 ? nextCursor : `c${i}` })),
    pageParams: groups.map((_, i) => (i === 0 ? null : `c${i - 1}`)),
  });

describe('matchesBoardQuery', () => {
  it('evaluates the filters GET /staff/orders takes, and says so when it cannot', () => {
    const order = makeStaffOrder({ status: 'accepted', branch_id: 1, type: 'delivery', channel: 'telegram', short_code: 'AB12', created_at: at(10) });
    expect(matchesBoardQuery(order, OPEN)).toBe(true);
    expect(matchesBoardQuery(order, { ...OPEN, branch_id: 2 })).toBe(false);
    expect(matchesBoardQuery(order, BADGE)).toBe(false);
    expect(matchesBoardQuery(order, { scope: 'active', status: 'new,accepted' })).toBe(true);
    expect(matchesBoardQuery(order, { scope: 'history' })).toBe(false);
    expect(matchesBoardQuery(order, { type: 'pickup' })).toBe(false);
    expect(matchesBoardQuery(order, { channel: 'pos_hall' })).toBe(false);
    expect(matchesBoardQuery(order, { q: 'ab' })).toBe(true);
    expect(matchesBoardQuery(order, { q: 'AC' })).toBe(false);
    expect(matchesBoardQuery(order, { date: TODAY })).toBe(true);
    expect(matchesBoardQuery(order, { date: '2026-10-19' })).toBe(false);
    // 00:30 on the 21st in Tashkent, still the 20th in UTC
    expect(matchesBoardQuery({ ...order, created_at: '2026-10-20T19:30:00Z' }, { date: '2026-10-21' })).toBe(true);
    expect(matchesBoardQuery({ ...order, created_at: null }, { date: TODAY })).toBeNull();
    expect(matchesBoardQuery(order, { ...OPEN, mystery: 'x' } as StaffOrdersQuery)).toBeNull();
  });
});

describe('applyBoardOrder', () => {
  it('replaces an order in place in the open list and the drawer', () => {
    const queryClient = client();
    const a = makeStaffOrder({ status: 'new', created_at: at(11) });
    const b = makeStaffOrder({ status: 'new', created_at: at(10) });
    setList(queryClient, OPEN, [a, b]);
    const changed = detailOf({ ...b, status: 'accepted', status_label: 'Qabul qilindi' });
    expect(applyBoardOrder(queryClient, changed)).toEqual({ patched: 1, reread: 0 });
    const held = queryClient.getQueryData<StaffOrdersList>(boardKeys.list(OPEN))?.orders;
    expect(held?.map((order) => [order.uuid, order.status])).toEqual([
      [a.uuid, 'new'],
      [b.uuid, 'accepted'],
    ]);
    // the list keeps the list shape: the history stays in the drawer
    expect(held?.[1]).not.toHaveProperty('status_history');
    expect(queryClient.getQueryData(boardKeys.detail(b.uuid))).toEqual(changed);
  });

  it('a new order goes in at its place (newest first) and into the badge, once', () => {
    const queryClient = client();
    const older = makeStaffOrder({ status: 'accepted', created_at: at(9) });
    const newer = makeStaffOrder({ status: 'accepted', created_at: at(12) });
    setList(queryClient, OPEN, [newer, older]);
    setList(queryClient, BADGE, []);
    const created = makeStaffOrder({ status: 'new', created_at: at(10) });
    applyBoardOrder(queryClient, detailOf(created));
    applyBoardOrder(queryClient, detailOf(created)); // the same event twice: not duplicated
    expect(list(queryClient, OPEN)).toEqual([newer.uuid, created.uuid, older.uuid]);
    expect(list(queryClient, BADGE)).toEqual([created.uuid]);
  });

  it('an accepted order leaves the badge; a finished one leaves the open list and enters today\'s finished pages', () => {
    const queryClient = client();
    const order = makeStaffOrder({ status: 'new', created_at: at(10) });
    const done = makeStaffOrder({ status: 'delivered', created_at: at(8) });
    setList(queryClient, OPEN, [order]);
    setList(queryClient, BADGE, [order]);
    setPages(queryClient, [[done]]);
    applyBoardOrder(queryClient, detailOf({ ...order, status: 'accepted' }));
    expect(list(queryClient, BADGE)).toEqual([]);
    expect(list(queryClient, OPEN)).toEqual([order.uuid]);
    applyBoardOrder(queryClient, detailOf({ ...order, status: 'canceled' }));
    expect(list(queryClient, OPEN)).toEqual([]);
    expect(pages(queryClient)).toEqual([[order.uuid, done.uuid]]);
  });

  it('an order of another branch or another day stays out', () => {
    const queryClient = client();
    setList(queryClient, OPEN, []);
    setPages(queryClient, [[]]);
    applyBoardOrder(queryClient, detailOf(makeStaffOrder({ status: 'new', branch_id: 2, created_at: at(10) })));
    applyBoardOrder(queryClient, detailOf(makeStaffOrder({ status: 'delivered', created_at: '2026-10-19T10:00:00+05:00' })));
    expect(list(queryClient, OPEN)).toEqual([]);
    expect(pages(queryClient)).toEqual([[]]);
  });

  it('an order older than everything a truncated list or the loaded pages hold is left to its later page', () => {
    const queryClient = client();
    const recent = makeStaffOrder({ status: 'accepted', created_at: at(12) });
    setList(queryClient, OPEN, [recent], true);
    const doneRecent = makeStaffOrder({ status: 'delivered', created_at: at(12) });
    setPages(queryClient, [[doneRecent]], 'more');
    applyBoardOrder(queryClient, detailOf(makeStaffOrder({ status: 'accepted', created_at: at(6) })));
    applyBoardOrder(queryClient, detailOf(makeStaffOrder({ status: 'delivered', created_at: at(6) })));
    expect(list(queryClient, OPEN)).toEqual([recent.uuid]);
    expect(pages(queryClient)).toEqual([[doneRecent.uuid]]);
  });

  it('a finished order already on a later page is replaced there', () => {
    const queryClient = client();
    const first = makeStaffOrder({ status: 'delivered', created_at: at(12) });
    const second = makeStaffOrder({ status: 'delivered', created_at: at(9) });
    setPages(queryClient, [[first], [second]]);
    applyBoardOrder(queryClient, detailOf({ ...second, status: 'refunded' }));
    const held = queryClient.getQueryData<InfiniteData<StaffOrdersPage>>(boardKeys.pages(FINISHED));
    expect(held?.pages[1]?.orders[0]?.status).toBe('refunded');
    expect(held?.pageParams).toEqual([null, 'c0']);
  });

  it('a list with a filter it cannot evaluate is read again instead of guessed', async () => {
    const queryClient = client();
    const odd = { ...OPEN, mystery: 'x' } as StaffOrdersQuery;
    setList(queryClient, odd, []);
    const result = applyBoardOrder(queryClient, detailOf(makeStaffOrder({ status: 'new', created_at: at(10) })));
    expect(result).toEqual({ patched: 0, reread: 1 });
    expect(queryClient.getQueryState(boardKeys.list(odd))?.isInvalidated).toBe(true);
  });
});

describe('removeBoardOrder', () => {
  it('takes the order out of every list and page, and has the drawer read it again', () => {
    const queryClient = client();
    const order = makeStaffOrder({ status: 'new', created_at: at(10) });
    const other = makeStaffOrder({ status: 'new', created_at: at(9) });
    setList(queryClient, OPEN, [order, other]);
    setList(queryClient, BADGE, [order]);
    queryClient.setQueryData(boardKeys.detail(order.uuid), detailOf(order));
    expect(removeBoardOrder(queryClient, order.uuid)).toBe(2);
    expect(list(queryClient, OPEN)).toEqual([other.uuid]);
    expect(list(queryClient, BADGE)).toEqual([]);
    expect(queryClient.getQueryState(boardKeys.detail(order.uuid))?.isInvalidated).toBe(true);
  });
});

describe('applyKitchenEvent', () => {
  const payload = (order: StaffOrder, itemId: number) => ({
    order: { uuid: order.uuid, status: 'preparing', store_id: 1, branch_id: 1, courier_id: null, updated_at: at(10) },
    item: { id: itemId, kitchen_status: 'ready', ready_at: at(10) },
    items_total: 1,
    items_ready: 1,
    rev: { 'pos-branch.1': '5' },
  });

  it('patches the line, the progress and the drawer from the payload, without a read', () => {
    const queryClient = client();
    const order = makeStaffOrder({ status: 'preparing', created_at: at(10) });
    const itemId = order.items[0]?.id ?? 0;
    setList(queryClient, OPEN, [order]);
    queryClient.setQueryData(boardKeys.detail(order.uuid), detailOf(order));
    expect(applyKitchenEvent(queryClient, payload(order, itemId))).toBe(2);
    const held = queryClient.getQueryData<StaffOrdersList>(boardKeys.list(OPEN))?.orders[0];
    expect(held?.items[0]).toMatchObject({ kitchen_status: 'ready', ready_at: at(10) });
    expect(held?.items_ready).toBe(1);
    expect(queryClient.getQueryData<StaffOrderDetail>(boardKeys.detail(order.uuid))?.items[0]?.kitchen_status).toBe('ready');
    expect(queryClient.getQueryState(boardKeys.list(OPEN))?.isInvalidated).toBe(false);
  });

  it('ignores an order the board does not hold and a payload of another shape', () => {
    const queryClient = client();
    setList(queryClient, OPEN, []);
    expect(applyKitchenEvent(queryClient, payload(makeStaffOrder(), 1))).toBe(0);
    expect(applyKitchenEvent(queryClient, { order: { uuid: 'x' } })).toBe(0);
    expect(applyKitchenEvent(queryClient, null)).toBe(0);
  });
});
