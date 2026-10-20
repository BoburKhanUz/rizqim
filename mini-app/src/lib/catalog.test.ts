import { describe, expect, it } from 'vitest';
import { makeProduct, makeVariant } from '../test/factories';
import { buildCartItem, DEFAULT_STORE_ADDRESS, getProductBasePrice, getStoreInfo, parsePrice } from './catalog';

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
