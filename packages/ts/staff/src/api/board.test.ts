import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { makeStaffOrder, makeStaffOrderDetail } from '../test/board';
import {
  fetchAllStaffOrders,
  fetchBranchList,
  fetchStaffOrder,
  fetchStaffOrders,
  getBoardActions,
  performBoardAction,
  updateStaffOrderStatus,
} from './board';
import { StaffApiError } from './errors';

afterEach(() => vi.unstubAllGlobals());

const query = (request: Request) => Object.fromEntries(new URL(request.url).searchParams);

describe('GET /staff/orders', () => {
  it('sends the filters and the cursor, and reads the next cursor', async () => {
    const order = makeStaffOrder();
    const { calls } = mockFetch({ 'GET /api/v1/staff/orders': () => ok([order], { meta: { next_cursor: 'abc', per_page: 30 } }) });
    const page = await fetchStaffOrders(testApi(), { scope: 'active', branch_id: 4, type: 'pickup', q: 'B1', per_page: 500 }, 'xyz');
    expect(page).toEqual({ orders: [order], nextCursor: 'abc' });
    expect(query(calls[0]!)).toEqual({ scope: 'active', branch_id: '4', type: 'pickup', q: 'B1', per_page: '100', cursor: 'xyz' });
  });

  it('follows the cursor to the end, without duplicates, and stops at the page cap', async () => {
    const [a, b, c] = [makeStaffOrder(), makeStaffOrder(), makeStaffOrder()];
    const pages: Record<string, unknown[]> = { '': [a, b], p2: [b, c] };
    const { calls } = mockFetch({
      'GET /api/v1/staff/orders': (request) => {
        const cursor = new URL(request.url).searchParams.get('cursor') ?? '';
        return ok(pages[cursor], { meta: { next_cursor: cursor === '' ? 'p2' : null, per_page: 100 } });
      },
    });
    expect(await fetchAllStaffOrders(testApi(), { scope: 'active' })).toEqual({ orders: [a, b, c], truncated: false });
    expect(query(calls[0]!).per_page).toBe('100');

    mockFetch({ 'GET /api/v1/staff/orders': () => ok([a], { meta: { next_cursor: 'more', per_page: 100 } }) });
    expect(await fetchAllStaffOrders(testApi(), {}, 2)).toEqual({ orders: [a], truncated: true });
  });

  it('reads one order with its history', async () => {
    const detail = makeStaffOrderDetail();
    mockFetch({ [`GET /api/v1/staff/orders/${detail.uuid}`]: () => ok(detail) });
    expect(await fetchStaffOrder(testApi(), detail.uuid)).toEqual(detail);
  });

  it('lists the branches', async () => {
    mockFetch({ 'GET /api/v1/owner/branches': () => ok([{ id: 1, name: 'Chilonzor' }]) });
    expect(await fetchBranchList(testApi())).toEqual([{ id: 1, name: 'Chilonzor' }]);
  });
});

describe('POST /staff/orders/{uuid}/status', () => {
  it('sends the reason and note only with a cancellation', async () => {
    const order = makeStaffOrder();
    const { calls } = mockFetch({ [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => ok(order) });
    await updateStaffOrderStatus(testApi(), { uuid: order.uuid, status: 'accepted', reason: 'other', note: 'x' });
    await updateStaffOrderStatus(testApi(), { uuid: order.uuid, status: 'rejected', reason: 'out_of_stock', note: 'Osh tugadi' });
    expect(await calls[0]!.json()).toEqual({ status: 'accepted' });
    expect(await calls[1]!.json()).toEqual({ status: 'rejected', reason: 'out_of_stock', note: 'Osh tugadi' });
  });

  it('throws the server refusal with its message and reason', async () => {
    const order = makeStaffOrder();
    mockFetch({ [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => json({ success: false, message: "O'tish mumkin emas", data: { reason: 'transition' } }, 422) });
    const error = await updateStaffOrderStatus(testApi(), { uuid: order.uuid, status: 'ready' }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(StaffApiError);
    expect(error).toMatchObject({ status: 422, message: "O'tish mumkin emas", reason: 'transition' });
  });
});

describe('board actions', () => {
  const OWNER = ['orders.view', 'orders.manage', 'orders.cancel', 'orders.assign_courier', 'kitchen.manage'];

  it('offers the staff screen actions the permissions open', () => {
    const ids = (status: string, type: string, permissions: string[]) => getBoardActions({ status, type }, permissions).map((a) => a.id);
    expect(ids('new', 'delivery', OWNER)).toEqual(['accept', 'reject']);
    expect(ids('new', 'delivery', ['orders.view', 'orders.manage'])).toEqual(['accept']); // a cashier cannot reject
    expect(ids('accepted', 'delivery', ['orders.view', 'kitchen.manage'])).toEqual(['start_preparing']); // a cook
    expect(ids('ready', 'delivery', OWNER)).toEqual(['assign_courier', 'cancel']);
    expect(ids('ready', 'pickup', ['orders.manage'])).toEqual(['hand_over']);
    expect(ids('courier_assigned', 'delivery', ['orders.manage'])).toEqual([]);
    expect(ids('delivered', 'delivery', OWNER)).toEqual([]);
  });

  it('sends each action to the endpoint that takes it', async () => {
    const order = makeStaffOrder();
    const { calls } = mockFetch({
      [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => ok(order),
      [`POST /api/v1/orders/${order.uuid}/assign-courier`]: () => ok(order),
      [`POST /api/v1/orders/${order.uuid}/status`]: () => ok(order),
    });
    const api = testApi();
    await performBoardAction(api, { uuid: order.uuid, action: { to: 'accepted', via: 'status' } });
    await performBoardAction(api, { uuid: order.uuid, action: { to: 'courier_assigned', via: 'assign-courier' }, courierId: 9 });
    await performBoardAction(api, { uuid: order.uuid, action: { to: 'on_the_way', via: 'status' } });
    await expect(performBoardAction(api, { uuid: order.uuid, action: { to: 'courier_assigned', via: 'assign-courier' } })).rejects.toThrow('courier');
    expect(calls.map((call) => new URL(call.url).pathname)).toEqual([
      `/api/v1/staff/orders/${order.uuid}/status`,
      `/api/v1/orders/${order.uuid}/assign-courier`,
      // P2-30: the pick-up goes to the staff endpoint too
      `/api/v1/staff/orders/${order.uuid}/status`,
    ]);
    expect(await calls[1]!.json()).toEqual({ courier_id: 9 });
    expect(await calls[2]!.json()).toEqual({ status: 'on_the_way' });
  });
});
