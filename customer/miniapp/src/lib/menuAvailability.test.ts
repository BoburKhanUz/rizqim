import { describe, expect, it } from 'vitest';
import type { CartItem } from '../store/cartStore';
import { makeProduct, makeVariant } from '../test/factories';
import type { BranchAvailability } from '../types';
import { availabilityView, findCartIssues, isModifierUnavailable, isProductUnavailable, isVariantUnavailable } from './menuAvailability';

function branch(id: number, stopped: Partial<BranchAvailability['stopped']> = {}, soldOut: number[] = []): BranchAvailability {
  return { branch_id: id, stopped: { products: [], variants: [], modifiers: [], ...stopped }, sold_out: { variants: soldOut } };
}

const variant = makeVariant({ id: 11, uuid: 'v-11', product_id: 1, stock: 10 });
const product = makeProduct({ id: 1, variants: [variant] });

describe('availabilityView', () => {
  it('flags nothing without data: the server decides at checkout', () => {
    const view = availabilityView(undefined);
    expect(view.known).toBe(false);
    expect(isProductUnavailable(product, view)).toBe(false);
    expect(availabilityView([]).known).toBe(false);
  });

  it('counts a variant sold out only when every branch is out of it (the branch is not chosen yet)', () => {
    const one = availabilityView([branch(1, { variants: [11] }), branch(2)]);
    expect(isVariantUnavailable(product, variant, one)).toBe(false);

    const all = availabilityView([branch(1, { variants: [11] }), branch(2, {}, [11])]);
    expect(isVariantUnavailable(product, variant, all)).toBe(true);
    expect(isProductUnavailable(product, all)).toBe(true);
  });

  it('judges by the branch once it is known', () => {
    const branches = [branch(1, { variants: [11] }), branch(2)];
    expect(isVariantUnavailable(product, variant, availabilityView(branches, 1))).toBe(true);
    expect(isVariantUnavailable(product, variant, availabilityView(branches, 2))).toBe(false);
    // a branch the data does not list: the whole store
    expect(isVariantUnavailable(product, variant, availabilityView(branches, 99))).toBe(false);
  });

  it('a stopped product stops all its variants; a stopped modifier is its own', () => {
    const view = availabilityView([branch(1, { products: [1], modifiers: [5] })]);
    expect(isProductUnavailable(product, view)).toBe(true);
    expect(isVariantUnavailable(product, variant, view)).toBe(true);
    expect(isModifierUnavailable({ id: 5 }, view)).toBe(true);
    expect(isModifierUnavailable({ id: 6 }, view)).toBe(false);
  });

  it('a product is not sold out while one of its variants can be ordered', () => {
    const other = makeVariant({ id: 12, uuid: 'v-12', product_id: 1 });
    const two = makeProduct({ id: 1, variants: [variant, other] });
    expect(isProductUnavailable(two, availabilityView([branch(1, { variants: [11] })]))).toBe(false);
  });

  it('knows a variant with no stock left, with or without availability data', () => {
    const empty = makeVariant({ id: 13, uuid: 'v-13', product_id: 1, stock: 0 });
    expect(isVariantUnavailable(product, empty, availabilityView(undefined))).toBe(true);
  });
});

describe('findCartIssues', () => {
  const line = (overrides: Partial<CartItem> = {}): CartItem => ({
    id: '1-v-11', productId: 1, variantId: 'v-11', storeId: '1', name: 'Lavash', price: 25000, quantity: 1, ...overrides,
  });

  it('judges nothing until the menu has loaded', () => {
    expect(findCartIssues([line()], null, availabilityView([branch(1, { variants: [11] })]))).toEqual([]);
  });

  it('finds a line that sold out', () => {
    const issues = findCartIssues([line()], [product], availabilityView([branch(1, { variants: [11] })]));
    expect(issues).toHaveLength(1);
    expect(issues[0]).toMatchObject({ problem: 'sold_out', message: 'Tugadi' });
  });

  it('finds a dish the store took off the menu', () => {
    const issues = findCartIssues([line({ productId: 9, variantId: 'gone' })], [product], availabilityView(undefined));
    expect(issues[0].problem).toBe('removed');
  });

  it('finds a stopped modifier and names it', () => {
    const withModifier = line({ modifiers: [{ id: 5, uuid: 'm-5', groupId: 1, name: 'Pishloq', priceDelta: 3000, quantity: 1 }] });
    const issues = findCartIssues([withModifier], [product], availabilityView([branch(1, { modifiers: [5] })]));
    expect(issues[0]).toMatchObject({ problem: 'modifier_stopped' });
    expect(issues[0].message).toContain('"Pishloq" tugadi');
  });

  it('finds more in the cart than is in stock, over all lines of the variant', () => {
    const few = makeProduct({ id: 1, variants: [makeVariant({ id: 11, uuid: 'v-11', product_id: 1, stock: 3 })] });
    const lines = [line({ quantity: 2 }), line({ id: '1-v-11+m-1x1', quantity: 2 })];
    const issues = findCartIssues(lines, [few], availabilityView(undefined));
    expect(issues.map((issue) => issue.problem)).toEqual(['insufficient', 'insufficient']);
  });

  it('leaves a healthy cart alone', () => {
    expect(findCartIssues([line()], [product], availabilityView([branch(1)]))).toEqual([]);
  });
});
