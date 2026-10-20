import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface CartItem {
  id: string; // unique identifier (variant id)
  productId: number;
  variantId?: string;
  storeId: string;
  name: string;
  price: number;
  quantity: number;
  image?: string;
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
  addItem: (item: CartItem, storeInfo?: CartStoreInfo) => boolean;
  forceClearAndAdd: (item: CartItem, storeInfo?: CartStoreInfo) => void;
  removeItem: (id: string) => void;
  updateQuantity: (id: string, quantity: number) => void;
  clearCart: () => void;
  replaceCart: (item: CartItem) => void;
  getSubtotal: () => number;
  getTotalItems: () => number;
  setSpecialInstructions: (instructions: string) => void;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      storeName: '',
      storeAddress: '',
      specialInstructions: '',
      
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
                  ? { ...item, quantity: item.quantity + (newItem.quantity || 1) } 
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
            specialInstructions: ''
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
              item.id === id ? { ...item, quantity } : item
            )
          };
        });
      },
      
      clearCart: () => set({ items: [], storeName: '', storeAddress: '', specialInstructions: '' }),
      
      replaceCart: (newItem) => set({ items: [{ ...newItem, quantity: newItem.quantity || 1 }] }),
      
      getSubtotal: () => {
        return get().items.reduce((total, item) => total + (item.price * item.quantity), 0);
      },
      
      getTotalItems: () => {
        return get().items.reduce((total, item) => total + item.quantity, 0);
      },

      setSpecialInstructions: (specialInstructions) => set({ specialInstructions })
    }),
    {
      name: 'cart-storage',
    }
  )
);
