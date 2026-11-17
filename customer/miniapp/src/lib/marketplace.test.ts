import { describe, expect, it } from 'vitest';
import { buildCartItem, needsProductSheet } from './catalog';
import { addsDirectly, cardToProduct, isAcceptingOrders, nextStorePage, searchCards, storesOfPages } from './marketplace';
import type { ProductCardItem, StoreListItem } from '../types';

const card = (overrides: Partial<ProductCardItem> = {}): ProductCardItem => ({
  id: 3,
  uuid: 'p-3',
  store_id: 1,
  category_id: 2,
  name: 'Lavash',
  description: 'Tovuqli',
  image_url: null,
  batch_tracked: false,
  price: '25000.00',
  needs_options: false,
  variant: { id: 30, uuid: 'v-30', name: 'Standart', price: '25000.00', unit: 'piece', stock: 4 },
  store: { id: 1, uuid: 's-1', name: 'Somsa markazi', address: 'Chilonzor', logo_url: null },
  ...overrides,
});

describe('cards to the cart', () => {
  it('a card without options becomes the cart line the full product made', () => {
    const product = cardToProduct(card());
    expect(needsProductSheet(product)).toBe(false);
    expect(buildCartItem(product, product.variants[0])).toMatchObject({
      productId: 3,
      variantId: 'v-30',
      variantDbId: 30,
      storeId: '1',
      name: 'Lavash (Standart)',
      price: 25000,
      quantity: 1,
    });
  });

  it('an unknown stock does not limit the add (the server checks)', () => {
    expect(cardToProduct(card({ variant: { id: 30, uuid: 'v-30', name: null, price: '1.00', unit: 'piece', stock: null } })).variants[0]?.stock).toBe(Number.POSITIVE_INFINITY);
  });

  it('only a card without options and with its variant adds directly', () => {
    expect(addsDirectly(card())).toBe(true);
    expect(addsDirectly(card({ needs_options: true, variant: null }))).toBe(false);
    expect(addsDirectly(card({ needs_options: false, variant: null }))).toBe(false);
  });

  it('a store takes orders unless the server said it does not', () => {
    expect(isAcceptingOrders({ accepting_orders: false })).toBe(false);
    expect(isAcceptingOrders({ accepting_orders: true })).toBe(true);
    expect(isAcceptingOrders({})).toBe(true);
    expect(isAcceptingOrders(null)).toBe(true);
  });

  it('search looks at the dish, its description and its restaurant', () => {
    const cards = [card(), card({ id: 4, name: 'Cola', description: null, store: { id: 2, uuid: 's-2', name: 'Ichimliklar', address: null, logo_url: null } })];
    expect(searchCards(cards, 'tovuq').map((c) => c.id)).toEqual([3]);
    expect(searchCards(cards, ' ICHIM ').map((c) => c.id)).toEqual([4]);
    expect(searchCards(cards, '')).toHaveLength(2);
  });
});

describe('store pages', () => {
  it('the next page until the last one; none without meta', () => {
    expect(nextStorePage({ page: 1, per_page: 20, total: 45, last_page: 3 })).toBe(2);
    expect(nextStorePage({ page: 3, per_page: 20, total: 45, last_page: 3 })).toBeUndefined();
    expect(nextStorePage(undefined)).toBeUndefined();
  });

  it('each store once, in page order', () => {
    const store = (id: number) => ({ id, name: `S${id}` }) as StoreListItem;
    expect(storesOfPages([{ stores: [store(1), store(2)] }, { stores: [store(2), store(3)] }]).map((s) => s.id)).toEqual([1, 2, 3]);
  });
});
