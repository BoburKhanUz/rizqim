import { isSamePoint, isValidPoint } from '../../components/map/geo';
import type { LatLng } from './types';

// Decimals arrive from the API as strings ("41.311081"); anything that is not a number becomes null.
export function toCoordinate(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

// A usable point from two loosely typed values, or null.
export function toLatLng(lat: unknown, lng: unknown): LatLng | null {
  const latitude = toCoordinate(lat);
  const longitude = toCoordinate(lng);
  return latitude !== null && longitude !== null && isValidPoint(latitude, longitude) ? { lat: latitude, lng: longitude } : null;
}

// Points the Mini App used to invent for addresses typed by hand, before there was a map picker. They are
// kept only to recognise (and throw away) such addresses that are still saved on a customer's phone.
const INVENTED_POINTS: readonly LatLng[] = [
  { lat: 40.7821, lng: 72.3442 }, // initial value of the cart's address form
  { lat: 41.31, lng: 69.28 }, // profile form fallback
  { lat: 41.316755, lng: 69.123402 }, // cart fallback for a branch without coordinates
];

export function isInventedPoint(lat: unknown, lng: unknown): boolean {
  const point = toLatLng(lat, lng);
  return point !== null && INVENTED_POINTS.some((invented) => isSamePoint(point, invented));
}

// The cart used to copy the restaurant's own coordinates into an address typed by hand, which routed the order to
// that branch. A saved address sitting exactly on a branch is one of those, not a real delivery point.
export function isOnAnyBranch(point: LatLng, branches: ReadonlyArray<LatLng>): boolean {
  return branches.some((branch) => isSamePoint(point, branch));
}
