// Stock rules shared by the product list and the cart.
// Stock only limits ordering when the API actually sends a numeric value.

export type StockVariant = { stock?: unknown };

export function getVariantStock(variant: StockVariant | null | undefined): number | null {
  const stock = variant?.stock;
  return typeof stock === 'number' && Number.isFinite(stock) ? stock : null;
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

// Mirrors VariantSelectorModal: a variant with stock <= 0 can't be added, and the cart can
// never hold more of a variant than is in stock. Returns the Uzbek message to show when one
// more unit (on top of `inCart`) must not be added, otherwise null.
export function getAddBlockedMessage(variant: StockVariant | null | undefined, inCart: number): string | null {
  const stock = getVariantStock(variant);
  if (stock === null) return null; // API did not send stock: don't block
  if (stock <= 0) return 'Kechirasiz, bu mahsulot tugagan.';
  if (inCart + 1 > stock) return `Kechirasiz, omborda faqat ${stock} dona qolgan.`;
  return null;
}
