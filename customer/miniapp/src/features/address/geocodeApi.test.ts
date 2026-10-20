import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('../../services/api', () => ({ default: { get } }));

import { reverseGeocode, searchAddress } from './geocodeApi';

function failedRequest(status: number | undefined): AxiosError {
  const config: InternalAxiosRequestConfig = { url: '/geocode/reverse', method: 'get', headers: new AxiosHeaders() };
  const response = status === undefined ? undefined : { data: {}, status, statusText: String(status), headers: {}, config };
  return new AxiosError(
    status === undefined ? 'Network Error' : `Request failed with status code ${status}`,
    status === undefined ? AxiosError.ERR_NETWORK : AxiosError.ERR_BAD_RESPONSE,
    config,
    {},
    response,
  );
}

beforeEach(() => {
  get.mockReset();
});

describe('reverseGeocode', () => {
  it('asks the backend for the point and returns the address', async () => {
    get.mockResolvedValue({ data: { success: true, data: { address: ' Amir Temur 12, Toshkent ', lat: 41.2995, lng: 69.2401 } } });
    const controller = new AbortController();

    const place = await reverseGeocode(41.2995, 69.2401, controller.signal);

    expect(place).toEqual({ address: 'Amir Temur 12, Toshkent', lat: 41.2995, lng: 69.2401 });
    expect(get).toHaveBeenCalledTimes(1);
    const [url, options] = get.mock.calls[0];
    expect(url).toBe('/geocode/reverse');
    expect(options.params).toEqual({ lat: 41.2995, lng: 69.2401 });
    expect(options.signal).toBe(controller.signal);
    expect(options.timeout).toBeGreaterThan(0);
  });

  it('accepts coordinates sent as strings', async () => {
    get.mockResolvedValue({ data: { success: true, data: { address: 'Chilonzor', lat: '41.27', lng: '69.20' } } });

    await expect(reverseGeocode(41.27, 69.2)).resolves.toEqual({ address: 'Chilonzor', lat: 41.27, lng: 69.2 });
  });

  it('is null when there is nothing at that point (404)', async () => {
    get.mockRejectedValue(failedRequest(404));

    await expect(reverseGeocode(0.1, -30.2)).resolves.toBeNull();
  });

  it('is null for an answer without a usable address or point', async () => {
    for (const data of [null, {}, { address: '', lat: 41.3, lng: 69.2 }, { address: 'X', lat: null, lng: 69.2 }, { address: 'X', lat: 95, lng: 69.2 }]) {
      get.mockResolvedValue({ data: { success: true, data } });
      await expect(reverseGeocode(41.3, 69.2)).resolves.toBeNull();
    }
  });

  it('lets a failure of the service through, so the caller can tell "nothing there" from "could not ask"', async () => {
    get.mockRejectedValue(failedRequest(503));
    await expect(reverseGeocode(41.3, 69.2)).rejects.toThrow();

    get.mockRejectedValue(failedRequest(undefined));
    await expect(reverseGeocode(41.3, 69.2)).rejects.toThrow();
  });
});

describe('searchAddress', () => {
  it('sends the query and returns the places in the order received', async () => {
    get.mockResolvedValue({
      data: {
        success: true,
        data: [
          { address: 'Chilonzor tumani, Toshkent', lat: 41.2753, lng: 69.2038 },
          { address: "Chilonzor ko'chasi 12, Toshkent", lat: 41.2761, lng: 69.2049 },
        ],
      },
    });
    const controller = new AbortController();

    const places = await searchAddress('Chilonzor', controller.signal);

    expect(places.map((place) => place.address)).toEqual(['Chilonzor tumani, Toshkent', "Chilonzor ko'chasi 12, Toshkent"]);
    const [url, options] = get.mock.calls[0];
    expect(url).toBe('/geocode/search');
    expect(options.params).toEqual({ q: 'Chilonzor' });
    expect(options.signal).toBe(controller.signal);
  });

  it('drops rows that are not usable places', async () => {
    get.mockResolvedValue({
      data: {
        success: true,
        data: [{ address: 'Good', lat: 41.3, lng: 69.2 }, { address: 'No point' }, { lat: 41.3, lng: 69.2 }, 'junk', null],
      },
    });

    await expect(searchAddress('Good')).resolves.toEqual([{ address: 'Good', lat: 41.3, lng: 69.2 }]);
  });

  it('is empty when nothing matches or the answer is not a list', async () => {
    get.mockResolvedValue({ data: { success: true, data: [] } });
    await expect(searchAddress('zzzz')).resolves.toEqual([]);

    get.mockResolvedValue({ data: { success: true, data: { address: 'not a list' } } });
    await expect(searchAddress('zzzz')).resolves.toEqual([]);
  });

  it('lets a failure of the service through', async () => {
    get.mockRejectedValue(failedRequest(503));

    await expect(searchAddress('Chilonzor')).rejects.toThrow();
  });
});
