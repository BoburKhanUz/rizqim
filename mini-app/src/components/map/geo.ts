import type { LatLng } from './types';

// 6 decimals is about 10 cm: more digits only make GPS noise look like data
export function roundCoordinate(value: number): number {
  return Math.round(value * 1e6) / 1e6;
}

export function isSamePoint(a: LatLng, b: LatLng, tolerance = 1e-7): boolean {
  return Math.abs(a.lat - b.lat) <= tolerance && Math.abs(a.lng - b.lng) <= tolerance;
}

// A point that can be a real place: finite, on the globe, and not the (0, 0) of an unset value.
export function isValidPoint(lat: number, lng: number): boolean {
  return Number.isFinite(lat) && Number.isFinite(lng)
    && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
    && !(lat === 0 && lng === 0);
}
