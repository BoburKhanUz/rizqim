import type { Order, OrderStatus, StaffCancelReason } from '../types';

// Reasons staff can give, in the order they are offered (App\Enums\CancelReason::staffCases). payment_timeout is the
// system's own and is never offered.
export const STAFF_CANCEL_REASONS: readonly StaffCancelReason[] = [
  'customer_request',
  'no_show',
  'out_of_stock',
  'store_closed',
  'courier_unavailable',
  'other',
];

export const CANCEL_NOTE_MAX_LENGTH = 500;

const NO_SHOW_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready'];

// The reasons a member of staff may give for cancelling `order`, as the bot offers them (and the API accepts them):
// no_show only for a pickup order the store accepted, is preparing or has ready that the customer never came for, and
// courier_unavailable only for a delivery order. The backend checks the same rules; this keeps the picker short.
export function getStaffCancelReasons(order: Pick<Order, 'status' | 'order_type'>): StaffCancelReason[] {
  const canBeNoShow = order.order_type === 'pickup' && NO_SHOW_STATUSES.includes(order.status);
  const needsCourier = order.order_type === 'delivery';
  return STAFF_CANCEL_REASONS.filter(
    (reason) => (reason !== 'no_show' || canBeNoShow) && (reason !== 'courier_unavailable' || needsCourier),
  );
}
