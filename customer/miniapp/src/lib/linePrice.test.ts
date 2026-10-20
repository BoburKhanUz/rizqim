import { describe, expect, it } from 'vitest';
import { lineTotal, modifiersTotal } from './linePrice';

// The rounding of docs/MENU.md, "Yaxlitlash qoidasi": exact, once, half up to whole so'm.
describe('lineTotal', () => {
  it('is price × quantity for pieces', () => {
    expect(lineTotal({ price: 25000, quantity: 3 })).toBe(75000);
  });

  it('adds the modifiers to every piece', () => {
    expect(lineTotal({ price: 50000, quantity: 2, unit: 'piece', modifiers: [{ priceDelta: 3000, quantity: 1 }] })).toBe(106000);
  });

  it('adds the modifiers once to a line sold by weight', () => {
    expect(lineTotal({ price: 120000, quantity: 1.5, unit: 'kg', modifiers: [{ priceDelta: 5000, quantity: 2 }] })).toBe(190000);
  });

  it('rounds half up, once: 33 333 × 0.5 = 16 666.5 -> 16 667', () => {
    expect(lineTotal({ price: 33333, quantity: 0.5, unit: 'kg' })).toBe(16667);
  });

  it('rounds the exact product, not the steps: 12 345.67 × 0.333 = 4 111.108 -> 4 111', () => {
    expect(lineTotal({ price: 12345.67, quantity: 0.333, unit: 'kg' })).toBe(4111);
  });

  it('is not fooled by float noise (0.1 + 0.2 kg)', () => {
    expect(lineTotal({ price: 10000, quantity: 0.1 + 0.2, unit: 'kg' })).toBe(3000);
    expect(lineTotal({ price: 1.15, quantity: 1000 })).toBe(1150);
  });

  it('keeps tiyin of a price until the end', () => {
    // 0.5 × 3.01 = 1.505 -> 2 (half up), where rounding the price first would give 2 × 0.5 = 1.5 -> 2 only by luck
    expect(lineTotal({ price: 3.01, quantity: 0.5, unit: 'kg' })).toBe(2);
    expect(lineTotal({ price: 3.01, quantity: 0.1, unit: 'kg' })).toBe(0);
  });
});

describe('modifiersTotal', () => {
  it('sums the deltas times their counts', () => {
    expect(modifiersTotal([{ priceDelta: 3000, quantity: 1 }, { priceDelta: 1500.5, quantity: 2 }])).toBe(6001);
  });

  it('is 0 for none', () => {
    expect(modifiersTotal(undefined)).toBe(0);
    expect(modifiersTotal([])).toBe(0);
  });
});
