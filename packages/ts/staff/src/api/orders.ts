import type { Order, OrderStatus, StaffCancelReason } from '../types';
import { isCancellation } from '../orders/status';
import type { OrderAction } from '../orders/status';
import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// GET /orders is read in two scopes (the unscoped, unbounded list is not used any more):
//  - active: the orders still in progress, newest first, at most 200 (`truncated` says there were more);
//  - history: the finished ones, a page at a time (keyset pagination: `next_cursor` is opaque).
// What the list holds depends on the role of the token: the store's orders for an owner, the branch's for a manager, the
// courier's own deliveries for a courier.

export const HISTORY_PAGE_SIZE = 20;
export const HISTORY_MAX_PAGE_SIZE = 50; // what the backend caps per_page at

export interface ActiveOrders {
  orders: Order[];
  truncated: boolean;
}

export interface OrdersPage {
  orders: Order[];
  nextCursor: string | null;
}

export async function fetchActiveOrders(api: StaffApi): Promise<ActiveOrders> {
  const body = unwrap(await api.client.GET('/v1/orders', { params: { query: { scope: 'active' } } }));
  const meta = 'meta' in body ? (body.meta as { truncated?: boolean }) : undefined;
  return { orders: body.data ?? [], truncated: meta?.truncated === true };
}

export async function fetchOrdersPage(api: StaffApi, cursor: string | null, pageSize: number = HISTORY_PAGE_SIZE): Promise<OrdersPage> {
  const size = Math.min(Math.max(1, pageSize), HISTORY_MAX_PAGE_SIZE);
  const body = unwrap(
    await api.client.GET('/v1/orders', { params: { query: { scope: 'history', per_page: size, ...(cursor ? { cursor } : {}) } } }),
  );
  const meta = 'meta' in body ? (body.meta as { next_cursor?: string | null }) : undefined;
  return { orders: body.data ?? [], nextCursor: meta?.next_cursor ?? null };
}

// GET /couriers: who an order can be assigned to (owner, manager). A manager always gets their own branch's.
export type AssignableCourier = OkData<'order.couriers'>[number];

export async function fetchCouriers(api: StaffApi, branchId?: number): Promise<AssignableCourier[]> {
  const body = unwrap(await api.client.GET('/v1/couriers', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

// --- Changing an order ----------------------------------------------------------------------------------------------

type StatusBody = JsonRequest<'order.updateStatus'>;

export interface OrderChange {
  uuid: string;
  status: OrderStatus;
  courierId?: number; // with status courier_assigned
  reason?: StaffCancelReason; // required by the API for canceled and rejected
  note?: string;
}

// POST /orders/{uuid}/assign-courier: only while the order allows it (403/422 otherwise).
export async function assignCourier(api: StaffApi, uuid: string, courierId: number): Promise<Order> {
  const body = unwrap(await api.client.POST('/v1/orders/{uuid}/assign-courier', { params: { path: { uuid } }, body: { courier_id: courierId } }));
  return body.data;
}

// POST /orders/{uuid}/complete: the courier marks his delivery handed over.
export async function completeOrder(api: StaffApi, uuid: string): Promise<Order> {
  const body = unwrap(await api.client.POST('/v1/orders/{uuid}/complete', { params: { path: { uuid } } }));
  return body.data;
}

// POST /orders/{uuid}/status: only the transitions the order's state machine allows (403/422 otherwise). Cancelling or
// rejecting carries the reason (and an optional note); any other status carries nothing else.
export async function updateOrderStatus(api: StaffApi, change: OrderChange): Promise<Order> {
  const payload: StatusBody = {
    status: change.status as StatusBody['status'],
    ...(isCancellation(change.status) ? { reason: change.reason, ...(change.note ? { note: change.note } : {}) } : {}),
  };
  const body = unwrap(await api.client.POST('/v1/orders/{uuid}/status', { params: { path: { uuid: change.uuid } }, body: payload }));
  return body.data;
}

// The change a screen asks for, sent the way the API wants it: moving to courier_assigned is the assign-courier
// endpoint (it needs the courier), anything else the status one.
export async function changeOrderStatus(api: StaffApi, change: OrderChange): Promise<Order> {
  if (change.status === 'courier_assigned') {
    if (change.courierId === undefined) throw new Error('A courier is required to assign an order');
    return assignCourier(api, change.uuid, change.courierId);
  }
  return updateOrderStatus(api, change);
}

export interface OrderActionRequest {
  uuid: string;
  action: Pick<OrderAction, 'to' | 'via'>;
  courierId?: number;
  reason?: StaffCancelReason;
  note?: string;
}

// Performs one of the actions orders/status.ts offers on the endpoint the action names.
export async function performOrderAction(api: StaffApi, request: OrderActionRequest): Promise<Order> {
  const { uuid, action, courierId, reason, note } = request;
  if (action.via === 'complete') return completeOrder(api, uuid);
  return changeOrderStatus(api, { uuid, status: action.to, courierId, reason, note });
}
