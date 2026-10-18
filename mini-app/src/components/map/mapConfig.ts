import type { LatLng } from './types';

const env = import.meta.env as Record<string, string | undefined>;

// The public OpenStreetMap tile server is fine for development and light traffic only (see its tile usage policy).
// For production set VITE_MAP_TILE_URL (and VITE_MAP_ATTRIBUTION) to a tile provider you have an account with,
// or to your own tile server.
const DEFAULT_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export const MAP_TILE_URL = env.VITE_MAP_TILE_URL?.trim() || DEFAULT_TILE_URL;
export const MAP_ATTRIBUTION = env.VITE_MAP_ATTRIBUTION?.trim() || DEFAULT_ATTRIBUTION;
export const MAP_MAX_ZOOM = 19;

// Only where the map OPENS while nothing has been picked yet (Tashkent). It is never used as an address location.
export const DEFAULT_MAP_CENTER: LatLng = { lat: 41.2995, lng: 69.2401 };
export const CITY_ZOOM = 12;
export const AREA_ZOOM = 14;
export const PIN_ZOOM = 17;
