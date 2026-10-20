import { beforeEach, describe, expect, it } from 'vitest';
import { useCartStore, type CartItem } from './cartStore';

const burger: CartItem = {
  id: '1-v1',
  productId: 1,
  variantId: 'v1',
  storeId: '10',
  name: 'Burger (Katta)',
  price: 30000,
  quantity: 1,
};
const cola: CartItem = { id: '2-v2', productId: 2, variantId: 'v2', storeId: '10', name: 'Cola (1 L)', price: 8000, quantity: 1 };
const otherStoreDish: CartItem = { id: '9-v9', productId: 9, variantId: 'v9', storeId: '20', name: 'Osh', price: 40000, quantity: 1 };

const cart = () => useCartStore.getState();

beforeEach(() => {
  cart().clearCart();
});

describe('addItem', () => {
  it('puts a first item in the cart and remembers the store it comes from', () => {
    const added = cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });

    expect(added).toBe(true);
    expect(cart().items).toEqual([burger]);
    expect(cart().storeName).toBe('Evos');
    expect(cart().storeAddress).toBe('Chilonzor 5');
  });

  it('falls back to the main branch when the store has no address', () => {
    cart().addItem(burger, { name: 'Evos', address: '' });

    expect(cart().storeAddress).toBe('Asosiy filial');
  });

  it('merges the same item into one line and adds the quantities', () => {
    cart().addItem(burger);
    cart().addItem({ ...burger, quantity: 2 });
    cart().addItem(burger);

    expect(cart().items).toHaveLength(1);
    expect(cart().items[0].quantity).toBe(4);
  });

  it('treats a missing or zero quantity as one unit', () => {
    cart().addItem({ ...burger, quantity: 0 });
    expect(cart().items[0].quantity).toBe(1);

    cart().addItem({ ...burger, quantity: 0 });
    expect(cart().items[0].quantity).toBe(2);
  });

  it('keeps different items of the same store as separate lines', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    cart().addItem(cola, { name: 'Evos', address: 'Chilonzor 5' });

    expect(cart().items.map((i) => i.id)).toEqual(['1-v1', '2-v2']);
  });

  it('refuses an item of another store and leaves the cart untouched', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    const before = cart().items;

    const added = cart().addItem(otherStoreDish, { name: 'Oshxona', address: 'Yunusobod 1' });

    expect(added).toBe(false);
    expect(cart().items).toBe(before);
    expect(cart().storeName).toBe('Evos');
  });

  it('only takes the store name from the first item', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    cart().addItem(cola, { name: 'Renamed', address: 'Elsewhere' });

    expect(cart().storeName).toBe('Evos');
    expect(cart().storeAddress).toBe('Chilonzor 5');
  });
});

describe('forceClearAndAdd', () => {
  it('replaces the whole cart with the new store\'s item and drops the old notes', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    cart().addItem(cola);
    cart().setSpecialInstructions('Achchiq bo\'lmasin');

    cart().forceClearAndAdd(otherStoreDish, { name: 'Oshxona', address: 'Yunusobod 1' });

    expect(cart().items).toEqual([otherStoreDish]);
    expect(cart().storeName).toBe('Oshxona');
    expect(cart().storeAddress).toBe('Yunusobod 1');
    expect(cart().specialInstructions).toBe('');
  });

  it('works without store info', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    cart().forceClearAndAdd({ ...otherStoreDish, quantity: 0 });

    expect(cart().items).toEqual([{ ...otherStoreDish, quantity: 1 }]);
    expect(cart().storeName).toBe('');
    expect(cart().storeAddress).toBe('Asosiy filial');
  });
});

describe('removeItem / updateQuantity', () => {
  beforeEach(() => {
    cart().addItem(burger);
    cart().addItem({ ...cola, quantity: 3 });
  });

  it('removes one line', () => {
    cart().removeItem('1-v1');

    expect(cart().items.map((i) => i.id)).toEqual(['2-v2']);
  });

  it('ignores an unknown id', () => {
    cart().removeItem('nope');
    cart().updateQuantity('nope', 5);

    expect(cart().items.map((i) => [i.id, i.quantity])).toEqual([['1-v1', 1], ['2-v2', 3]]);
  });

  it('sets a new quantity', () => {
    cart().updateQuantity('2-v2', 7);

    expect(cart().items.find((i) => i.id === '2-v2')?.quantity).toBe(7);
  });

  it('removes the line when the quantity drops to zero or below', () => {
    cart().updateQuantity('2-v2', 0);
    expect(cart().items.map((i) => i.id)).toEqual(['1-v1']);

    cart().updateQuantity('1-v1', -1);
    expect(cart().items).toEqual([]);
  });
});

describe('totals', () => {
  it('are zero for an empty cart', () => {
    expect(cart().getSubtotal()).toBe(0);
    expect(cart().getTotalItems()).toBe(0);
  });

  it('add up price times quantity and the number of units', () => {
    cart().addItem({ ...burger, quantity: 2 }); // 60 000
    cart().addItem({ ...cola, quantity: 3 }); // 24 000

    expect(cart().getSubtotal()).toBe(84000);
    expect(cart().getTotalItems()).toBe(5);
  });
});

describe('clearCart / replaceCart / special instructions', () => {
  it('clearCart empties everything, store and notes included', () => {
    cart().addItem(burger, { name: 'Evos', address: 'Chilonzor 5' });
    cart().setSpecialInstructions('Eshik oldiga qo\'ying');

    cart().clearCart();

    expect(cart().items).toEqual([]);
    expect(cart().storeName).toBe('');
    expect(cart().storeAddress).toBe('');
    expect(cart().specialInstructions).toBe('');
  });

  it('replaceCart keeps just the given item, as a single unit when no quantity is set', () => {
    cart().addItem(burger);
    cart().addItem(cola);

    cart().replaceCart({ ...cola, quantity: 0 });

    expect(cart().items).toEqual([{ ...cola, quantity: 1 }]);
  });
});

describe('persistence', () => {
  it('saves the cart to localStorage so it survives a reload', () => {
    cart().addItem({ ...burger, quantity: 2 }, { name: 'Evos', address: 'Chilonzor 5' });

    const saved = JSON.parse(localStorage.getItem('cart-storage') ?? 'null');
    expect(saved.state.items).toEqual([{ ...burger, quantity: 2 }]);
    expect(saved.state.storeName).toBe('Evos');
  });
});
