import { useState } from 'react';
import { hapticFeedback } from '../lib/telegram';
import { showAlert } from '../lib/dialogs';
import { buildCartItem, needsProductSheet } from '../lib/catalog';
import { NO_AVAILABILITY, isVariantUnavailable, type AvailabilityView } from '../lib/menuAvailability';
import { getAddBlockedMessage } from '../lib/stock';
import { quantityOfVariant, useCartStore, type CartStoreInfo, type PendingCartItem } from '../store/cartStore';
import type { ProductChoice } from '../components/ProductSheet';
import type { Product, ProductVariant } from '../types';

interface AddToCartOptions {
  // The restaurant the cart remembers for the dish
  storeInfoOf: (product: Product) => CartStoreInfo | undefined;
  // What the menu cannot offer now; the sheet greys those out and the add is refused (the server checks again anyway)
  availability?: AvailabilityView;
}

/**
 * Putting a dish into the cart from a product list (home, category, store page):
 *  - a dish with one plain variant goes in at once (one unit);
 *  - a dish with variants to choose from, modifier groups or sold by weight opens the product sheet first
 *    (`sheetProduct`; render <ProductSheet product={sheetProduct} onAdd={add} onClose={closeSheet} />);
 *  - a sold-out variant, or more than is in stock, is refused and the customer is told why;
 *  - food of another restaurant in the cart asks first (`pending`, <ReplaceCartDialog />).
 */
export function useAddToCart({ storeInfoOf, availability = NO_AVAILABILITY }: AddToCartOptions) {
  const items = useCartStore((state) => state.items);
  const addItem = useCartStore((state) => state.addItem);
  const [pending, setPending] = useState<PendingCartItem | null>(null);
  const [sheetProduct, setSheetProduct] = useState<Product | null>(null);

  const addVariant = (product: Product, variant: ProductVariant | undefined, choice?: Pick<ProductChoice, 'quantity' | 'modifiers'>): boolean => {
    if (variant && isVariantUnavailable(product, variant, availability)) {
      hapticFeedback('error');
      void showAlert(`Uzr, "${product.name}" hozir mavjud emas (tugadi).`);
      return false;
    }

    const cartItem = buildCartItem(product, variant, choice);
    // The stock is the variant's, however many lines (with other modifiers) of it are in the cart
    const blocked = getAddBlockedMessage(variant, quantityOfVariant(items, variant?.uuid), cartItem.quantity);
    if (blocked) {
      hapticFeedback('error');
      void showAlert(blocked);
      return false;
    }

    const storeInfo = storeInfoOf(product);
    const added = addItem(cartItem, storeInfo);
    if (!added) setPending({ item: cartItem, storeInfo });
    return added;
  };

  // The "+" of a dish card
  const start = (product: Product) => {
    hapticFeedback('medium');
    if (needsProductSheet(product)) {
      setSheetProduct(product);
      return;
    }
    addVariant(product, product.variants?.[0]);
  };

  return {
    start,
    addVariant,
    sheetProduct,
    openSheet: setSheetProduct,
    closeSheet: () => setSheetProduct(null),
    pending,
    clearPending: () => setPending(null),
  };
}
