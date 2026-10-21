import { cartLineTotal, type CartItem } from '../store/cartStore';
import type { Modifier, Product } from '../types';
import { toAmount } from './money';
import { isMeasuredUnit, isMultipleOfStep, minQuantityOf, quantityStepOf } from './quantity';

// A cart line keeps the price it was added with, but the server prices every line at checkout from the menu as it is
// then (OrderLineBuilder). So the cart shows and totals its lines at the loaded menu's prices (the variant's price per
// unit and the modifiers' price deltas), and a line whose price or measure changed since it was added is flagged until
// the customer accepts it (findCartIssues): the MainButton total, the delivery quote's subtotal (minimum order, free
// delivery) and the promo check all use what the server will charge.

// The modifier of a product by its uuid, as the menu offers it now (only active groups and modifiers are listed).
function modifierOf(product: Product, uuid: string): Modifier | undefined {
  for (const group of product.modifier_groups ?? []) {
    const modifier = group.modifiers.find((candidate) => candidate.uuid === uuid);
    if (modifier) return modifier;
  }
  return undefined;
}

// The line as the menu prices it now: the variant's price, unit and quantity rules and the modifiers' current deltas.
// A line whose dish, variant or modifier the menu does not list stays as it is (findCartIssues flags it).
export function currentLine(item: CartItem, products: readonly Product[] | null): CartItem {
  const product = products?.find((candidate) => candidate.id === item.productId);
  const variant = item.variantId ? product?.variants?.find((candidate) => candidate.uuid === item.variantId) : undefined;
  if (!product || !variant) return item;

  const modifiers = item.modifiers?.map((modifier) => {
    const fresh = modifierOf(product, modifier.uuid);
    return fresh ? { ...modifier, priceDelta: toAmount(fresh.price_delta) } : modifier;
  });
  const line: CartItem = { ...item, price: toAmount(variant.price) };
  delete line.unit;
  delete line.quantityStep;
  delete line.minQuantity;
  if (isMeasuredUnit(variant.unit)) {
    Object.assign(line, { unit: variant.unit, quantityStep: quantityStepOf(variant), minQuantity: minQuantityOf(variant) });
  }
  if (modifiers) line.modifiers = modifiers;
  return line;
}

export function currentLines(items: readonly CartItem[], products: readonly Product[] | null): CartItem[] {
  return products === null ? [...items] : items.map((item) => currentLine(item, products));
}

// A modifier of the line that the menu no longer offers on its dish (switched off or removed), or undefined.
export function missingModifier(item: CartItem, products: readonly Product[] | null) {
  const product = products?.find((candidate) => candidate.id === item.productId);
  if (!product || !Array.isArray(product.modifier_groups)) return undefined;
  return item.modifiers?.find((modifier) => modifierOf(product, modifier.uuid) === undefined);
}

export type LineChange = { kind: 'measure' } | { kind: 'price'; from: number; to: number };

// How the line differs from the same line at today's menu: sold in another unit, or a quantity the new step / minimum
// does not allow (the server would refuse it: the customer takes it out and adds it again), or another price.
export function lineChange(item: CartItem, fresh: CartItem): LineChange | null {
  const wasMeasured = isMeasuredUnit(item.unit);
  const isMeasured = isMeasuredUnit(fresh.unit);
  if (wasMeasured !== isMeasured || (isMeasured && item.unit !== fresh.unit)) return { kind: 'measure' };
  if (isMeasured) {
    const step = fresh.quantityStep ?? 0.1;
    const minimum = fresh.minQuantity ?? step;
    if (item.quantity < minimum || !isMultipleOfStep(item.quantity, step)) return { kind: 'measure' };
  }
  const from = cartLineTotal(item);
  const to = cartLineTotal(fresh);
  return from === to ? null : { kind: 'price', from, to };
}
