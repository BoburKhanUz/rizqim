import type { CartItem, CartStoreInfo } from '../store/cartStore';
import type { Amount, Product, ProductVariant } from '../types';
import { parseAmount, toAmount } from './money';

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

// The cart line for one unit of a product (of `variant`, or of a product without variants).
export function buildCartItem(product: Product, variant: ProductVariant | undefined): CartItem {
  return {
    id: variant ? `${product.id}-${variant.uuid}` : `${product.id}`,
    productId: product.id,
    variantId: variant?.uuid,
    storeId: product.store_id.toString(),
    name: variant ? `${product.name} (${variant.name})` : product.name,
    price: variant ? toAmount(variant.price) : 0,
    quantity: 1,
    image: product.image_url ?? undefined,
  };
}
