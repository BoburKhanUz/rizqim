import type { MenuUnit } from '../types';
import { addQuantities, formatQuantity, isMeasuredUnit } from './quantity';

// Stock rules shared by the product list and the cart.
// Stock only limits ordering when the API actually sends a numeric value.

export type StockVariant = { stock?: unknown; unit?: MenuUnit };

export function getVariantStock(variant: StockVariant | null | undefined): number | null {
  const stock = variant?.stock;
  return typeof stock === 'number' && Number.isFinite(stock) ? stock : null;
}

// Known to have nothing left. A variant whose stock the API did not send is not out of stock.
export function isVariantOutOfStock(variant: StockVariant | null | undefined): boolean {
  const stock = getVariantStock(variant);
  return stock !== null && stock <= 0;
}

// A variant the store has not switched off. The API sends a boolean (0/1 on legacy drivers).
export function isVariantActive(variant: { is_active?: unknown }): boolean {
  return variant.is_active !== false && variant.is_active !== 0;
}

// A product is sold out only when every variant is known to have no stock left.
export function isProductSoldOut(product: { variants?: StockVariant[] | null }): boolean {
  const variants = product.variants;
  if (!variants || variants.length === 0) return false;
  return variants.every((v) => {
    const stock = getVariantStock(v);
    return stock !== null && stock <= 0;
  });
}

// Mirrors the product sheet: a variant with stock <= 0 can't be added, and the cart can never hold more
// of a variant than is in stock. Returns the Uzbek message to show when `amount` more (one unit by default, a step of
// 0.5 kg for an item sold by weight), on top of `inCart`, must not be added, otherwise null.
export function getAddBlockedMessage(variant: StockVariant | null | undefined, inCart: number, amount = 1): string | null {
  const stock = getVariantStock(variant);
  if (stock === null) return null; // API did not send stock: don't block
  if (stock <= 0) return 'Kechirasiz, bu mahsulot tugagan.';
  if (addQuantities(inCart, amount) > stock) {
    const left = isMeasuredUnit(variant?.unit) ? formatQuantity(stock, variant?.unit) : `${stock} dona`;
    return `Kechirasiz, omborda faqat ${left} qolgan.`;
  }
  return null;
}
