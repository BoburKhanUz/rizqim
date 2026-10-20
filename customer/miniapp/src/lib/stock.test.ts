import { describe, expect, it } from 'vitest';
import {
  getAddBlockedMessage,
  getVariantStock,
  isProductSoldOut,
  isVariantActive,
  isVariantOutOfStock,
} from './stock';

describe('getVariantStock', () => {
  it('returns a finite numeric stock, zero and negative included', () => {
    expect(getVariantStock({ stock: 7 })).toBe(7);
    expect(getVariantStock({ stock: 0 })).toBe(0);
    expect(getVariantStock({ stock: -2 })).toBe(-2);
  });

  it('is null whenever the API did not send a usable number', () => {
    expect(getVariantStock(undefined)).toBeNull();
    expect(getVariantStock(null)).toBeNull();
    expect(getVariantStock({})).toBeNull();
    expect(getVariantStock({ stock: null })).toBeNull();
    expect(getVariantStock({ stock: '5' })).toBeNull();
    expect(getVariantStock({ stock: Number.NaN })).toBeNull();
    expect(getVariantStock({ stock: Number.POSITIVE_INFINITY })).toBeNull();
  });
});

describe('isVariantOutOfStock', () => {
  it('is true only for a known stock of zero or less', () => {
    expect(isVariantOutOfStock({ stock: 0 })).toBe(true);
    expect(isVariantOutOfStock({ stock: -1 })).toBe(true);
    expect(isVariantOutOfStock({ stock: 1 })).toBe(false);
  });

  it('does not block a variant whose stock is unknown', () => {
    expect(isVariantOutOfStock({})).toBe(false);
    expect(isVariantOutOfStock({ stock: null })).toBe(false);
    expect(isVariantOutOfStock(undefined)).toBe(false);
  });
});

describe('isVariantActive', () => {
  it('is false for an explicitly switched off variant (boolean or legacy 0)', () => {
    expect(isVariantActive({ is_active: false })).toBe(false);
    expect(isVariantActive({ is_active: 0 })).toBe(false);
  });

  it('is true for on, legacy 1 and for an absent flag', () => {
    expect(isVariantActive({ is_active: true })).toBe(true);
    expect(isVariantActive({ is_active: 1 })).toBe(true);
    expect(isVariantActive({})).toBe(true);
  });
});

describe('isProductSoldOut', () => {
  it('is false when there are no variants to judge by', () => {
    expect(isProductSoldOut({})).toBe(false);
    expect(isProductSoldOut({ variants: null })).toBe(false);
    expect(isProductSoldOut({ variants: [] })).toBe(false);
  });

  it('is true when every variant is known to be empty', () => {
    expect(isProductSoldOut({ variants: [{ stock: 0 }, { stock: -3 }] })).toBe(true);
  });

  it('is false as soon as one variant has stock', () => {
    expect(isProductSoldOut({ variants: [{ stock: 0 }, { stock: 2 }] })).toBe(false);
  });

  it('is false when a variant stock is unknown: it must not be blocked on a guess', () => {
    expect(isProductSoldOut({ variants: [{ stock: 0 }, {}] })).toBe(false);
  });
});

describe('getAddBlockedMessage', () => {
  it('lets anything through when the stock is unknown', () => {
    expect(getAddBlockedMessage({}, 500)).toBeNull();
    expect(getAddBlockedMessage(undefined, 0)).toBeNull();
    expect(getAddBlockedMessage(null, 0)).toBeNull();
  });

  it('blocks a variant with no stock, even when none is in the cart', () => {
    expect(getAddBlockedMessage({ stock: 0 }, 0)).toBe('Kechirasiz, bu mahsulot tugagan.');
    expect(getAddBlockedMessage({ stock: -1 }, 0)).toBe('Kechirasiz, bu mahsulot tugagan.');
  });

  it('allows adding one more unit up to the stock and blocks the one after', () => {
    expect(getAddBlockedMessage({ stock: 3 }, 0)).toBeNull();
    expect(getAddBlockedMessage({ stock: 3 }, 2)).toBeNull(); // the 3rd unit is the last one in stock
    expect(getAddBlockedMessage({ stock: 3 }, 3)).toBe('Kechirasiz, omborda faqat 3 dona qolgan.');
    expect(getAddBlockedMessage({ stock: 3 }, 10)).toBe('Kechirasiz, omborda faqat 3 dona qolgan.');
  });
});

describe('getAddBlockedMessage for an item sold by weight', () => {
  const meat = { stock: 1.2, unit: 'kg' as const };

  it('adds a step at a time and says what is left in kg', () => {
    expect(getAddBlockedMessage(meat, 0.5, 0.5)).toBeNull();
    expect(getAddBlockedMessage(meat, 1, 0.5)).toBe('Kechirasiz, omborda faqat 1,2\u00a0kg qolgan.');
  });

  it('does not trip over float noise at the limit', () => {
    expect(getAddBlockedMessage({ stock: 0.3, unit: 'kg' }, 0.1, 0.2)).toBeNull();
  });
});
