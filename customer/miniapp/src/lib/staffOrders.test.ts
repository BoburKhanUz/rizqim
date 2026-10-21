import { describe, expect, it } from 'vitest';
import { WAITING_THRESHOLDS } from '@rizqim/staff';
import { makeStaffItem, makeStaffOrder } from '../test/staffOrdersApi';
import {
  formatWaiting,
  isLineReady,
  isWaiting,
  kitchenProgress,
  lineModifiers,
  lineQuantity,
  toOrderRef,
  waitingMinutes,
  waitingTone,
} from './staffOrders';

const NOW = Date.parse('2026-10-14T10:00:00.000Z');
const ago = (minutes: number) => new Date(NOW - minutes * 60_000).toISOString();

describe('lineQuantity', () => {
  it('writes pieces as x2 and a weight with its unit and a decimal comma', () => {
    expect(lineQuantity({ quantity: 2, unit: 'piece' }, 'uz')).toBe('x2');
    expect(lineQuantity({ quantity: 1.5, unit: 'kg' }, 'uz')).toMatch(/^1,5\skg$/);
    expect(lineQuantity({ quantity: 0.25, unit: 'l' }, 'ru')).toMatch(/^0,25\sл$/);
    expect(lineQuantity({ quantity: 1.5, unit: 'kg' }, 'en')).toMatch(/^1\.5\skg$/);
  });
});

describe('lineModifiers', () => {
  it('lists the modifiers of a line, with a quantity when it is more than one', () => {
    const item = makeStaffItem({ modifiers: [{ name: 'Pishloqli', quantity: 1 }, { name: 'Zaytun', quantity: 2 }] });
    expect(lineModifiers(item)).toBe('Pishloqli, Zaytun × 2');
  });

  it('is empty for a line without modifiers', () => {
    expect(lineModifiers(makeStaffItem())).toBe('');
  });
});

describe('the kitchen', () => {
  it('knows a ready line', () => {
    expect(isLineReady(makeStaffItem({ kitchen_status: 'ready' }))).toBe(true);
    expect(isLineReady(makeStaffItem({ kitchen_status: 'pending' }))).toBe(false);
  });

  it('reports the progress of the lines, or none for an order without lines', () => {
    expect(kitchenProgress({ items_total: 3, items_ready: 2 })).toEqual({ ready: 2, total: 3 });
    expect(kitchenProgress({ items_total: 0, items_ready: 0 })).toBeNull();
  });
});

describe('toOrderRef', () => {
  it('reads the staff order\'s type as the order type the package knows', () => {
    expect(toOrderRef(makeStaffOrder({ status: 'ready', type: 'pickup', short_code: 'P1' }))).toEqual({ status: 'ready', order_type: 'pickup', short_code: 'P1' });
  });
});

describe('waitingMinutes', () => {
  it('counts from the acceptance, and from the creation until the order is accepted', () => {
    expect(waitingMinutes({ status: 'preparing', accepted_at: ago(7), created_at: ago(20) }, NOW)).toBe(7);
    expect(waitingMinutes({ status: 'new', accepted_at: null, created_at: ago(3) }, NOW)).toBe(3);
  });

  it('takes the server\'s waiting_seconds when it is ahead of the local clock', () => {
    expect(waitingMinutes({ status: 'preparing', accepted_at: ago(2), created_at: ago(20), waiting_seconds: 9 * 60 }, NOW)).toBe(9);
  });

  it('is whole minutes and never negative', () => {
    expect(waitingMinutes({ status: 'accepted', accepted_at: new Date(NOW - 119_000).toISOString(), created_at: ago(5) }, NOW)).toBe(1);
    expect(waitingMinutes({ status: 'accepted', accepted_at: new Date(NOW + 60_000).toISOString(), created_at: ago(5) }, NOW)).toBe(0);
  });

  it('is null once the food is made, and without a usable time', () => {
    for (const status of ['ready', 'courier_assigned', 'on_the_way', 'delivered', 'canceled']) {
      expect(waitingMinutes({ status, accepted_at: ago(9), created_at: ago(9) }, NOW)).toBeNull();
      expect(isWaiting({ status })).toBe(false);
    }
    expect(waitingMinutes({ status: 'new', accepted_at: null, created_at: null }, NOW)).toBeNull();
    expect(waitingMinutes({ status: 'new', accepted_at: null, created_at: 'not a date' }, NOW)).toBeNull();
  });
});

describe('waitingTone', () => {
  it('is calm, then warning, then danger, sooner for a new order than for one in the kitchen (the web board\'s thresholds)', () => {
    expect([0, 4, 5, 9, 10].map((minutes) => waitingTone(minutes, 'new'))).toEqual(['neutral', 'neutral', 'warning', 'warning', 'danger']);
    expect([0, WAITING_THRESHOLDS.kitchen.warning - 1, WAITING_THRESHOLDS.kitchen.warning, WAITING_THRESHOLDS.kitchen.late].map((minutes) => waitingTone(minutes, 'preparing'))).toEqual([
      'neutral',
      'neutral',
      'warning',
      'danger',
    ]);
  });
});

describe('formatWaiting', () => {
  it.each([
    [0, 'uz', '0 daq'],
    [12, 'uz', '12 daq'],
    [60, 'uz', '1 soat'],
    [75, 'uz', '1 soat 15 daq'],
    [12, 'ru', '12 мин'],
    [135, 'ru', '2 ч 15 мин'],
  ] as const)('%i min in %s is %s', (minutes, language, expected) => {
    expect(formatWaiting(minutes, language)).toBe(expected);
  });
});
