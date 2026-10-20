import { useCallback, useState } from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { MapPickerProps } from '../../components/map/MapPicker';

// Leaflet needs a real browser: the map is replaced by two buttons that "tap" different points
vi.mock('../../components/map/LazyMapPicker', () => ({
  default: ({ position, onPick }: MapPickerProps) => (
    <div data-testid="map" data-pin={position ? `${position.lat},${position.lng}` : ''}>
      <button type="button" onClick={() => onPick({ lat: 41.3, lng: 69.2 })}>tap A</button>
      <button type="button" onClick={() => onPick({ lat: 41.4, lng: 69.3 })}>tap B</button>
    </div>
  ),
}));
vi.mock('../../lib/telegram', () => ({ tg: {}, hapticFeedback: vi.fn() }));
vi.mock('./geocodeApi', () => ({ reverseGeocode: vi.fn(), searchAddress: vi.fn() }));
vi.mock('./geolocation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./geolocation')>()),
  getCurrentPosition: vi.fn(),
}));

import AddressLocationField from './AddressLocationField';
import { reverseGeocode, searchAddress } from './geocodeApi';
import { GeolocationError, getCurrentPosition } from './geolocation';
import type { AddressLocationValue } from './types';

// The way a form holds the field's value: merge patches into the latest state
function Form({ initial = { address: '', lat: null, lng: null } }: { initial?: AddressLocationValue }) {
  const [value, setValue] = useState(initial);
  const patch = useCallback((changes: Partial<AddressLocationValue>) => setValue((prev) => ({ ...prev, ...changes })), []);

  return (
    <>
      <AddressLocationField value={value} onChange={patch} />
      <output data-testid="value">{JSON.stringify(value)}</output>
    </>
  );
}

const saved = (): AddressLocationValue => JSON.parse(screen.getByTestId('value').textContent ?? '{}');
const addressInput = () => screen.getByLabelText('Manzil') as HTMLInputElement;
const searchButton = () => screen.getByRole('button', { name: 'Manzilni qidirish' }) as HTMLButtonElement;
const tap = (name: 'tap A' | 'tap B') => fireEvent.click(screen.getByRole('button', { name }));
const type = (text: string) => fireEvent.change(addressInput(), { target: { value: text } });
const pressEnter = () => fireEvent.keyDown(addressInput(), { key: 'Enter' });

// Lets the debounce and the (mocked) network answers go by
const advance = (ms: number) => act(async () => { await vi.advanceTimersByTimeAsync(ms); });
const flush = () => act(async () => { await Promise.resolve(); });

const place = (address: string, lat = 41.3, lng = 69.2) => ({ address, lat, lng });

beforeEach(() => {
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
});

describe('dropping a pin on the map', () => {
  it('sets the pin at once and fills in the address of that spot after a short pause', async () => {
    vi.mocked(reverseGeocode).mockResolvedValue(place("Amir Temur ko'chasi 12, Toshkent"));
    render(<Form />);

    tap('tap A');

    expect(saved()).toEqual({ address: '', lat: 41.3, lng: 69.2 });
    expect(screen.getByTestId('map').dataset.pin).toBe('41.3,69.2');
    expect(reverseGeocode).not.toHaveBeenCalled();

    await advance(500);

    expect(reverseGeocode).toHaveBeenCalledTimes(1);
    expect(vi.mocked(reverseGeocode).mock.calls[0].slice(0, 2)).toEqual([41.3, 69.2]);
    expect(addressInput().value).toBe("Amir Temur ko'chasi 12, Toshkent");
    expect(saved()).toEqual({ address: "Amir Temur ko'chasi 12, Toshkent", lat: 41.3, lng: 69.2 });
  });

  it('looks up only the last spot when the pin is corrected a few times in a row', async () => {
    vi.mocked(reverseGeocode).mockResolvedValue(place('Yunusobod, Toshkent', 41.4, 69.3));
    render(<Form />);

    tap('tap A');
    await advance(200);
    tap('tap B');
    await advance(500);

    expect(reverseGeocode).toHaveBeenCalledTimes(1);
    expect(vi.mocked(reverseGeocode).mock.calls[0].slice(0, 2)).toEqual([41.4, 69.3]);
    expect(saved()).toEqual({ address: 'Yunusobod, Toshkent', lat: 41.4, lng: 69.3 });
  });

  it('ignores the late answer for a spot the pin has already left', async () => {
    let answerForA: (value: ReturnType<typeof place>) => void = () => {};
    vi.mocked(reverseGeocode)
      .mockImplementationOnce(() => new Promise((resolve) => { answerForA = resolve; }))
      .mockResolvedValueOnce(place('Yunusobod, Toshkent', 41.4, 69.3));
    render(<Form />);

    tap('tap A');
    await advance(500); // the lookup for A is under way
    tap('tap B');
    await advance(500); // B is answered first
    expect(addressInput().value).toBe('Yunusobod, Toshkent');

    answerForA(place('Chilonzor, Toshkent')); // A's answer arrives late
    await flush();

    expect(addressInput().value).toBe('Yunusobod, Toshkent');
    expect(saved().lat).toBe(41.4);
  });

  it("keeps the customer's own text and offers the map's address instead", async () => {
    vi.mocked(reverseGeocode).mockResolvedValue(place('Amir Temur 12, Toshkent'));
    render(<Form initial={{ address: 'Mening uyim', lat: null, lng: null }} />);

    tap('tap A');
    await advance(500);

    expect(addressInput().value).toBe('Mening uyim');
    const suggestion = screen.getByRole('button', { name: /Amir Temur 12, Toshkent/ });

    fireEvent.click(suggestion);

    expect(addressInput().value).toBe('Amir Temur 12, Toshkent');
    expect(screen.queryByRole('button', { name: /Amir Temur 12, Toshkent/ })).toBeNull();
  });

  it('overwrites an address that was only ever filled in automatically', async () => {
    vi.mocked(reverseGeocode)
      .mockResolvedValueOnce(place('Birinchi manzil'))
      .mockResolvedValueOnce(place('Ikkinchi manzil', 41.4, 69.3));
    render(<Form />);

    tap('tap A');
    await advance(500);
    tap('tap B');
    await advance(500);

    expect(addressInput().value).toBe('Ikkinchi manzil');
  });

  it('keeps the pin and says so when the address could not be looked up', async () => {
    vi.mocked(reverseGeocode).mockRejectedValue(new Error('503'));
    render(<Form />);

    tap('tap A');
    await advance(500);

    expect(screen.getByText(/Manzil matnini aniqlab bo'lmadi/)).toBeInTheDocument();
    expect(saved()).toEqual({ address: '', lat: 41.3, lng: 69.2 });
  });

  it('says so when there is no address at that spot', async () => {
    vi.mocked(reverseGeocode).mockResolvedValue(null);
    render(<Form />);

    tap('tap A');
    await advance(500);

    expect(screen.getByText(/Bu nuqta uchun manzil topilmadi/)).toBeInTheDocument();
    expect(saved().lat).toBe(41.3);
  });
});

describe('searching for an address', () => {
  it('does nothing while typing: a search is only made on purpose', async () => {
    render(<Form />);

    type('Chilonzor');
    await advance(2000);

    expect(searchAddress).not.toHaveBeenCalled();
  });

  it('searches on Enter and on the search button, and lists what was found', async () => {
    vi.mocked(searchAddress).mockResolvedValue([place('Chilonzor tumani, Toshkent', 41.2753, 69.2038), place("Chilonzor ko'chasi 12, Toshkent", 41.2761, 69.2049)]);
    render(<Form />);

    type('Chilonzor');
    pressEnter();
    await flush();

    expect(searchAddress).toHaveBeenCalledTimes(1);
    expect(vi.mocked(searchAddress).mock.calls[0][0]).toBe('Chilonzor');
    expect(screen.getAllByRole('listitem')).toHaveLength(2);

    fireEvent.click(searchButton());
    await flush();

    expect(searchAddress).toHaveBeenCalledTimes(2);
  });

  it('moves the pin to the chosen result and uses its address, without a second lookup', async () => {
    vi.mocked(searchAddress).mockResolvedValue([place('Chilonzor tumani, Toshkent', 41.2753, 69.2038), place("Chilonzor ko'chasi 12, Toshkent", 41.2761, 69.2049)]);
    render(<Form />);

    type('Chilonzor');
    pressEnter();
    await flush();
    fireEvent.click(screen.getByRole('button', { name: /Chilonzor ko'chasi 12, Toshkent/ }));
    await advance(1000);

    expect(saved()).toEqual({ address: "Chilonzor ko'chasi 12, Toshkent", lat: 41.2761, lng: 69.2049 });
    expect(screen.getByTestId('map').dataset.pin).toBe('41.2761,69.2049');
    expect(screen.queryAllByRole('listitem')).toHaveLength(0);
    expect(reverseGeocode).not.toHaveBeenCalled();
  });

  it('asks for at least three characters', async () => {
    render(<Form />);

    type('ab');
    expect(searchButton().disabled).toBe(true);
    pressEnter();
    await flush();

    expect(searchAddress).not.toHaveBeenCalled();

    type('abc');
    expect(searchButton().disabled).toBe(false);
  });

  it('tells the customer when nothing was found or the search failed', async () => {
    vi.mocked(searchAddress).mockResolvedValueOnce([]).mockRejectedValueOnce(new Error('503'));
    render(<Form />);

    type('nowhere');
    pressEnter();
    await flush();
    expect(screen.getByText(/Hech narsa topilmadi/)).toBeInTheDocument();

    pressEnter();
    await flush();
    expect(screen.getByText(/Manzilni qidirib bo'lmadi/)).toBeInTheDocument();
    expect(saved().lat).toBeNull();
  });
});

describe('"Joylashuvimni aniqlash"', () => {
  const locate = () => fireEvent.click(screen.getByRole('button', { name: /Joylashuvimni aniqlash/ }));

  it('puts the pin where the device is (rounded to 6 decimals) and looks its address up right away', async () => {
    vi.mocked(getCurrentPosition).mockResolvedValue({ lat: 41.32751234567, lng: 69.28189999 });
    vi.mocked(reverseGeocode).mockResolvedValue(place('Mirzo Ulug‘bek, Toshkent', 41.3275, 69.2819));
    render(<Form />);

    locate();
    await flush();
    await flush();

    expect(vi.mocked(reverseGeocode).mock.calls[0].slice(0, 2)).toEqual([41.327512, 69.2819]);
    expect(saved()).toEqual({ address: 'Mirzo Ulug‘bek, Toshkent', lat: 41.327512, lng: 69.2819 });
  });

  it.each([
    ['denied', /ruxsat berilmagan/],
    ['unsupported', /joylashuvni aniqlay olmaydi/],
    ['timeout', /uzoq davom etdi/],
    ['unavailable', /aniqlab bo'lmadi/],
  ] as const)('explains a "%s" failure and leaves the pin empty', async (reason, message) => {
    vi.mocked(getCurrentPosition).mockRejectedValue(new GeolocationError(reason));
    render(<Form />);

    locate();
    await flush();

    expect(screen.getByText(message)).toBeInTheDocument();
    expect(saved()).toEqual({ address: '', lat: null, lng: null });
    expect(reverseGeocode).not.toHaveBeenCalled();
  });
});

describe('the pin status', () => {
  it('says the location is not set until there is a pin, and never invents one', () => {
    render(<Form />);

    expect(screen.getByText(/Joylashuv hali belgilanmagan/)).toBeInTheDocument();
    expect(screen.getByTestId('map').dataset.pin).toBe('');
    expect(saved()).toEqual({ address: '', lat: null, lng: null });
  });

  it('shows an existing pin straight away', () => {
    render(<Form initial={{ address: 'Chilonzor 12', lat: 41.3651, lng: 69.2872 }} />);

    expect(screen.getByText(/Joylashuv belgilandi/)).toBeInTheDocument();
    expect(screen.getByTestId('map').dataset.pin).toBe('41.3651,69.2872');
  });
});
