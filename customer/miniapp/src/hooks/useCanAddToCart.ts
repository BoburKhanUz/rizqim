import { useCallback } from 'react';
import { hapticFeedback } from '../lib/telegram';
import { showAlert } from '../lib/dialogs';
import { getAddBlockedMessage } from '../lib/stock';
import type { StockVariant } from '../lib/stock';
import { useCartStore } from '../store/cartStore';

/**
 * The stock rule of the product lists (see lib/stock.ts): a sold-out variant can't be added, and the cart never holds
 * more of a variant than is in stock. Returns a check for one more unit of `variant`, to run before addItem(); when it
 * says no, the customer has already been told why.
 */
export function useCanAddToCart() {
  const items = useCartStore((state) => state.items);

  return useCallback((variant: StockVariant | undefined, cartItemId: string): boolean => {
    const inCart = items.find((item) => item.id === cartItemId)?.quantity ?? 0;
    const blocked = getAddBlockedMessage(variant, inCart);
    if (blocked) {
      hapticFeedback('error');
      void showAlert(blocked);
      return false;
    }
    return true;
  }, [items]);
}
