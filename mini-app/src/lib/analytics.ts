import { parsePrice } from './catalog';
import type { Order, OrderStatus } from '../types';

export type Timeframe = 'today' | 'weekly' | 'monthly' | 'yearly' | 'custom';

// Inclusive calendar days ("YYYY-MM-DD", as <input type="date"> gives them) of the 'custom' timeframe.
export interface DateRange {
  start: string;
  end: string;
}

const DAY_MS = 24 * 60 * 60 * 1000;

// Whether a timestamp falls in the timeframe, as seen from `now` (epoch ms). Passing `now` in keeps this pure:
// screens use the time of their last data refresh, so it moves on together with the data.
export function isInTimeframe(timestamp: string, timeframe: Timeframe, now: number, range?: DateRange): boolean {
  const time = new Date(timestamp).getTime();
  switch (timeframe) {
    case 'today':
      return new Date(timestamp).toDateString() === new Date(now).toDateString();
    case 'weekly':
      return time >= now - 7 * DAY_MS;
    case 'monthly':
      return time >= now - 30 * DAY_MS;
    case 'yearly':
      return time >= now - 365 * DAY_MS;
    case 'custom': {
      if (!range) return true;
      const start = new Date(range.start).setHours(0, 0, 0, 0);
      const end = new Date(range.end).setHours(23, 59, 59, 999);
      return time >= start && time <= end;
    }
  }
}

export interface RankedEntry {
  name: string;
  count: number;
}

export interface OrderAnalytics {
  periodOrders: Order[];
  deliveredOrders: Order[];
  totalRevenue: number;
  cashRevenue: number;
  onlineRevenue: number;
  averagePrepMs: number | null; // accepted -> preparing
  averageDeliveryMs: number | null; // delivery (or preparing) -> delivered
  topProducts: RankedEntry[];
  topCouriers: RankedEntry[];
}

// When each status was reached (the last time wins if a status repeats).
function statusTimes(order: Order): Partial<Record<OrderStatus, number>> {
  const times: Partial<Record<OrderStatus, number>> = {};
  for (const history of order.status_histories ?? []) {
    times[history.status] = new Date(history.created_at).getTime();
  }
  return times;
}

function average(values: number[]): number | null {
  return values.length === 0 ? null : values.reduce((sum, value) => sum + value, 0) / values.length;
}

function rank(counts: Map<string, number>): RankedEntry[] {
  return Array.from(counts, ([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 3);
}

// The owner's analytics screen: finished (delivered or canceled) orders of the timeframe, summarised.
export function computeOrderAnalytics(orders: Order[], timeframe: Timeframe, now: number): OrderAnalytics {
  const periodOrders = orders.filter(
    (order) => (order.status === 'delivered' || order.status === 'canceled') && isInTimeframe(order.updated_at, timeframe, now),
  );
  const deliveredOrders = periodOrders.filter((order) => order.status === 'delivered');

  const totalRevenue = deliveredOrders.reduce((sum, order) => sum + parsePrice(order.total_price), 0);
  const cashRevenue = deliveredOrders
    .filter((order) => order.payment_method === 'cash')
    .reduce((sum, order) => sum + parsePrice(order.total_price), 0);

  const prepDurations: number[] = [];
  const deliveryDurations: number[] = [];
  for (const order of periodOrders) {
    const times = statusTimes(order);
    const accepted = times.accepted;
    const preparing = times.preparing;
    if (accepted && preparing) prepDurations.push(preparing - accepted);

    const dispatched = times.delivery || times.preparing;
    const delivered = times.delivered;
    if (dispatched && delivered) deliveryDurations.push(delivered - dispatched);
  }

  const productCounts = new Map<string, number>();
  const courierCounts = new Map<string, number>();
  for (const order of deliveredOrders) {
    for (const item of order.items ?? []) {
      const name = item.product_name || 'Burger';
      productCounts.set(name, (productCounts.get(name) ?? 0) + item.quantity);
    }
    if (order.courier) {
      courierCounts.set(order.courier.name, (courierCounts.get(order.courier.name) ?? 0) + 1);
    }
  }

  return {
    periodOrders,
    deliveredOrders,
    totalRevenue,
    cashRevenue,
    onlineRevenue: totalRevenue - cashRevenue,
    averagePrepMs: average(prepDurations),
    averageDeliveryMs: average(deliveryDurations),
    topProducts: rank(productCounts),
    topCouriers: rank(courierCounts),
  };
}
