import { describe, expect, it } from 'vitest';
import { cartLineTotal, type CartItem } from '../store/cartStore';
import { makeProduct, makeVariant } from '../test/factories';
import type { ModifierGroup } from '../types';
import { buildCartItem } from './catalog';
import { currentLine, currentLines, lineChange } from './cartPricing';
import { availabilityView, findCartIssues } from './menuAvailability';

const sauce = (priceDelta: string): ModifierGroup => ({
  id: 1, uuid: 'g-1', name: 'Sous', min_select: 0, max_select: 1, is_required: false, sort_order: 0,
  modifiers: [{ id: 5, uuid: 'm-5', modifier_group_id: 1, name: 'Pishloqli', price_delta: priceDelta, max_quantity: 1, sort_order: 0, is_active: true }],
});
const cola = (price: string) => makeProduct({ id: 3, variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price, stock: 20 })] });
const burger = (price: string, delta: string) =>
  makeProduct({ id: 1, variants: [makeVariant({ id: 1, uuid: 'v-burger', product_id: 1, price, stock: 20 })], modifier_groups: [sauce(delta)] });

const colaLine: CartItem = { ...buildCartItem(cola('8000.00'), cola('8000.00').variants![0]), quantity: 2 };
const burgerLine: CartItem = {
  id: '1-v-burger+m-5x1', productId: 1, variantId: 'v-burger', variantDbId: 1, storeId: '1', name: 'Burger', price: 30000, quantity: 2,
  modifiers: [{ id: 5, uuid: 'm-5', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 1 }],
};

describe('currentLine: the line at the menu\'s prices', () => {
  it('takes the variant\'s current price, so the cart total is what the server charges', () => {
    const fresh = currentLine(colaLine, [cola('12000.00')]);
    expect(fresh.price).toBe(12000);
    expect(cartLineTotal(fresh)).toBe(24000); // what OrderLineBuilder charges, not the 16 000 of the moment it was added
  });

  it('takes the modifiers\' current price deltas', () => {
    const fresh = currentLine(burgerLine, [burger('30000.00', '5000.00')]);
    expect(fresh.modifiers?.[0].priceDelta).toBe(5000);
    expect(cartLineTotal(fresh)).toBe(70000); // (30 000 + 5 000) × 2
  });

  it('keeps the saved line until the menu has loaded, and for a dish the menu no longer lists', () => {
    expect(currentLines([colaLine], null)).toEqual([colaLine]);
    expect(currentLine(colaLine, [burger('1.00', '1.00')])).toBe(colaLine);
  });
});

describe('lineChange', () => {
  it('is nothing when the menu still asks the same', () => {
    expect(lineChange(colaLine, currentLine(colaLine, [cola('8000.00')]))).toBeNull();
    expect(lineChange(burgerLine, currentLine(burgerLine, [burger('30000.00', '3000.00')]))).toBeNull();
  });

  it('is the old and the new line price when a price or a modifier delta changed', () => {
    expect(lineChange(colaLine, currentLine(colaLine, [cola('12000.00')]))).toEqual({ kind: 'price', from: 16000, to: 24000 });
    expect(lineChange(burgerLine, currentLine(burgerLine, [burger('30000.00', '5000.00')]))).toEqual({ kind: 'price', from: 66000, to: 70000 });
  });

  it('is a change of measure when the dish is now sold by weight, or the quantity no longer fits the step', () => {
    const byWeight = makeProduct({ id: 3, variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price: '8000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.500' })] });
    expect(lineChange(colaLine, currentLine(colaLine, [byWeight]))).toEqual({ kind: 'measure' });

    const meat: CartItem = { ...colaLine, quantity: 1.5, unit: 'kg', quantityStep: 0.5, minQuantity: 0.5 };
    const coarser = makeProduct({ id: 3, variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price: '8000.00', unit: 'kg', quantity_step: '1.000', min_quantity: '1.000' })] });
    expect(lineChange(meat, currentLine(meat, [coarser]))).toEqual({ kind: 'measure' });
  });
});

describe('findCartIssues: changes of the menu since the line was added', () => {
  const view = availabilityView([]);

  it('flags a changed price with both prices', () => {
    const issues = findCartIssues([colaLine], [cola('12000.00')], view);
    expect(issues).toHaveLength(1);
    expect(issues[0].problem).toBe('price_changed');
    expect(issues[0].message).toMatch(/^Narxi o'zgardi: 16\s000\sso'm → 24\s000\sso'm$/);
  });

  it('flags a changed unit or step: the line has to be added again', () => {
    const meat: CartItem = { ...colaLine, quantity: 1.5, unit: 'kg', quantityStep: 0.5, minQuantity: 0.5 };
    const coarser = makeProduct({ id: 3, variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price: '8000.00', unit: 'kg', quantity_step: '1.000', min_quantity: '1.000' })] });
    expect(findCartIssues([meat], [coarser], view)[0].problem).toBe('measure_changed');
  });

  it('flags a modifier the dish no longer offers', () => {
    const withoutSauce = makeProduct({ id: 1, variants: burger('30000.00', '3000.00').variants, modifier_groups: [] });
    const issues = findCartIssues([burgerLine], [withoutSauce], view);
    expect(issues[0]).toMatchObject({ problem: 'modifier_stopped' });
    expect(issues[0].message).toContain('"Pishloqli" endi taklif qilinmaydi');
  });

  it('a sold-out line is flagged as sold out, whatever its price did', () => {
    const issues = findCartIssues([colaLine], [cola('12000.00')], availabilityView([{ branch_id: 1, stopped: { products: [], variants: [3], modifiers: [] }, sold_out: { variants: [] } }]));
    expect(issues[0].problem).toBe('sold_out');
  });
});
