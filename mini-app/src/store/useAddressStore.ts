import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { isInventedPoint } from '../features/address/coordinates';

export interface Address {
  id: number;
  title: string;
  address: string;
  // null = the spot on the map was never picked. Such an address can't be delivered to until it is fixed on the
  // map (Cart and Profile offer that); there is no default or made-up point.
  lat: number | null;
  lng: number | null;
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
  updateAddress: (id: number, changes: Partial<Omit<Address, 'id'>>) => void;
  deleteAddress: (id: number) => void;
}

// v1: addresses saved before the map picker could carry coordinates the app had made up. Those are dropped here,
// so the customer is asked to place the pin instead of the order going to a wrong branch or being refused as out of zone.
const STORE_VERSION = 1;

function dropInventedLocations(addresses: Address[]): Address[] {
  return addresses.map((address) => (isInventedPoint(address.lat, address.lng) ? { ...address, lat: null, lng: null } : address));
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
      updateAddress: (id, changes) => set((state) => ({
        addresses: state.addresses.map((address) => (address.id === id ? { ...address, ...changes } : address))
      })),
      deleteAddress: (id) => set((state) => ({
        addresses: state.addresses.filter(a => a.id !== id),
        // Deleting the selected address selects another one (never the one just deleted)
        selectedAddressId: state.selectedAddressId === id ? (state.addresses.find(a => a.id !== id)?.id ?? null) : state.selectedAddressId
      })),
    }),
    {
      name: 'address-storage',
      version: STORE_VERSION,
      migrate: (persisted, version) => {
        const state = persisted as Partial<AddressState> | undefined;
        if (version < 1 && Array.isArray(state?.addresses)) {
          return { ...state, addresses: dropInventedLocations(state.addresses) } as AddressState;
        }
        return persisted as AddressState;
      },
    }
  )
);
