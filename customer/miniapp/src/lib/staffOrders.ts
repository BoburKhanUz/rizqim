import { formatLineQuantity, getItemModifiers, formatModifiers, type Language, type MenuUnit, type OrderStatus, type OrderType, type StaffOrder, type StaffOrderItem } from '@rizqim/staff';

// How an order of GET /staff/orders is read on the phone. The API decides everything (status, kitchen state, totals); these
// only turn what it sent into what a screen prints.

// The two fields getOrderActions() and the cancel reasons read, from the staff shape (`type` there, `order_type` in Order).
export function toOrderRef(order: Pick<StaffOrder, 'status' | 'type' | 'short_code'>): { status: OrderStatus; order_type: OrderType; short_code: string | null } {
  return { status: order.status as OrderStatus, order_type: order.type as OrderType, short_code: order.short_code };
}

// "x2" for pieces, "1,5 kg" for an item sold by weight (the same words in every list)
export function lineQuantity(item: Pick<StaffOrderItem, 'quantity' | 'unit'>, language: Language): string {
  return formatLineQuantity(item.quantity, item.unit as MenuUnit, language);
}

// "Sous: Pishloqli, Zaytun × 2" per modifier group is not sent in one piece: the modifiers of a line as one phrase.
export function lineModifiers(item: Pick<StaffOrderItem, 'modifiers'>): string {
  return formatModifiers(getItemModifiers(item as never));
}

export function isLineReady(item: Pick<StaffOrderItem, 'kitchen_status'>): boolean {
  return item.kitchen_status === 'ready';
}

// The kitchen's progress on an order: lines marked ready of all lines. null when the order has no lines.
export function kitchenProgress(order: Pick<StaffOrder, 'items_total' | 'items_ready'>): { ready: number; total: number } | null {
  return order.items_total > 0 ? { ready: order.items_ready, total: order.items_total } : null;
}

// The orders the kitchen is cooking: their lines carry the "ready" state worth showing
export const KITCHEN_STATUSES: readonly string[] = ['accepted', 'preparing'];

// Statuses in which a customer (or the courier) is still waiting for the food: the waiting time is shown for these
const WAITING_STATUSES: readonly string[] = ['new', 'accepted', 'preparing'];

export function isWaiting(order: Pick<StaffOrder, 'status'>): boolean {
  return WAITING_STATUSES.includes(order.status);
}

// Whole minutes the order has been waiting: from its acceptance (from its creation until it is accepted) to now.
// null when the order is not waiting any more or has no timestamp.
export function waitingMinutes(order: Pick<StaffOrder, 'status' | 'accepted_at' | 'created_at'>, now: number): number | null {
  if (!isWaiting(order)) return null;
  const since = order.accepted_at ?? order.created_at;
  if (!since) return null;
  const started = new Date(since).getTime();
  if (Number.isNaN(started)) return null;
  return Math.max(0, Math.floor((now - started) / 60_000));
}

// How the waiting time is coloured (a display aid for the kitchen line, like the kitchen screen's; the order itself is
// never touched): calm, then warning, then danger.
export const WAITING_WARNING_MINUTES = 15;
export const WAITING_DANGER_MINUTES = 30;

export function waitingTone(minutes: number): 'neutral' | 'warning' | 'danger' {
  if (minutes >= WAITING_DANGER_MINUTES) return 'danger';
  if (minutes >= WAITING_WARNING_MINUTES) return 'warning';
  return 'neutral';
}

// "12 daq" / "1 soat 5 daq" / "12 мин" / "1 ч 5 мин"
export function formatWaiting(minutes: number, language: Language): string {
  const unit = language === 'ru' || language === 'en' ? { h: 'ч', m: 'мин' } : { h: 'soat', m: 'daq' };
  if (minutes < 60) return `${minutes} ${unit.m}`;
  const rest = minutes % 60;
  return rest === 0 ? `${Math.floor(minutes / 60)} ${unit.h}` : `${Math.floor(minutes / 60)} ${unit.h} ${rest} ${unit.m}`;
}
