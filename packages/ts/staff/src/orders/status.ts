import type { StatusTone } from '@rizqim/ui';
import type { Order, OrderStatus } from '../types';

// Order statuses as DATA the screens read. The backend is the authority on what an order may do next (the state
// machine of App\Enums\OrderStatus / OrderStatusTransitions, the role permissions): this is display logic only, the
// words and colours of a status and which buttons a screen offers. A button the backend then refuses answers 403/422
// and the screen shows its message.

// Every App\Enums\OrderStatus value, in the order of a normal order's life. `Record` below makes the compiler ask for an
// entry in every table when the enum grows.
export const ORDER_STATUSES: readonly OrderStatus[] = [
  'new',
  'accepted',
  'preparing',
  'ready',
  'courier_assigned',
  'on_the_way',
  'delivery', // legacy alias of on_the_way
  'delivered',
  'canceled',
  'rejected',
  'refunded',
];

export const isOrderStatus = (value: unknown): value is OrderStatus => typeof value === 'string' && (ORDER_STATUSES as readonly string[]).includes(value);

// Statuses of an order that is still in progress (what GET /orders?scope=active returns) and of one that has finished
// (scope=history). A status this package does not know counts as neither.
export const OPEN_STATUSES: readonly OrderStatus[] = ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'];
export const FINISHED_STATUSES: readonly OrderStatus[] = ['delivered', 'canceled', 'rejected', 'refunded'];

export const isOpenStatus = (status: string): boolean => (OPEN_STATUSES as readonly string[]).includes(status);
export const isFinishedStatus = (status: string): boolean => (FINISHED_STATUSES as readonly string[]).includes(status);

// The colour group of each status (docs/DESIGN.md, "Holat ranglari"): warning = waiting for the store, info = in
// progress, success = delivered, danger = canceled or rejected, neutral = refunded and anything unknown.
// @rizqim/ui draws the badge from the same groups (its test in this package keeps the two equal).
export const STATUS_TONE: Record<OrderStatus, StatusTone> = {
  new: 'warning',
  accepted: 'info',
  preparing: 'info',
  ready: 'info',
  courier_assigned: 'info',
  on_the_way: 'info',
  delivery: 'info',
  delivered: 'success',
  canceled: 'danger',
  rejected: 'danger',
  refunded: 'neutral',
};

export const statusTone = (status: string): StatusTone => (isOrderStatus(status) ? STATUS_TONE[status] : 'neutral');

// The columns of a live board and the counters of a phone screen: which statuses sit together.
//   new        waiting for the store to accept
//   preparing  accepted, in the kitchen
//   ready      cooked, waiting for pickup or a courier
//   courier    a courier is assigned or on the way
//   done       delivered (or handed over)
//   canceled   canceled, rejected, refunded
export type StatusGroup = 'new' | 'preparing' | 'ready' | 'courier' | 'done' | 'canceled';

export const STATUS_GROUPS: readonly StatusGroup[] = ['new', 'preparing', 'ready', 'courier', 'done', 'canceled'];

export const GROUP_STATUSES: Record<StatusGroup, readonly OrderStatus[]> = {
  new: ['new'],
  preparing: ['accepted', 'preparing'],
  ready: ['ready'],
  courier: ['courier_assigned', 'on_the_way', 'delivery'],
  done: ['delivered'],
  canceled: ['canceled', 'rejected', 'refunded'],
};

export function statusGroup(status: string): StatusGroup | null {
  return STATUS_GROUPS.find((group) => (GROUP_STATUSES[group] as readonly string[]).includes(status)) ?? null;
}

// Orders with a courier (assigned, or already out with the order)
export const WITH_COURIER_STATUSES: readonly OrderStatus[] = GROUP_STATUSES.courier;

// Statuses from which staff can still cancel an order from the kitchen side (a new order is rejected instead)
export const CANCELABLE_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready'];

export const isCancellation = (status: OrderStatus): boolean => status === 'canceled' || status === 'rejected';

// --- What a screen may offer next ----------------------------------------------------------------------------------

// The roles of the staff screens that change an order. `owner` works like `manager` (the Mini App has one screen for
// both); `courier` only moves his own deliveries.
export type OrderActionRole = 'owner' | 'manager' | 'courier';

export type OrderActionId =
  | 'accept' // new -> accepted
  | 'reject' // new -> rejected (needs a reason)
  | 'start_preparing' // accepted -> preparing
  | 'mark_ready' // preparing -> ready
  | 'assign_courier' // ready -> courier_assigned (needs a courier)
  | 'hand_over' // ready -> delivered, for a pickup or dine-in order
  | 'pick_up' // courier_assigned -> on_the_way
  | 'confirm_delivery' // on_the_way -> delivered
  | 'cancel'; // accepted / preparing / ready -> canceled (needs a reason)

export interface OrderAction {
  id: OrderActionId;
  // The status the order moves to
  to: OrderStatus;
  // The backend wants a cancel reason with it (POST /orders/{uuid}/status `reason`)
  needsReason: boolean;
  // The backend wants a courier id with it (POST /orders/{uuid}/assign-courier)
  needsCourier: boolean;
  // Which endpoint carries it: POST /orders/{uuid}/status, .../assign-courier or .../complete (the courier's own)
  via: 'status' | 'assign-courier' | 'complete';
  // The way out of an order rather than the next step: screens draw it apart (a red button)
  destructive: boolean;
}

const action = (id: OrderActionId, to: OrderStatus, flags: Partial<Omit<OrderAction, 'id' | 'to'>> = {}): OrderAction => ({
  id,
  to,
  needsReason: flags.needsReason ?? false,
  needsCourier: flags.needsCourier ?? false,
  via: flags.via ?? 'status',
  destructive: flags.destructive ?? false,
});

const CANCEL = action('cancel', 'canceled', { needsReason: true, destructive: true });

// The buttons a manager (or the owner) sees on an order, the next step first and the way out last.
function managerActions(order: Pick<Order, 'status' | 'order_type'>): OrderAction[] {
  const delivery = order.order_type === 'delivery';
  switch (order.status) {
    case 'new':
      return [action('accept', 'accepted'), action('reject', 'rejected', { needsReason: true, destructive: true })];
    case 'accepted':
      return [action('start_preparing', 'preparing'), CANCEL];
    case 'preparing':
      return [action('mark_ready', 'ready'), CANCEL];
    case 'ready':
      return [delivery ? action('assign_courier', 'courier_assigned', { needsCourier: true, via: 'assign-courier' }) : action('hand_over', 'delivered'), CANCEL];
    case 'courier_assigned':
      return [action('pick_up', 'on_the_way')];
    case 'on_the_way':
    case 'delivery':
      return [action('confirm_delivery', 'delivered')];
    default:
      return [];
  }
}

// A courier picks the order up, then hands it to the customer.
function courierActions(order: Pick<Order, 'status'>): OrderAction[] {
  switch (order.status) {
    case 'courier_assigned':
      return [action('pick_up', 'on_the_way')];
    case 'on_the_way':
    case 'delivery':
      return [action('confirm_delivery', 'delivered', { via: 'complete' })];
    default:
      return [];
  }
}

// The next actions the screen of `role` offers on `order`. An empty list for a finished order and for a role this
// package has no screen for (the kitchen and cashier roles come with the kitchen display).
export function getOrderActions(order: Pick<Order, 'status' | 'order_type'>, role: OrderActionRole | string): OrderAction[] {
  if (role === 'owner' || role === 'manager') return managerActions(order);
  if (role === 'courier') return courierActions(order);
  return [];
}
