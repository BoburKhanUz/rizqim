import { describe, expect, it } from 'vitest';
import { getAllowedPaymentMethods, getAvailableOrderTypes, pickOrderType, pickPaymentMethod } from './checkout';
import type { CartPaymentMethod } from './checkout';
import type { PaymentSettings } from '../types';

// payment_settings is a JSON column: whatever the owner's form (or an older version of it) saved comes out of the API as is
const settings = (value: unknown) => value as PaymentSettings;

describe('getAllowedPaymentMethods: what the server takes at checkout', () => {
  it('offers cash only for a store that set nothing up: an online method that is missing is off', () => {
    expect(getAllowedPaymentMethods(undefined, undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(null, undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({}), undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({ delivery_fee: 15000 }), undefined)).toEqual(['naqd']);
  });

  it('offers Click and Payme once the store switched them on', () => {
    expect(getAllowedPaymentMethods(settings({ click: { enabled: true } }), undefined)).toEqual(['naqd', 'click']);
    expect(getAllowedPaymentMethods(settings({ payme: { enabled: true } }), undefined)).toEqual(['naqd', 'payme']);
    expect(getAllowedPaymentMethods(settings({ click: { enabled: true }, payme: { enabled: true } }), undefined)).toEqual(['naqd', 'click', 'payme']);
  });

  it('does not offer a provider that is configured but switched off', () => {
    const configured = { enabled: false, merchant_id: '123', service_id: '456' };

    expect(getAllowedPaymentMethods(settings({ click: configured, payme: configured }), undefined)).toEqual(['naqd']);
  });

  it('does not offer a method the platform cannot process yet (available: false)', () => {
    expect(getAllowedPaymentMethods(settings({ click: { enabled: false, available: false } }), undefined)).toEqual(['naqd']);
  });

  it('keeps cash on unless the store switched it off, in either form of the setting', () => {
    expect(getAllowedPaymentMethods(settings({ cash: { enabled: true } }), undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({ cash: { enabled: false } }), undefined)).toEqual([]);
    expect(getAllowedPaymentMethods(settings({ cash: false }), undefined)).toEqual([]);
    expect(getAllowedPaymentMethods(settings({ cash: true }), undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({ cash: { enabled: false }, click: { enabled: true } }), undefined)).toEqual(['click']);
  });

  it('reads legacy stores whose providers are bare booleans', () => {
    expect(getAllowedPaymentMethods(settings({ cash: true, click: true, payme: false }), undefined)).toEqual(['naqd', 'click']);
    expect(getAllowedPaymentMethods(settings({ click: false }), undefined)).toEqual(['naqd']);
  });

  it('reads a switch the way PHP casts it: 0/1, "0"/"1" and a section without `enabled`', () => {
    expect(getAllowedPaymentMethods(settings({ click: { enabled: 1 }, payme: { enabled: 0 } }), undefined)).toEqual(['naqd', 'click']);
    expect(getAllowedPaymentMethods(settings({ click: { enabled: '1' }, payme: { enabled: '0' } }), undefined)).toEqual(['naqd', 'click']);
    // `$section['enabled'] ?? $section`: a section holding anything but no `enabled` counts as on, an empty one as off
    expect(getAllowedPaymentMethods(settings({ click: { merchant_id: '123' }, payme: {} }), undefined)).toEqual(['naqd', 'click']);
    expect(getAllowedPaymentMethods(settings({ click: { enabled: null, merchant_id: '123' }, payme: [] }), undefined)).toEqual(['naqd', 'click']);
  });

  it('offers nothing when the store switched every method off', () => {
    expect(getAllowedPaymentMethods(settings({ cash: { enabled: false }, click: { enabled: false }, payme: { enabled: false } }), undefined)).toEqual([]);
  });

  it('never offers Uzum or Paynet, whatever the store says', () => {
    expect(getAllowedPaymentMethods(settings({ uzum: { enabled: true }, paynet: { enabled: true } }), undefined)).toEqual(['naqd']);
  });

  it('honours the list of methods the platform allows the store', () => {
    expect(getAllowedPaymentMethods(settings({ allowed_methods: ['cash'], click: { enabled: true } }), undefined)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({ allowed_methods: ['click', 'payme'], cash: { enabled: true }, click: { enabled: true } }), undefined)).toEqual(['click']);
  });

  it('adds cashback whenever the customer has some, on top of what the store takes', () => {
    expect(getAllowedPaymentMethods(settings({ click: { enabled: true } }), 5000)).toEqual(['naqd', 'click', 'keshbek']);
    expect(getAllowedPaymentMethods(settings({ cash: { enabled: false } }), 5000)).toEqual(['keshbek']);
    expect(getAllowedPaymentMethods(settings({}), 0)).toEqual(['naqd']);
    expect(getAllowedPaymentMethods(settings({}), undefined)).toEqual(['naqd']);
  });
});

describe('getAvailableOrderTypes', () => {
  it('takes both delivery and pickup from a store that never switched either off', () => {
    expect(getAvailableOrderTypes(undefined)).toEqual(['delivery', 'pickup']);
    expect(getAvailableOrderTypes(null)).toEqual(['delivery', 'pickup']);
    expect(getAvailableOrderTypes(settings({}))).toEqual(['delivery', 'pickup']);
    expect(getAvailableOrderTypes(settings({ delivery_enabled: true, pickup_enabled: true }))).toEqual(['delivery', 'pickup']);
  });

  it('drops the way the store switched off', () => {
    expect(getAvailableOrderTypes(settings({ delivery_enabled: false }))).toEqual(['pickup']);
    expect(getAvailableOrderTypes(settings({ pickup_enabled: false }))).toEqual(['delivery']);
    expect(getAvailableOrderTypes(settings({ delivery_enabled: false, pickup_enabled: true }))).toEqual(['pickup']);
  });

  it('is empty when the store takes neither', () => {
    expect(getAvailableOrderTypes(settings({ delivery_enabled: false, pickup_enabled: false }))).toEqual([]);
  });

  it('reads a switch the way PHP casts it: a missing or null one is on, 0 is off', () => {
    expect(getAvailableOrderTypes(settings({ delivery_enabled: null, pickup_enabled: 0 }))).toEqual(['delivery']);
    expect(getAvailableOrderTypes(settings({ delivery_enabled: 0, pickup_enabled: 1 }))).toEqual(['pickup']);
  });
});

describe('pickPaymentMethod', () => {
  const ALL: CartPaymentMethod[] = ['naqd', 'click', 'payme', 'keshbek'];

  it("keeps the customer's own pick while it is on offer", () => {
    expect(pickPaymentMethod(['naqd', 'click', 'payme'], 'payme', false)).toBe('payme');
    expect(pickPaymentMethod(['naqd', 'click', 'payme'], 'naqd', false)).toBe('naqd');
  });

  it('gives a customer who has not picked yet Click, but only when the store takes it', () => {
    expect(pickPaymentMethod(['naqd', 'click', 'payme'], null, false)).toBe('click');
    expect(pickPaymentMethod(['naqd', 'payme'], null, false)).toBe('naqd');
    expect(pickPaymentMethod(['payme'], null, false)).toBe('payme');
  });

  it('never selects a method that is not on offer: Click is not preselected for a store without it', () => {
    expect(pickPaymentMethod(['naqd'], null, false)).toBe('naqd');
    expect(pickPaymentMethod(['naqd'], 'click', false)).toBe('naqd');
  });

  it('falls back to the first method on offer when the pick is gone', () => {
    expect(pickPaymentMethod(['naqd', 'click'], 'payme', false)).toBe('naqd');
    expect(pickPaymentMethod(['click', 'payme'], 'naqd', false)).toBe('click');
  });

  it('uses cashback only when it covers the whole order', () => {
    expect(pickPaymentMethod(ALL, 'keshbek', true)).toBe('keshbek');
    expect(pickPaymentMethod(ALL, 'keshbek', false)).toBe('naqd'); // the cart grew past the balance: the first one left
    expect(pickPaymentMethod(['keshbek'], 'keshbek', false)).toBeNull();
    expect(pickPaymentMethod(['keshbek'], null, true)).toBe('keshbek');
  });

  it('is null when there is nothing to pay with', () => {
    expect(pickPaymentMethod([], null, false)).toBeNull();
    expect(pickPaymentMethod([], 'click', true)).toBeNull();
  });
});

describe('pickOrderType', () => {
  it("keeps the customer's pick while the store takes it", () => {
    expect(pickOrderType(['delivery', 'pickup'], 'delivery')).toBe('delivery');
    expect(pickOrderType(['delivery', 'pickup'], 'pickup')).toBe('pickup');
  });

  it('moves to the way the store does take when the pick is switched off', () => {
    expect(pickOrderType(['pickup'], 'delivery')).toBe('pickup');
    expect(pickOrderType(['delivery'], 'pickup')).toBe('delivery');
  });

  it('is null when the store takes neither', () => {
    expect(pickOrderType([], 'delivery')).toBeNull();
  });
});
