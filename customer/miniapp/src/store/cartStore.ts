import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { lineTotal } from '../lib/linePrice';
import { addQuantities, isMeasuredUnit, roundQuantity } from '../lib/quantity';
import type { AttemptStorage, OpenAttempt } from '../lib/idempotency';
import type { MenuUnit } from '../types';

// One chosen modifier of a cart line (a "Sous: pishloqli" with the price it adds).
export interface CartModifier {
  id: number; // the modifier's numeric id (the stop-list and the availability use it)
  uuid: string; // what POST /orders sends
  groupId: number;
  name: string;
  priceDelta: number; // so'm, per piece (once per line for an item sold by weight)
  quantity: number; // how many times it is taken (1 unless the modifier allows more)
}

export interface CartItem {
  id: string; // unique identifier of the line: the variant, plus the chosen modifiers (cartLineId)
  productId: number;
  variantId?: string; // the variant's uuid (what POST /orders sends)
  storeId: string;
  name: string;
  price: number; // per unit: a piece, or a kilogram / litre for an item sold by weight
  quantity: number; // a fraction for an item sold by weight (1.5)
  image?: string;
  // Menu fields (P1-31). All optional: a cart saved by an older version has none and means pieces without modifiers.
  variantDbId?: number; // the variant's numeric id, for the availability checks
  unit?: MenuUnit;
  quantityStep?: number;
  minQuantity?: number;
  modifiers?: CartModifier[];
}

// The id of a cart line: lines of one variant with different modifier choices are different lines (the server keeps
// them apart too); the same choices in any order are the same line.
export function cartLineId(baseId: string, modifiers: readonly CartModifier[] | undefined): string {
  if (!modifiers || modifiers.length === 0) return baseId;
  const choices = modifiers
    .map((modifier) => `${modifier.uuid}x${modifier.quantity}`)
    .sort()
    .join(',');
  return `${baseId}+${choices}`;
}

// What the customer pays for one line (exactly the server's rounding, see lib/linePrice.ts).
export function cartLineTotal(item: CartItem): number {
  return lineTotal({ price: item.price, quantity: item.quantity, unit: item.unit, modifiers: item.modifiers });
}

// How much of one variant the cart holds over all its lines (the stock is the variant's, whatever the modifiers).
export function quantityOfVariant(items: readonly CartItem[], variantId: string | undefined): number {
  if (!variantId) return 0;
  return items.filter((item) => item.variantId === variantId).reduce((sum, item) => addQuantities(sum, item.quantity), 0);
}

// The store the cart's items come from, as the cart screen shows it.
export interface CartStoreInfo {
  name: string;
  address: string;
}

// An item that could not be added because the cart holds another store's items; kept until the customer decides.
export interface PendingCartItem {
  item: CartItem;
  storeInfo?: CartStoreInfo;
}

interface CartState {
  items: CartItem[];
  storeName: string;
  storeAddress: string;
  specialInstructions: string;
  // The checkout attempt whose answer has not come yet (its Idempotency-Key and body), kept with the cart so that a
  // retry after a lost answer reuses the key even after the cart was left or the Mini App reloaded (lib/idempotency.ts).
  // Cleared when the order is placed (2xx) or the cart is emptied; a changed body replaces it.
  checkoutAttempt: OpenAttempt | null;
  addItem: (item: CartItem, storeInfo?: CartStoreInfo) => boolean;
  forceClearAndAdd: (item: CartItem, storeInfo?: CartStoreInfo) => void;
  removeItem: (id: string) => void;
  updateQuantity: (id: string, quantity: number) => void;
  clearCart: () => void;
  replaceCart: (item: CartItem) => void;
  getSubtotal: () => number;
  getTotalItems: () => number;
  setSpecialInstructions: (instructions: string) => void;
  // Takes the menu's current price, modifier prices and quantity rules into the lines (the customer accepted them)
  refreshLines: (lines: readonly CartItem[]) => void;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      storeName: '',
      storeAddress: '',
      specialInstructions: '',
      checkoutAttempt: null,
      
      addItem: (newItem, storeInfo) => {
        let isSuccess = true;
        set((state) => {
          const isDifferentStore = state.items.length > 0 && state.items[0].storeId !== newItem.storeId;
          
          if (isDifferentStore) {
             isSuccess = false;
             return state; // Do not modify state, let UI handle the block
          }

          let newStoreName = state.storeName;
          let newStoreAddress = state.storeAddress;

          if (state.items.length === 0 && storeInfo) {
             newStoreName = storeInfo.name;
             newStoreAddress = storeInfo.address || 'Asosiy filial';
          }

          const existingItem = state.items.find(item => item.id === newItem.id);
          if (existingItem) {
            return {
              storeName: newStoreName,
              storeAddress: newStoreAddress,
              items: state.items.map(item => 
                item.id === newItem.id 
                  ? { ...item, quantity: addQuantities(item.quantity, newItem.quantity || 1) } 
                  : item
              )
            };
          }
          return { 
            storeName: newStoreName,
            storeAddress: newStoreAddress,
            items: [...state.items, { ...newItem, quantity: newItem.quantity || 1 }] 
          };
        });
        return isSuccess;
      },

      forceClearAndAdd: (newItem, storeInfo) => {
         set({
            items: [{ ...newItem, quantity: newItem.quantity || 1 }],
            storeName: storeInfo?.name || '',
            storeAddress: storeInfo?.address || 'Asosiy filial',
            specialInstructions: '',
            checkoutAttempt: null
         });
      },
      
      removeItem: (id) => {
        set((state) => ({
          items: state.items.filter(item => item.id !== id)
        }));
      },
      
      updateQuantity: (id, quantity) => {
        set((state) => {
          if (quantity <= 0) {
            return { items: state.items.filter(item => item.id !== id) };
          }
          return {
            items: state.items.map(item => 
              item.id === id ? { ...item, quantity: roundQuantity(quantity) } : item
            )
          };
        });
      },
      
      clearCart: () => set({ items: [], storeName: '', storeAddress: '', specialInstructions: '', checkoutAttempt: null }),
      
      replaceCart: (newItem) => set({ items: [{ ...newItem, quantity: newItem.quantity || 1 }] }),
      
      getSubtotal: () => {
        return get().items.reduce((total, item) => total + cartLineTotal(item), 0);
      },
      
      getTotalItems: () => {
        // A weighed line is one dish whatever its weight (1,5 kg is not "1.5 items")
        return get().items.reduce((total, item) => total + (isMeasuredUnit(item.unit) ? 1 : item.quantity), 0);
      },

      setSpecialInstructions: (specialInstructions) => set({ specialInstructions }),

      refreshLines: (lines) => {
        set((state) => ({
          items: state.items.map((item) => {
            const fresh = lines.find((line) => line.id === item.id);
            if (!fresh) return item;
            const { price, unit, quantityStep, minQuantity, modifiers } = fresh;
            return { ...item, price, unit, quantityStep, minQuantity, ...(modifiers ? { modifiers } : {}) };
          }),
        }));
      },
    }),
    {
      name: 'cart-storage',
    }
  )
);

// The cart's open checkout attempt as the storage of lib/idempotency.ts: it is persisted with the cart.
export const cartAttemptStorage: AttemptStorage = {
  get: () => useCartStore.getState().checkoutAttempt ?? null,
  set: (checkoutAttempt) => useCartStore.setState({ checkoutAttempt }),
};
