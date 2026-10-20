import { beforeEach, describe, expect, it } from 'vitest';
import { cartLineId, cartLineTotal, quantityOfVariant, useCartStore, type CartItem, type CartModifier } from './cartStore';

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

const cheese: CartModifier = { id: 5, uuid: 'm-5', groupId: 1, name: 'Pishloq', priceDelta: 3000, quantity: 1 };
const spicy: CartModifier = { id: 6, uuid: 'm-6', groupId: 1, name: 'Achchiq', priceDelta: 1000, quantity: 1 };
const withModifiers = (modifiers: CartModifier[], quantity = 1): CartItem => ({ ...burger, id: cartLineId(burger.id, modifiers), modifiers, quantity });
const meat: CartItem = {
  id: '3-v3', productId: 3, variantId: 'v3', storeId: '10', name: 'Qazi (kg)', price: 120000, quantity: 0.5, unit: 'kg', quantityStep: 0.5, minQuantity: 0.5,
};

describe('lines with modifiers', () => {
  it('keeps lines of one dish with different modifiers apart', () => {
    cart().addItem(burger);
    cart().addItem(withModifiers([cheese]));
    cart().addItem(withModifiers([cheese, spicy]));

    expect(cart().items).toHaveLength(3);
    expect(new Set(cart().items.map((item) => item.id)).size).toBe(3);
  });

  it('merges the same choices, whatever order they were picked in', () => {
    cart().addItem(withModifiers([cheese, spicy]));
    cart().addItem(withModifiers([spicy, cheese]));

    expect(cart().items).toHaveLength(1);
    expect(cart().items[0].quantity).toBe(2);
  });

  it('keeps the id of a line without modifiers as it was', () => {
    expect(cartLineId('1-v1', undefined)).toBe('1-v1');
    expect(cartLineId('1-v1', [])).toBe('1-v1');
  });

  it('prices a line with the modifiers on every piece', () => {
    expect(cartLineTotal(withModifiers([cheese, spicy], 2))).toBe(2 * (30000 + 3000 + 1000));
  });

  it('counts the stock of a variant over all its lines', () => {
    cart().addItem(burger);
    cart().addItem(withModifiers([cheese], 2));
    cart().addItem(cola);

    expect(quantityOfVariant(cart().items, 'v1')).toBe(3);
    expect(quantityOfVariant(cart().items, undefined)).toBe(0);
  });
});

describe('lines sold by weight', () => {
  it('adds fractions without float noise', () => {
    cart().addItem({ ...meat, quantity: 0.1 });
    cart().addItem({ ...meat, quantity: 0.2 });

    expect(cart().items[0].quantity).toBe(0.3);
  });

  it('prices the line like the server: 120 000 × 1.5 kg = 180 000, 33 333 × 0.5 rounds half up', () => {
    cart().addItem({ ...meat, quantity: 1.5 });
    expect(cart().getSubtotal()).toBe(180000);

    cart().clearCart();
    cart().addItem({ ...meat, price: 33333 });
    expect(cart().getSubtotal()).toBe(16667);
  });

  it('counts a weighed line as one dish in the badge', () => {
    cart().addItem({ ...meat, quantity: 2.5 });
    cart().addItem(burger);
    cart().addItem(burger);

    expect(cart().getTotalItems()).toBe(3);
  });

  it('sets a fractional quantity exactly', () => {
    cart().addItem(meat);
    cart().updateQuantity('3-v3', 0.1 + 0.2);

    expect(cart().items[0].quantity).toBe(0.3);
  });
});
