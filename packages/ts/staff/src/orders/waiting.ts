import type { StaffOrder } from '../api/board';

// How long an order has waited, and how worrying that is: one rule for the partner web board and the Mini App's staff
// screens (the kitchen display has its own, configurable per terminal). Display only: nothing here changes an order.

export type WaitTone = 'calm' | 'warning' | 'late';

// Minutes after which the waiting time turns amber (warning) and red (late)
export const WAITING_THRESHOLDS = {
  // A new order should be accepted within minutes
  new: { warning: 5, late: 10 },
  // An accepted one (and the stopped clock of one that is ready or on its way) should be ready within half an hour
  kitchen: { warning: 20, late: 40 },
} as const;

// The statuses whose clock is still running (it stops once the order is ready)
export const WAITING_RUNNING_STATUSES: readonly string[] = ['new', 'accepted', 'preparing'];

type WaitingFields = Pick<StaffOrder, 'status' | 'created_at' | 'accepted_at' | 'waiting_seconds'>;

// Seconds waited, by the server's rule (`waiting_seconds`, the same as the kitchen display's): from the acceptance (the
// creation while the order is new), counting while it is new or in the kitchen and stopped once it was ready. A running
// clock ticks on from the server's start time between two refreshes; a stopped one is the server's number.
export function waitingSecondsOf(order: WaitingFields, now: number): number {
  const since = Date.parse(order.accepted_at ?? order.created_at ?? '');
  const ticking = WAITING_RUNNING_STATUSES.includes(order.status) && !Number.isNaN(since) ? (now - since) / 1000 : 0;
  return Math.max(0, order.waiting_seconds ?? 0, ticking);
}

export function waitingToneOf(status: string, minutes: number): WaitTone {
  const { warning, late } = status === 'new' ? WAITING_THRESHOLDS.new : WAITING_THRESHOLDS.kitchen;
  return minutes >= late ? 'late' : minutes >= warning ? 'warning' : 'calm';
}

// Whole minutes waited and their tone
export function getOrderWaiting(order: WaitingFields, now: number): { minutes: number; tone: WaitTone } {
  const minutes = Math.floor(waitingSecondsOf(order, now) / 60);
  return { minutes, tone: waitingToneOf(order.status, minutes) };
}
