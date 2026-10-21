import { describe, expect, it } from 'vitest';
import { makeOrder } from '../test/helpers';
import { getStaffCancelReasons, STAFF_CANCEL_REASONS } from './cancel';
import { formatModifiers, getItemModifiers, getShippingAddress } from './display';
import { formatDuration, getStageDurations, stageRows } from './stages';

describe('staff cancel reasons', () => {
  it('never offers the system\'s own payment_timeout', () => {
    expect(STAFF_CANCEL_REASONS).not.toContain('payment_timeout');
  });

  it('offers no_show only for a pickup order the store accepted, and a missing courier only for delivery', () => {
    expect(getStaffCancelReasons({ order_type: 'delivery', status: 'accepted' })).toEqual([
      'customer_request',
      'out_of_stock',
      'store_closed',
      'courier_unavailable',
      'other',
    ]);
    expect(getStaffCancelReasons({ order_type: 'pickup', status: 'ready' })).toEqual([
      'customer_request',
      'no_show',
      'out_of_stock',
      'store_closed',
      'other',
    ]);
    expect(getStaffCancelReasons({ order_type: 'pickup', status: 'new' })).not.toContain('no_show');
    expect(getStaffCancelReasons({ order_type: 'dine_in', status: 'preparing' })).toEqual(['customer_request', 'out_of_stock', 'store_closed', 'other']);
  });
});

describe('stage durations', () => {
  const history = (status: string, at: string) => ({ id: 1, order_id: 1, user_id: null, status, notes: null, created_at: at, updated_at: at }) as never;

  it('writes the time between two moments as minutes and seconds', () => {
    expect(formatDuration(new Date('2026-10-20T10:00:00Z'), new Date('2026-10-20T10:01:03Z'))).toBe('1m 3s');
    expect(formatDuration(new Date('2026-10-20T10:00:00Z'), new Date('2026-10-20T10:00:42Z'))).toBe('42s');
    expect(formatDuration(undefined, new Date())).toBeNull();
  });

  it('times each stage of an order from its status history', () => {
    const order = makeOrder({
      status_histories: [
        history('new', '2026-10-20T10:00:00Z'),
        history('accepted', '2026-10-20T10:01:00Z'),
        history('preparing', '2026-10-20T10:03:00Z'),
        history('ready', '2026-10-20T10:13:00Z'),
        history('on_the_way', '2026-10-20T10:15:00Z'),
        history('delivered', '2026-10-20T10:35:30Z'),
      ],
    });
    expect(getStageDurations(order)).toEqual({
      acceptTime: '1m 0s',
      prepTime: '2m 0s',
      readyTime: '10m 0s',
      transitTime: '2m 0s',
      deliveryTime: '20m 30s',
    });
  });

  it('has no durations for an order without history, and skips the stages without two times', () => {
    expect(getStageDurations(makeOrder())).toBeNull();
    expect(getStageDurations(makeOrder({ status_histories: [] }))).toBeNull();
    const partial = getStageDurations(makeOrder({ status_histories: [history('new', '2026-10-20T10:00:00Z'), history('accepted', '2026-10-20T10:00:20Z')] }));
    expect(partial).toMatchObject({ acceptTime: '20s', prepTime: null, deliveryTime: null });
    expect(stageRows(partial!, 'uz')).toEqual([['Qabul qilish', '20s']]);
    expect(stageRows(partial!, 'ru')).toEqual([['Принятие', '20s']]);
  });
});

describe('the loosely typed columns of an order', () => {
  it('reads shipping_address into numbers and strings', () => {
    expect(getShippingAddress(makeOrder({ shipping_address: { address: 'Chilonzor 5', latitude: 41.3, longitude: '69.2' } }))).toEqual({
      address: 'Chilonzor 5',
      latitude: 41.3,
      longitude: 69.2,
    });
    expect(getShippingAddress(makeOrder({ shipping_address: { latitude: 'abc' } }))).toEqual({ address: null, latitude: null, longitude: null });
    expect(getShippingAddress(makeOrder())).toBeNull();
  });

  it('reads the modifiers of an order line and prints them', () => {
    const modifiers = getItemModifiers({ modifiers: [{ name: 'Pepsi', quantity: 2, group_name: 'Ichimlik', price_delta: '5000.00', total: '10000.00' }, { name: 'Pishloq' }, { nope: 1 }] });
    expect(modifiers.map((m) => [m.name, m.quantity])).toEqual([['Pepsi', 2], ['Pishloq', 1]]);
    expect(formatModifiers(modifiers)).toBe('Pepsi × 2, Pishloq');
    expect(getItemModifiers({ modifiers: null })).toEqual([]);
  });
});
