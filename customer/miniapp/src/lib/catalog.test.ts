import { describe, expect, it } from 'vitest';
import { makeProduct, makeVariant } from '../test/factories';
import { buildCartItem, DEFAULT_STORE_ADDRESS, formatUnitPrice, getProductBasePrice, getStoreInfo, needsProductSheet, parsePrice } from './catalog';

describe('parsePrice', () => {
  it('reads the decimal strings of the API and plain numbers', () => {
    expect(parsePrice('25000.00')).toBe(25000);
    expect(parsePrice('12.5')).toBe(12.5);
    expect(parsePrice(8000)).toBe(8000);
  });

  it('is NaN for anything that is not a price, so "no price" can be told from 0', () => {
    expect(parsePrice(undefined)).toBeNaN();
    expect(parsePrice(null)).toBeNaN();
    expect(parsePrice('abc')).toBeNaN();
    expect(parsePrice('')).toBeNaN();
    expect(parsePrice('0.00')).toBe(0);
  });
});

describe('getProductBasePrice', () => {
  it('is the cheapest variant', () => {
    const variants = [makeVariant({ price: '30000.00' }), makeVariant({ price: '18000.00' }), makeVariant({ price: '25000.00' })];

    expect(getProductBasePrice({ variants })).toBe(18000);
  });

  it('skips variants without a usable price', () => {
    const variants = [makeVariant({ price: '0.00' }), makeVariant({ price: 'abc' }), makeVariant({ price: '9000.00' })];

    expect(getProductBasePrice({ variants })).toBe(9000);
  });

  it('prefers a price on the product itself', () => {
    expect(getProductBasePrice({ price: '15000.00', variants: [makeVariant({ price: '9000.00' })] })).toBe(15000);
    expect(getProductBasePrice({ price: '0', variants: [makeVariant({ price: '9000.00' })] })).toBe(9000);
  });

  it('is zero when there is nothing to price', () => {
    expect(getProductBasePrice(null)).toBe(0);
    expect(getProductBasePrice(undefined)).toBe(0);
    expect(getProductBasePrice({})).toBe(0);
    expect(getProductBasePrice({ variants: [] })).toBe(0);
    expect(getProductBasePrice({ variants: [makeVariant({ price: '0.00' })] })).toBe(0);
  });
});

describe('getStoreInfo', () => {
  it('keeps the name and address the cart shows', () => {
    expect(getStoreInfo({ name: 'Evos', address: 'Chilonzor 5' })).toEqual({ name: 'Evos', address: 'Chilonzor 5' });
  });

  it('uses the main branch when the store has no address', () => {
    expect(getStoreInfo({ name: 'Evos', address: null })).toEqual({ name: 'Evos', address: DEFAULT_STORE_ADDRESS });
    expect(getStoreInfo({ name: 'Evos' })).toEqual({ name: 'Evos', address: DEFAULT_STORE_ADDRESS });
  });

  it('is undefined without a store', () => {
    expect(getStoreInfo(null)).toBeUndefined();
    expect(getStoreInfo(undefined)).toBeUndefined();
  });
});

describe('buildCartItem', () => {
  const product = makeProduct({ id: 7, store_id: 3, name: 'Lavash', image_url: 'https://cdn.example/lavash.jpg' });
  const variant = makeVariant({ uuid: 'var-uuid', name: 'Katta', price: '32000.00' });

  it('builds one unit of the chosen variant', () => {
    expect(buildCartItem(product, variant)).toEqual({
      id: '7-var-uuid',
      productId: 7,
      variantId: 'var-uuid',
      storeId: '3',
      name: 'Lavash (Katta)',
      price: 32000,
      quantity: 1,
      image: 'https://cdn.example/lavash.jpg',
      variantDbId: variant.id,
    });
  });

  it('gives the same line the same id every time, so adding twice merges in the cart', () => {
    expect(buildCartItem(product, variant).id).toBe(buildCartItem(product, variant).id);
  });

  it('handles a product without variants', () => {
    expect(buildCartItem(product, undefined)).toMatchObject({
      id: '7',
      variantId: undefined,
      name: 'Lavash',
      price: 0,
      quantity: 1,
    });
  });

  it('prices a variant with a broken price at 0, not NaN', () => {
    expect(buildCartItem(product, makeVariant({ price: 'n/a' })).price).toBe(0);
  });

  it('has no image instead of a null one', () => {
    expect(buildCartItem(makeProduct({ image_url: null }), variant).image).toBeUndefined();
  });
});

describe('buildCartItem with a selection', () => {
  const product = makeProduct({ id: 7, store_id: 3, name: 'Burger' });
  const variant = makeVariant({ uuid: 'var-uuid', name: 'Katta', price: '50000.00' });
  const cheese = { id: 6, uuid: 'm-6', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 1 };

  it('gives lines with different modifiers different ids, and the same choices the same id', () => {
    const plain = buildCartItem(product, variant);
    const withCheese = buildCartItem(product, variant, { modifiers: [cheese] });

    expect(withCheese.id).not.toBe(plain.id);
    expect(withCheese.id).toBe(buildCartItem(product, variant, { modifiers: [{ ...cheese }] }).id);
    expect(withCheese.modifiers).toEqual([cheese]);
    expect(plain).not.toHaveProperty('modifiers');
  });

  it('starts an item sold by weight at its least quantity and remembers the step', () => {
    const meat = makeVariant({ uuid: 'v-meat', price: '120000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '1.000' });

    expect(buildCartItem(product, meat)).toMatchObject({ quantity: 1, unit: 'kg', quantityStep: 0.5, minQuantity: 1, price: 120000 });
    expect(buildCartItem(product, meat, { quantity: 2.5 }).quantity).toBe(2.5);
  });

  it('starts at the first multiple of the step when the minimum is not one, so the server takes the order', () => {
    const meat = makeVariant({ uuid: 'v-meat', price: '100000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.300' });

    expect(buildCartItem(product, meat)).toMatchObject({ quantity: 0.5, quantityStep: 0.5, minQuantity: 0.5 });
  });
});

describe('needsProductSheet', () => {
  const one = [makeVariant()];

  it('is false for a dish with one plain variant', () => {
    expect(needsProductSheet({ variants: one, modifier_groups: [] })).toBe(false);
    expect(needsProductSheet({ variants: one })).toBe(false);
  });

  it('is true for variants to choose from, modifier groups or a weight', () => {
    expect(needsProductSheet({ variants: [makeVariant(), makeVariant({ id: 2 })] })).toBe(true);
    expect(needsProductSheet({
      variants: one,
      modifier_groups: [{ id: 1, uuid: 'g', name: 'Sous', min_select: 0, max_select: null, is_required: false, sort_order: 0, modifiers: [{ id: 1, uuid: 'm', modifier_group_id: 1, name: 'K', price_delta: '0.00', max_quantity: 1, sort_order: 0, is_active: true }] }],
    })).toBe(true);
    expect(needsProductSheet({ variants: [makeVariant({ unit: 'kg' })] })).toBe(true);
  });

  it('ignores a group without modifiers', () => {
    expect(needsProductSheet({ variants: one, modifier_groups: [{ id: 1, uuid: 'g', name: 'Bo\'sh', min_select: 0, max_select: null, is_required: false, sort_order: 0, modifiers: [] }] })).toBe(false);
  });
});

describe('formatUnitPrice', () => {
  it('adds the unit of an item sold by weight', () => {
    expect(formatUnitPrice(makeVariant({ price: '120000.00', unit: 'kg' }))).toBe("120\u00a0000\u00a0so'm / kg");
    expect(formatUnitPrice(makeVariant({ price: '25000.00' }))).toBe("25\u00a0000\u00a0so'm");
  });
});
