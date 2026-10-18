import { beforeEach, describe, expect, it } from 'vitest';
import { useAddressStore } from '../../store/useAddressStore';
import type { Address } from '../../store/useAddressStore';

function address(overrides: Partial<Address> = {}): Address {
  return { id: 1, title: 'Uy', address: 'Chilonzor 12', lat: 41.3651, lng: 69.2872, is_default: false, ...overrides };
}

// What an address of the app BEFORE the map picker looked like in localStorage (zustand persist, version 0)
function saveOldStore(addresses: Address[], selectedAddressId: number | null = null) {
  localStorage.setItem('address-storage', JSON.stringify({ state: { addresses, selectedAddressId }, version: 0 }));
}

beforeEach(() => {
  useAddressStore.setState({ addresses: [], selectedAddressId: null });
});

describe('addresses saved by the app before the map picker', () => {
  it('lose the coordinates the app had made up for them, so the customer must place the pin', async () => {
    saveOldStore([
      address({ id: 1, lat: 40.7821, lng: 72.3442 }), // cart form default
      address({ id: 2, lat: 41.31, lng: 69.28 }), // profile fallback
      address({ id: 3, lat: 41.316755, lng: 69.123402 }), // cart fallback without branch coordinates
    ]);

    await useAddressStore.persist.rehydrate();

    for (const saved of useAddressStore.getState().addresses) {
      expect(saved.lat).toBeNull();
      expect(saved.lng).toBeNull();
    }
  });

  it('keep real coordinates and everything else about them', async () => {
    saveOldStore([address({ id: 7, title: 'Ofis', apartment: '12', region_id: 1, is_default: true })], 7);

    await useAddressStore.persist.rehydrate();

    const state = useAddressStore.getState();
    expect(state.addresses).toEqual([address({ id: 7, title: 'Ofis', apartment: '12', region_id: 1, is_default: true })]);
    expect(state.selectedAddressId).toBe(7);
  });

  it('are only cleaned once: a point picked on the map later is never second-guessed', async () => {
    localStorage.setItem(
      'address-storage',
      JSON.stringify({ state: { addresses: [address({ lat: 41.31, lng: 69.28 })], selectedAddressId: null }, version: 1 }),
    );

    await useAddressStore.persist.rehydrate();

    expect(useAddressStore.getState().addresses[0].lat).toBe(41.31);
  });
});

describe('updateAddress', () => {
  it('changes the given fields of one address only', () => {
    useAddressStore.setState({ addresses: [address({ id: 1 }), address({ id: 2, title: 'Ofis' })] });

    useAddressStore.getState().updateAddress(1, { lat: 41.2, lng: 69.1, address: 'Yangi manzil' });

    const [first, second] = useAddressStore.getState().addresses;
    expect(first).toMatchObject({ id: 1, title: 'Uy', address: 'Yangi manzil', lat: 41.2, lng: 69.1 });
    expect(second).toEqual(address({ id: 2, title: 'Ofis' }));
  });

  it('can take the point away again', () => {
    useAddressStore.setState({ addresses: [address({ id: 1 })] });

    useAddressStore.getState().updateAddress(1, { lat: null, lng: null });

    expect(useAddressStore.getState().addresses[0]).toMatchObject({ lat: null, lng: null });
  });

  it('ignores an unknown id', () => {
    useAddressStore.setState({ addresses: [address({ id: 1 })] });

    useAddressStore.getState().updateAddress(99, { title: 'X' });

    expect(useAddressStore.getState().addresses).toEqual([address({ id: 1 })]);
  });
});

describe('deleteAddress', () => {
  beforeEach(() => {
    useAddressStore.setState({ addresses: [address({ id: 1 }), address({ id: 2 }), address({ id: 3 })], selectedAddressId: 1 });
  });

  it('selects another address when the selected one goes, never the deleted one', () => {
    useAddressStore.getState().deleteAddress(1);

    const state = useAddressStore.getState();
    expect(state.addresses.map((a) => a.id)).toEqual([2, 3]);
    expect(state.selectedAddressId).toBe(2);
  });

  it('keeps the selection when another address goes', () => {
    useAddressStore.getState().deleteAddress(3);

    expect(useAddressStore.getState().selectedAddressId).toBe(1);
  });

  it('selects nothing once the last address is gone', () => {
    useAddressStore.setState({ addresses: [address({ id: 1 })], selectedAddressId: 1 });

    useAddressStore.getState().deleteAddress(1);

    expect(useAddressStore.getState()).toMatchObject({ addresses: [], selectedAddressId: null });
  });
});
