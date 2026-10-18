import { create } from 'zustand';

interface AppState {
  activeTab: 'home' | 'cart' | 'orders' | 'profile';
  homeSubView: 'feed' | 'store' | 'product';
  selectedStoreUuid: string | null;
  selectedProductId: number | null;
  searchQuery: string;
  isLoading: boolean;
  orderConfirmed: boolean;
  
  // Actions
  setActiveTab: (tab: 'home' | 'cart' | 'orders' | 'profile') => void;
  setHomeSubView: (view: 'feed' | 'store' | 'product') => void;
  setSelectedStoreUuid: (uuid: string | null) => void;
  setSelectedProductId: (id: number | null) => void;
  setSearchQuery: (query: string) => void;
  triggerLoading: (duration?: number) => void;
  setOrderConfirmed: (val: boolean) => void;
}

export const useAppStore = create<AppState>((set) => ({
  activeTab: 'home',
  homeSubView: 'feed',
  selectedStoreUuid: null,
  selectedProductId: null,
  searchQuery: '',
  isLoading: false,
  orderConfirmed: false,
  
  setActiveTab: (tab) => set({ activeTab: tab }),
  setHomeSubView: (view) => set({ homeSubView: view }),
  setSelectedStoreUuid: (uuid) => set({ selectedStoreUuid: uuid }),
  setSelectedProductId: (id) => set({ selectedProductId: id }),
  setSearchQuery: (query) => set({ searchQuery: query }),
  
  triggerLoading: (duration = 400) => {
    set({ isLoading: true });
    setTimeout(() => set({ isLoading: false }), duration);
  },
  
  setOrderConfirmed: (val) => set({ orderConfirmed: val }),
}));
