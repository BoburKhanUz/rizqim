import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface Address {
  id: number;
  title: string;
  address: string;
  lat: number;
  lng: number;
  apartment?: string;
  entrance?: string;
  floor?: string;
  intercom?: string;
  comment?: string;
  is_default: boolean;
  region_id?: number;
  district_id?: number;
  region_name?: string;
  district_name?: string;
}

interface AddressState {
  addresses: Address[];
  selectedAddressId: number | null;
  setAddresses: (addresses: Address[]) => void;
  setSelectedAddressId: (id: number | null) => void;
  addAddress: (address: Address) => void;
  deleteAddress: (id: number) => void;
}

export const useAddressStore = create<AddressState>()(
  persist(
    (set) => ({
      addresses: [],
      selectedAddressId: null,
      
      setAddresses: (addresses) => set({ addresses }),
      setSelectedAddressId: (id) => set({ selectedAddressId: id }),
      addAddress: (address) => set((state) => ({ 
        addresses: [...state.addresses, address],
        selectedAddressId: address.id 
      })),
      deleteAddress: (id) => set((state) => ({ 
        addresses: state.addresses.filter(a => a.id !== id),
        selectedAddressId: state.selectedAddressId === id ? (state.addresses[0]?.id || null) : state.selectedAddressId
      })),
    }),
    {
      name: 'address-storage',
    }
  )
);