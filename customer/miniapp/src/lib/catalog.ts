import { cartLineId, type CartItem, type CartModifier, type CartStoreInfo } from '../store/cartStore';
import type { Amount, Product, ProductVariant } from '../types';
import { isCombo, type CartComboPart } from './combo';
import { formatMoney } from './format';
import { parseAmount, toAmount } from './money';
import { groupsOf } from './modifiers';
import { isMeasuredUnit, minQuantityOf, quantityStepOf, unitLabel } from './quantity';

// A price as a number, or NaN when it is not one: for the callers that must tell "no price" from 0
// (everything that only adds up or shows prices uses toAmount instead).
export function parsePrice(value: Amount | null | undefined): number {
  return parseAmount(value) ?? Number.NaN;
}

// The price to show on a product card: the product's own price if it has one, else the cheapest variant.
export function getProductBasePrice(
  product: { price?: Amount; variants?: Pick<ProductVariant, 'price'>[] | null } | null | undefined,
): number {
  if (!product) return 0;
  if (product.price && toAmount(product.price) > 0) {
    return toAmount(product.price);
  }
  if (product.variants && product.variants.length > 0) {
    const prices = product.variants.map((v) => toAmount(v.price)).filter((price) => price > 0);
    if (prices.length > 0) {
      return Math.min(...prices);
    }
  }
  return 0;
}

export const DEFAULT_STORE_ADDRESS = 'Asosiy filial';

// What the cart remembers about the store its items come from.
export function getStoreInfo(
  store: { name: string; address?: string | null } | null | undefined,
): CartStoreInfo | undefined {
  return store ? { name: store.name, address: store.address || DEFAULT_STORE_ADDRESS } : undefined;
}

// What the customer picked on a product: how much of it (a fraction for an item sold by weight) and the modifiers.
export interface CartSelection {
  quantity?: number;
  modifiers?: CartModifier[];
  combo?: CartComboPart[];
}

// The cart line for one unit of a product (of `variant`, or of a product without variants), or for the quantity and
// modifiers of `selection`. Lines of one variant with different modifiers get different ids.
export function buildCartItem(product: Product, variant: ProductVariant | undefined, selection: CartSelection = {}): CartItem {
  const modifiers = selection.modifiers && selection.modifiers.length > 0 ? selection.modifiers : undefined;
  const combo = selection.combo && selection.combo.length > 0 ? selection.combo : undefined;
  const measured = isMeasuredUnit(variant?.unit);
  return {
    id: cartLineId(variant ? `${product.id}-${variant.uuid}` : `${product.id}`, modifiers, combo),
    productId: product.id,
    variantId: variant?.uuid,
    storeId: product.store_id.toString(),
    name: variant ? `${product.name} (${variant.name})` : product.name,
    price: variant ? toAmount(variant.price) : 0,
    quantity: selection.quantity ?? (measured ? minQuantityOf(variant) : 1),
    image: product.image_url ?? undefined,
    variantDbId: variant?.id,
    ...(measured && variant ? { unit: variant.unit, quantityStep: quantityStepOf(variant), minQuantity: minQuantityOf(variant) } : {}),
    ...(modifiers ? { modifiers } : {}),
    ...(combo ? { combo } : {}),
  };
}

// Whether adding the dish needs its sheet first: variants to choose from, modifier groups to fill in, or a weight to pick.
export function needsProductSheet(product: Pick<Product, 'variants' | 'modifier_groups'> & Partial<Pick<Product, 'combo'>>): boolean {
  const variants = product.variants ?? [];
  // P6-31: a combo opens its sheet to pick the parts
  return variants.length > 1 || groupsOf(product).length > 0 || variants.some((variant) => isMeasuredUnit(variant.unit)) || isCombo({ combo: product.combo ?? null });
}

// "33 000 so'm / kg" for an item sold by weight, "33 000 so'm" otherwise.
export function formatUnitPrice(variant: Pick<ProductVariant, 'price' | 'unit'>): string {
  const price = formatMoney(variant.price);
  return isMeasuredUnit(variant.unit) ? `${price} / ${unitLabel(variant.unit)}` : price;
}
