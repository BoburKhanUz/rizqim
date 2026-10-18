import { describe, expect, it } from 'vitest';
import { makeOrder, makeOrderItem, makeUser } from '../test/factories';
import { computeOrderAnalytics, isInTimeframe } from './analytics';
import type { Order, OrderStatus } from '../types';

// Wednesday 2026-10-14 15:00 local time. The tests build timestamps relative to it, so they hold in any time zone.
const NOW = new Date(2026, 9, 14, 15, 0, 0).getTime();
const HOUR = 60 * 60 * 1000;
const DAY = 24 * HOUR;
const ago = (ms: number) => new Date(NOW - ms).toISOString();

describe('isInTimeframe', () => {
  it('"today" is the calendar day of now, not the last 24 hours', () => {
    expect(isInTimeframe(new Date(2026, 9, 14, 0, 5).toISOString(), 'today', NOW)).toBe(true);
    expect(isInTimeframe(new Date(2026, 9, 14, 23, 59).toISOString(), 'today', NOW)).toBe(true);
    expect(isInTimeframe(new Date(2026, 9, 13, 23, 59).toISOString(), 'today', NOW)).toBe(false);
    expect(isInTimeframe(new Date(2026, 9, 15, 0, 1).toISOString(), 'today', NOW)).toBe(false);
  });

  it('weekly, monthly and yearly look back 7, 30 and 365 days from now', () => {
    expect(isInTimeframe(ago(7 * DAY - HOUR), 'weekly', NOW)).toBe(true);
    expect(isInTimeframe(ago(7 * DAY + HOUR), 'weekly', NOW)).toBe(false);
    expect(isInTimeframe(ago(29 * DAY), 'monthly', NOW)).toBe(true);
    expect(isInTimeframe(ago(31 * DAY), 'monthly', NOW)).toBe(false);
    expect(isInTimeframe(ago(364 * DAY), 'yearly', NOW)).toBe(true);
    expect(isInTimeframe(ago(366 * DAY), 'yearly', NOW)).toBe(false);
  });

  it('"custom" covers both end days completely', () => {
    const range = { start: '2026-10-10', end: '2026-10-12' };

    expect(isInTimeframe(new Date(2026, 9, 10, 0, 0, 1).toISOString(), 'custom', NOW, range)).toBe(true);
    expect(isInTimeframe(new Date(2026, 9, 12, 23, 59, 58).toISOString(), 'custom', NOW, range)).toBe(true);
    expect(isInTimeframe(new Date(2026, 9, 9, 23, 59).toISOString(), 'custom', NOW, range)).toBe(false);
    expect(isInTimeframe(new Date(2026, 9, 13, 0, 1).toISOString(), 'custom', NOW, range)).toBe(false);
  });

  it('"custom" without a range does not filter', () => {
    expect(isInTimeframe(ago(500 * DAY), 'custom', NOW)).toBe(true);
  });

  it('is pure: the same answer for the same now, whatever the real clock says', () => {
    const timestamp = ago(2 * HOUR);
    expect(isInTimeframe(timestamp, 'today', NOW)).toBe(isInTimeframe(timestamp, 'today', NOW));
  });
});

function order(status: OrderStatus, overrides: Partial<Order> = {}): Order {
  return makeOrder({ status, updated_at: ago(HOUR), ...overrides });
}

describe('computeOrderAnalytics', () => {
  it('counts only finished orders of the timeframe', () => {
    const orders = [
      order('delivered', { id: 1 }),
      order('canceled', { id: 2 }),
      order('preparing', { id: 3 }), // still open
      order('new', { id: 4 }),
      order('delivered', { id: 5, updated_at: ago(3 * DAY) }), // not today
    ];

    const today = computeOrderAnalytics(orders, 'today', NOW);
    expect(today.periodOrders.map((o) => o.id)).toEqual([1, 2]);
    expect(today.deliveredOrders.map((o) => o.id)).toEqual([1]);

    const weekly = computeOrderAnalytics(orders, 'weekly', NOW);
    expect(weekly.periodOrders.map((o) => o.id)).toEqual([1, 2, 5]);
  });

  it('adds up the revenue of delivered orders and splits cash from online payments', () => {
    const orders = [
      order('delivered', { id: 1, total_price: '30000.00', payment_method: 'cash' }),
      order('delivered', { id: 2, total_price: '45000.50', payment_method: 'click' }),
      order('delivered', { id: 3, total_price: 20000, payment_method: 'payme' }),
      order('canceled', { id: 4, total_price: '99999.00', payment_method: 'cash' }),
    ];

    const result = computeOrderAnalytics(orders, 'today', NOW);

    expect(result.totalRevenue).toBeCloseTo(95000.5);
    expect(result.cashRevenue).toBe(30000);
    expect(result.onlineRevenue).toBeCloseTo(65000.5);
  });

  it('is empty and null-safe without orders', () => {
    expect(computeOrderAnalytics([], 'today', NOW)).toEqual({
      periodOrders: [],
      deliveredOrders: [],
      totalRevenue: 0,
      cashRevenue: 0,
      onlineRevenue: 0,
      averagePrepMs: null,
      averageDeliveryMs: null,
      topProducts: [],
      topCouriers: [],
    });
  });

  it('averages preparation (accepted -> preparing) and delivery (delivery or preparing -> delivered) times', () => {
    const at = (minutesAgo: number) => ago(minutesAgo * 60 * 1000);
    const history = (status: OrderStatus, minutesAgo: number) => ({
      id: 0, order_id: 1, user_id: null, status, notes: null, created_at: at(minutesAgo), updated_at: at(minutesAgo),
    });
    const orders = [
      // prep 4 min, delivery 30 min (from "delivery")
      order('delivered', {
        id: 1,
        status_histories: [history('accepted', 60), history('preparing', 56), history('delivery', 40), history('delivered', 10)],
      }),
      // prep 10 min, delivery 20 min (no "delivery" status: counted from "preparing")
      order('delivered', {
        id: 2,
        status_histories: [history('accepted', 50), history('preparing', 40), history('delivered', 20)],
      }),
      // no history: ignored
      order('delivered', { id: 3 }),
    ];

    const result = computeOrderAnalytics(orders, 'today', NOW);

    expect(result.averagePrepMs).toBe(7 * 60 * 1000);
    expect(result.averageDeliveryMs).toBe(25 * 60 * 1000);
  });

  it('ranks the three best-selling products and couriers of delivered orders', () => {
    const items = (...entries: [string, number][]) => entries.map(([product_name, quantity], i) => makeOrderItem({ id: i, product_name, quantity }));
    const ali = makeUser({ id: 10, name: 'Ali' });
    const vali = makeUser({ id: 11, name: 'Vali' });
    const orders = [
      order('delivered', { id: 1, courier: ali, items: items(['Lavash', 3], ['Cola', 1]) }),
      order('delivered', { id: 2, courier: ali, items: items(['Lavash', 2], ['Osh', 4], ['Choy', 1]) }),
      order('delivered', { id: 3, courier: vali, items: items(['Cola', 5]) }),
      order('canceled', { id: 4, courier: vali, items: items(['Pitsa', 99]) }), // canceled: not counted
      order('delivered', { id: 5, items: items(['', 1]) }), // unnamed product (counted as "Burger"), no courier
    ];

    const result = computeOrderAnalytics(orders, 'today', NOW);

    expect(result.topProducts).toEqual([
      { name: 'Cola', count: 6 },
      { name: 'Lavash', count: 5 },
      { name: 'Osh', count: 4 },
    ]);
    expect(result.topCouriers).toEqual([
      { name: 'Ali', count: 2 },
      { name: 'Vali', count: 1 },
    ]);
  });
});
