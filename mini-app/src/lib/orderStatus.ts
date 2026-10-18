import type { OrderStatus } from '../types';

// Translation key of a status label (store/languageStore.ts has one per OrderStatus in every language).
export type OrderStatusLabelKey = `status_${OrderStatus}`;

export interface OrderStatusLook {
  labelKey: OrderStatusLabelKey | null; // null: a status this app does not know, shown as the API sent it
  badge: string; // background, text and border colours
}

// Colours of the status badge of the customer's order list. One entry per App\Enums\OrderStatus value, so no
// status borrows another one's look (`Record` makes the compiler ask for an entry when the enum grows).
// on_the_way and delivery are two names for the same stage and look the same.
const BADGE: Record<OrderStatus, string> = {
  new: 'bg-amber-500/10 text-amber-500 border border-amber-500/20',
  accepted: 'bg-blue-500/10 text-blue-500 border border-blue-500/20',
  preparing: 'bg-purple-500/10 text-purple-500 border border-purple-500/20 animate-pulse',
  ready: 'bg-teal-500/10 text-teal-500 border border-teal-500/20',
  courier_assigned: 'bg-indigo-500/10 text-indigo-500 border border-indigo-500/20',
  on_the_way: 'bg-orange-500/10 text-orange-500 border border-orange-500/20',
  delivery: 'bg-orange-500/10 text-orange-500 border border-orange-500/20',
  delivered: 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20',
  canceled: 'bg-red-500/10 text-red-500 border border-red-500/20',
  rejected: 'bg-rose-500/10 text-rose-500 border border-rose-500/20',
  refunded: 'bg-sky-500/10 text-sky-500 border border-sky-500/20',
};

const UNKNOWN_BADGE = 'bg-slate-500/10 text-slate-500 border border-slate-500/20';

const isOrderStatus = (status: string): status is OrderStatus => Object.hasOwn(BADGE, status);

// How to show `status`. Never another status' look: one the app has no entry for (the backend got a new one)
// is neutral and keeps its own name.
export function getOrderStatusLook(status: string): OrderStatusLook {
  if (!isOrderStatus(status)) return { labelKey: null, badge: UNKNOWN_BADGE };
  return { labelKey: `status_${status}`, badge: BADGE[status] };
}
