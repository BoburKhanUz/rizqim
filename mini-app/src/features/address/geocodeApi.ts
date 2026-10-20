import { isAxiosError } from 'axios';
import api from '../../services/api';
import type { ApiResponse } from '../../types';
import { toLatLng } from './coordinates';
import type { GeocodedPlace } from './types';

// The server answers within ~10 s even when its geocoding provider is slow or queued
const REQUEST_TIMEOUT_MS = 12000;

// Never trust the wire: a place without a usable address and point is dropped.
function parsePlace(raw: unknown): GeocodedPlace | null {
  if (typeof raw !== 'object' || raw === null) return null;
  const { address, lat, lng } = raw as Record<string, unknown>;
  const point = toLatLng(lat, lng);
  if (typeof address !== 'string' || address.trim() === '' || point === null) return null;
  return { address: address.trim(), ...point };
}

/**
 * The address at a point, via the backend (it talks to the geocoder; the app never does).
 * Resolves null when there is nothing at that point; rejects when the service is unavailable.
 */
export async function reverseGeocode(lat: number, lng: number, signal?: AbortSignal): Promise<GeocodedPlace | null> {
  try {
    const res = await api.get<ApiResponse<unknown>>('/geocode/reverse', { params: { lat, lng }, signal, timeout: REQUEST_TIMEOUT_MS });
    return parsePlace(res.data?.data);
  } catch (err) {
    if (isAxiosError(err) && err.response?.status === 404) return null;
    throw err;
  }
}

/**
 * Places matching a typed address, best match first (Uzbekistan only). Empty when nothing matches;
 * rejects when the service is unavailable.
 */
export async function searchAddress(query: string, signal?: AbortSignal): Promise<GeocodedPlace[]> {
  const res = await api.get<ApiResponse<unknown>>('/geocode/search', { params: { q: query }, signal, timeout: REQUEST_TIMEOUT_MS });
  const rows = Array.isArray(res.data?.data) ? res.data.data : [];
  return rows.map(parsePlace).filter((place): place is GeocodedPlace => place !== null);
}
