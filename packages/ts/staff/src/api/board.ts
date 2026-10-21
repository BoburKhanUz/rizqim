import type { operations } from '@rizqim/api-client';
import { getOrderActions, type OrderAction, type OrderActionId } from '../orders/status';
import type { OrderStatus, OrderType, StaffCancelReason } from '../types';
import type { StaffApi } from './client';
import { unwrap } from './errors';
import { assignCourier, performOrderAction } from './orders';
import type { JsonRequest, OkData } from './types';

// P2-21: the live orders board of the partner web (and any staff screen that works with a branch's orders):
// GET /staff/orders (filters, keyset cursor), GET /staff/orders/{uuid} (the detail), POST /staff/orders/{uuid}/status
// (the role-checked status change, docs/API.md §9.1). The shapes are the generated ones.

// An order as GET /staff/orders lists it (customer, delivery and courier are null without orders.manage)
export type StaffOrder = OkData<'staffOrder.index'>[number];
export type StaffOrderItem = StaffOrder['items'][number];
// An order as GET /staff/orders/{uuid} sends it: the list fields plus the status history
export type StaffOrderDetail = OkData<'staffOrder.show'>;
export type StaffOrderHistoryEntry = StaffOrderDetail['status_history'][number];

// The filters of GET /staff/orders (every one optional; send the same ones on every page)
export type StaffOrdersQuery = Omit<NonNullable<operations['staffOrder.index']['parameters']['query']>, 'cursor'>;

export const STAFF_ORDERS_MAX_PAGE_SIZE = 100;
// How many pages fetchAllStaffOrders follows before it stops and says `truncated`
export const STAFF_ORDERS_MAX_PAGES = 5;

export interface StaffOrdersPage {
  orders: StaffOrder[];
  nextCursor: string | null;
}

export async function fetchStaffOrders(api: StaffApi, query: StaffOrdersQuery = {}, cursor: string | null = null): Promise<StaffOrdersPage> {
  const perPage = query.per_page === undefined ? undefined : Math.min(Math.max(1, query.per_page), STAFF_ORDERS_MAX_PAGE_SIZE);
  const body = unwrap(
    await api.client.GET('/v1/staff/orders', {
      params: { query: { ...query, ...(perPage === undefined ? {} : { per_page: perPage }), ...(cursor ? { cursor } : {}) } },
    }),
  );
  return { orders: body.data ?? [], nextCursor: body.meta?.next_cursor ?? null };
}

export interface StaffOrdersList {
  orders: StaffOrder[];
  // There were more pages than `maxPages`
  truncated: boolean;
}

// Every page of a filter, newest first (a board's open orders): follows `next_cursor` up to `maxPages` pages.
export async function fetchAllStaffOrders(
  api: StaffApi,
  query: StaffOrdersQuery = {},
  maxPages: number = STAFF_ORDERS_MAX_PAGES,
): Promise<StaffOrdersList> {
  const pageQuery = { per_page: STAFF_ORDERS_MAX_PAGE_SIZE, ...query };
  const orders: StaffOrder[] = [];
  const seen = new Set<string>();
  let cursor: string | null = null;
  for (let page = 0; page < Math.max(1, maxPages); page++) {
    const result: StaffOrdersPage = await fetchStaffOrders(api, pageQuery, cursor);
    for (const order of result.orders) {
      // An order that changed between two pages can come twice
      if (!seen.has(order.uuid)) {
        seen.add(order.uuid);
        orders.push(order);
      }
    }
    cursor = result.nextCursor;
    if (!cursor) return { orders, truncated: false };
  }
  return { orders, truncated: true };
}

export async function fetchStaffOrder(api: StaffApi, uuid: string): Promise<StaffOrderDetail> {
  const body = unwrap(await api.client.GET('/v1/staff/orders/{uuid}', { params: { path: { uuid } } }));
  return body.data;
}

type StaffStatusBody = JsonRequest<'staffOrder.updateStatus'>;
// The statuses POST /staff/orders/{uuid}/status takes (the role decides which of them a person may set)
export type StaffStatusTarget = StaffStatusBody['status'];
export const STAFF_STATUS_TARGETS: readonly StaffStatusTarget[] = ['accepted', 'preparing', 'ready', 'delivered', 'canceled', 'rejected'];
export const isStaffStatusTarget = (status: string): status is StaffStatusTarget => (STAFF_STATUS_TARGETS as readonly string[]).includes(status);

export interface StaffStatusChange {
  uuid: string;
  status: StaffStatusTarget;
  reason?: StaffCancelReason; // required by the API for canceled and rejected
  note?: string;
}

export async function updateStaffOrderStatus(api: StaffApi, change: StaffStatusChange): Promise<StaffOrder> {
  const cancellation = change.status === 'canceled' || change.status === 'rejected';
  const payload: StaffStatusBody = {
    status: change.status,
    ...(cancellation ? { reason: change.reason ?? null, ...(change.note ? { note: change.note } : {}) } : {}),
  };
  const body = unwrap(await api.client.POST('/v1/staff/orders/{uuid}/status', { params: { path: { uuid: change.uuid } }, body: payload }));
  return body.data;
}

// --- The buttons of a board card ------------------------------------------------------------------------------------

// Which permission each button needs (docs/API.md §9.1): only to HIDE buttons the backend would refuse anyway (it checks
// every request itself and answers 403/422; the screen shows that). Any one of the listed permissions is enough.
export const BOARD_ACTION_PERMISSIONS: Record<OrderActionId, readonly string[]> = {
  accept: ['orders.manage'],
  reject: ['orders.cancel'],
  start_preparing: ['orders.manage', 'kitchen.manage'],
  mark_ready: ['orders.manage', 'kitchen.manage'],
  assign_courier: ['orders.assign_courier'],
  hand_over: ['orders.manage'],
  // courier_assigned → on_the_way is only on POST /orders/{uuid}/status (owner, manager)
  pick_up: ['orders.assign_courier'],
  confirm_delivery: ['orders.manage'],
  cancel: ['orders.cancel'],
};

// The next actions of a board card for someone holding `permissions`: the staff screens' actions (orders/status.ts) less
// the ones their permissions do not open.
export function getBoardActions(order: { status: string; type: string }, permissions: readonly string[]): OrderAction[] {
  const actions = getOrderActions({ status: order.status as OrderStatus, order_type: order.type as OrderType }, 'manager');
  return actions.filter((action) => BOARD_ACTION_PERMISSIONS[action.id].some((permission) => permissions.includes(permission)));
}

export interface BoardActionRequest {
  uuid: string;
  action: Pick<OrderAction, 'to' | 'via'>;
  courierId?: number;
  reason?: StaffCancelReason;
  note?: string;
}

// Performs a board action on the endpoint that takes it: the staff status endpoint for what it accepts (any role with
// the permission), assign-courier for a courier, the older POST /orders/{uuid}/status for the rest (on_the_way).
export async function performBoardAction(api: StaffApi, request: BoardActionRequest): Promise<{ uuid: string; status: string }> {
  const { uuid, action, courierId, reason, note } = request;
  if (action.via === 'assign-courier') {
    if (courierId === undefined) throw new Error('A courier is required to assign an order');
    return assignCourier(api, uuid, courierId);
  }
  if (action.via === 'status' && isStaffStatusTarget(action.to)) return updateStaffOrderStatus(api, { uuid, status: action.to, reason, note });
  return performOrderAction(api, request);
}

// --- Branches (the branch picker of a staff screen) -----------------------------------------------------------------

// GET /owner/branches: the store's branches (a branch-bound caller gets only their own)
export type BranchListItem = OkData<'branch.index'>[number];

export async function fetchBranchList(api: StaffApi): Promise<BranchListItem[]> {
  const body = unwrap(await api.client.GET('/v1/owner/branches'));
  return body.data ?? [];
}
